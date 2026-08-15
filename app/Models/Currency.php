<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'symbol',
        'decimal_places',
        'rounding_step',
        'is_base',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'rounding_step' => 'integer',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Codes are identity here, so 'usd' and 'USD' must not become two rows. */
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    /**
     * The published list is cached for five minutes, so a currency edited
     * anywhere other than the currencies controller — a seeder, a console
     * command, a fixture — would otherwise stay invisible to the storefront
     * for as long as the cache held.
     */
    protected static function booted(): void
    {
        $flush = fn () => app(\App\Services\CurrencyService::class)->flushCache();

        static::saved($flush);
        static::deleted($flush);
    }

    public function rates()
    {
        return $this->hasMany(CurrencyRate::class)->orderByDesc('effective_at');
    }

    /**
     * The rate in force at a moment — the newest one that had already started,
     * among those quoted against the base currently in use.
     *
     * The base filter is what keeps a quote from changing meaning when the base
     * moves: "13,000" per pound is not 13,000 per dollar, and reading the old
     * row after the switch mispriced everything by the rate between the two.
     * Rows quoted against a retired base stay in the history, unread.
     */
    public function rateAt(?\DateTimeInterface $moment = null, ?string $baseCode = null): ?CurrencyRate
    {
        $baseCode ??= static::query()->where('is_base', true)->value('code');

        return $this->rates()
            ->where('effective_at', '<=', $moment ?? now())
            ->when(
                $baseCode !== null,
                // Rows written before the base was recorded belong to the only
                // base this platform had, so they are still readable.
                fn ($query) => $query->where(function ($scoped) use ($baseCode) {
                    $scoped->where('base_code', $baseCode)->orWhereNull('base_code');
                })
            )
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return trim((string) $this->name_en) ?: $this->name_ar;
        }

        return trim((string) $this->name_ar) ?: (string) $this->name_en;
    }
}
