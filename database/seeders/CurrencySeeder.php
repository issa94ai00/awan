<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;

/**
 * The currencies this platform trades in: the Syrian pound, the Saudi riyal
 * and the US dollar.
 *
 * The pound is the base — the books are kept in it and every rate is quoted
 * against it. The other two are catalogued so a shopper can read prices in
 * them, but they ship **rateless**: what a dollar is worth in pounds is a
 * market fact that changes weekly, and inventing one here would put a made-up
 * number in front of customers. Until an admin records a quote, the clients
 * show base prices, which is the honest answer.
 *
 * Idempotent: matched on the code, which is the currency's identity, so
 * re-seeding refreshes the presentation of an existing row instead of adding
 * a second pound. Rates are never touched — they are an append-only history
 * and a seeder has no business rewriting them.
 */
class CurrencySeeder extends Seeder
{
    public const BASE_CODE = 'SYP';

    /**
     * code => [name_ar, name_en, symbol, decimal_places, rounding_step]
     *
     * The rounding step is the smallest note worth quoting: pound prices are
     * rounded to 500 because a price of 12,347 SYP is false precision, while
     * the riyal and the dollar are quoted to the fils and the cent.
     */
    private const CURRENCIES = [
        'SYP' => ['ليرة سورية', 'Syrian Pound', 'ل.س', 0, 500],
        'SAR' => ['ريال سعودي', 'Saudi Riyal', 'ر.س', 2, 0],
        'USD' => ['دولار أمريكي', 'US Dollar', '$', 2, 0],
    ];

    public function run(): void
    {
        $currencies = app(CurrencyService::class);

        $base = null;
        $sortOrder = 0;

        foreach (self::CURRENCIES as $code => [$nameAr, $nameEn, $symbol, $decimals, $step]) {
            $currency = Currency::firstOrNew(['code' => $code]);

            $currency->fill([
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'symbol' => $symbol,
                'decimal_places' => $decimals,
                'rounding_step' => $step,
                'sort_order' => $sortOrder++,
            ]);

            // A currency an admin deactivated on purpose stays deactivated;
            // only a currency being created here starts active.
            if (! $currency->exists) {
                $currency->is_active = true;
                $currency->is_base = false;
            }

            $currency->save();

            if ($code === self::BASE_CODE) {
                $base = $currency;
            }
        }

        // Only claim the base when nobody else holds it: an admin who moved the
        // platform onto the dollar must not be moved back by a re-seed.
        if ($base !== null && Currency::where('is_base', true)->doesntExist()) {
            $currencies->setBase($base);
        }

        $currencies->flushCache();
    }
}
