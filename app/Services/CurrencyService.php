<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Setting;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The one place that knows what money is worth.
 *
 * Everything financial in this system is stored and posted in the **base**
 * currency. Conversion here is for display: showing a shopper a price in the
 * currency they think in. It never changes what is charged, what an invoice
 * says, or what reaches the ledger — those stay in the base currency, and the
 * screens that take money say so out loud.
 *
 * That boundary is the whole design. Converting a total on the way *in* would
 * mean the books hold amounts at rates nobody recorded, and a rate that moved
 * between the order and the payment would leave the receivable unsettleable by
 * a difference no entry explains. Doing that properly needs foreign-currency
 * postings and a realised-gain account; until that exists, conversion stays on
 * the display side where being approximate is honest rather than dangerous.
 */
class CurrencyService
{
    private const CACHE_KEY = 'currencies.active.v1';

    private const CACHE_TTL = 300;

    /** Fallback used before the migration has run, or if the table is empty. */
    private const FALLBACK = [
        'code' => 'SAR',
        'symbol' => 'ر.س',
        'decimal_places' => 2,
        'rounding_step' => 0,
    ];

    /**
     * Active currencies with their current rate, cheapest-to-read form.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function active(): Collection
    {
        if (! Schema::hasTable('currencies')) {
            return collect();
        }

        $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return Currency::query()
                ->active()
                ->orderByDesc('is_base')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency) => $this->present($currency))
                ->all();
        });

        return collect($rows);
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** The currency the books are kept in. */
    public function base(): ?Currency
    {
        if (! Schema::hasTable('currencies')) {
            return null;
        }

        return Currency::query()->where('is_base', true)->first()
            ?? Currency::query()->active()->orderBy('id')->first();
    }

    /** The base currency's code, safe to call before anything is set up. */
    public function baseCode(): string
    {
        return $this->base()?->code ?? self::FALLBACK['code'];
    }

    /**
     * Units of `$code` per one unit of the base currency.
     *
     * Returns `null` rather than guessing when no rate has been entered: a
     * missing rate is a thing the admin has to fix, and inventing 1.0 would
     * quietly price a dollar at a riyal.
     */
    public function rateFor(string $code, ?DateTimeInterface $moment = null): ?string
    {
        $code = strtoupper(trim($code));

        if ($code === $this->baseCode()) {
            return '1';
        }

        $currency = $this->find($code);
        if (! $currency) {
            return null;
        }

        return $currency->rateAt($moment)?->rate;
    }

    /**
     * Converts an amount from the base currency into `$code`, for display.
     *
     * Returns `null` when there is no rate, so the caller shows the base amount
     * rather than a number it cannot justify.
     */
    public function convertFromBase(float|int|string $amount, string $code, ?DateTimeInterface $moment = null): ?float
    {
        $rate = $this->rateFor($code, $moment);
        if ($rate === null) {
            return null;
        }

        $currency = $this->find($code);
        $converted = (float) $amount * (float) $rate;

        return $this->round($converted, $currency);
    }

    /** The reverse, for reading a price a user typed in their own currency. */
    public function convertToBase(float|int|string $amount, string $code, ?DateTimeInterface $moment = null): ?float
    {
        $rate = (float) ($this->rateFor($code, $moment) ?? 0);
        if ($rate <= 0) {
            return null;
        }

        return $this->round((float) $amount / $rate, $this->base());
    }

    /**
     * Applies the currency's own rounding.
     *
     * `rounding_step` exists because decimal places are not the whole story: a
     * price of 12,347 in a currency whose smallest note is 500 is false
     * precision, and shops quote the step.
     */
    public function round(float $amount, ?Currency $currency): float
    {
        $step = (int) ($currency?->rounding_step ?? 0);

        if ($step > 0) {
            return (float) (round($amount / $step) * $step);
        }

        return round($amount, (int) ($currency?->decimal_places ?? 2));
    }

    /** Formats an amount the way the currency asks to be written. */
    public function format(float|int|string $amount, ?string $code = null): string
    {
        $currency = $code ? $this->find($code) : $this->base();

        $decimals = (int) ($currency?->decimal_places ?? self::FALLBACK['decimal_places']);
        $symbol = (string) ($currency?->symbol ?? self::FALLBACK['symbol']);

        return number_format((float) $amount, $decimals).' '.$symbol;
    }

    public function find(string $code): ?Currency
    {
        if (! Schema::hasTable('currencies')) {
            return null;
        }

        return Currency::query()->where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Records a new rate for a currency.
     *
     * Appends rather than updates: the old rate stays readable, which is what
     * lets a past order be shown at the rate that applied when it was placed.
     *
     * @throws RuntimeException
     */
    public function recordRate(Currency $currency, string|float $rate, ?DateTimeInterface $effectiveAt = null, ?string $note = null): CurrencyRate
    {
        if ((float) $rate <= 0) {
            throw new RuntimeException('سعر الصرف يجب أن يكون أكبر من صفر.');
        }

        if ($currency->is_base && (float) $rate !== 1.0) {
            throw new RuntimeException('عملة الأساس تساوي واحداً دائماً — لا يمكن تغيير سعرها.');
        }

        $record = $currency->rates()->create([
            'rate' => $rate,
            'effective_at' => $effectiveAt ?? now(),
            'created_by' => auth()->id(),
            'note' => $note,
        ]);

        $this->flushCache();

        return $record;
    }

    /**
     * Makes one currency the base.
     *
     * Deliberately does **not** re-express stored amounts. Every figure in the
     * database is a number in the old base, and silently reinterpreting them as
     * the new one would restate every historical order and journal entry by the
     * rate between them. Switching base is an accounting event, and the screen
     * says so rather than this method pretending otherwise.
     */
    public function setBase(Currency $currency): void
    {
        Currency::query()->where('id', '!=', $currency->id)->update(['is_base' => false]);
        $currency->forceFill(['is_base' => true, 'is_active' => true])->save();

        // The base is worth one of itself; make sure a row says so.
        if (! $currency->rateAt()) {
            $currency->rates()->create([
                'rate' => 1,
                'effective_at' => now(),
                'created_by' => auth()->id(),
                'note' => 'Base currency',
            ]);
        }

        $this->flushCache();
        $this->syncDefaultCurrencySetting($currency->code);
    }

    /**
     * Codes the platform may use as its accounting / default currency.
     *
     * Falls back to the hardcoded list the settings screens used before the
     * currencies table existed, so validation still works mid-migration.
     *
     * @return list<string>
     */
    public function selectableCodes(): array
    {
        $codes = $this->active()->pluck('code')->filter()->values()->all();

        if ($codes !== []) {
            return $codes;
        }

        return [self::FALLBACK['code'], 'USD', 'EUR', 'AED', 'EGP', 'SYP'];
    }

    /**
     * Makes `$code` the base currency when it differs from the current one.
     *
     * Settings and the currencies screen both call this so `default_currency`
     * and `currencies.is_base` cannot drift apart.
     */
    public function applyDefaultCurrency(string $code): void
    {
        $code = strtoupper(trim($code));

        if ($code === '' || $code === $this->baseCode()) {
            $this->syncDefaultCurrencySetting($this->baseCode());

            return;
        }

        $currency = $this->find($code);

        if (! $currency) {
            throw new RuntimeException("العملة {$code} غير معرّفة في إدارة العملات.");
        }

        $this->setBase($currency);
    }

    /**
     * Keeps the legacy `default_currency` setting aligned with the base row.
     *
     * Every screen that still reads the setting (formatters, JSON-LD, the
     * storefront boot payload) then sees the same code the books use.
     */
    public function syncDefaultCurrencySetting(?string $code = null): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $code = strtoupper(trim((string) ($code ?? $this->baseCode())));

        if ($code === '') {
            return;
        }

        Setting::set('default_currency', $code);
    }

    /**
     * The wire shape used by the storefront, the admin screens and the app.
     *
     * @return array<string, mixed>
     */
    public function present(Currency $currency): array
    {
        $rate = $currency->rateAt();

        return [
            'code' => $currency->code,
            'name' => $currency->displayName(),
            'name_ar' => $currency->name_ar,
            'name_en' => $currency->name_en,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
            'rounding_step' => $currency->rounding_step,
            'is_base' => $currency->is_base,
            // Null means "no rate entered yet" — the clients show the base
            // amount instead of converting by a number nobody supplied.
            'rate' => $rate?->rate,
            'rate_effective_at' => $rate?->effective_at?->toIso8601String(),
        ];
    }
}
