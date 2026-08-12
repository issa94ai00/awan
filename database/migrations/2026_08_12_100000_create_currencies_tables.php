<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A managed list of currencies, and what each one is worth.
 *
 * Currency support was scaffolding without a floor. Nine financial tables carry
 * a `currency` column and three carry `exchange_rate`, but every writer put the
 * literal 'SAR' and the literal 1.0000 into them, the choice of currency was a
 * five-item list hardcoded in two controllers, and nothing anywhere converted
 * anything. The Flutter app printed "ر.س" regardless.
 *
 * These two tables are what was missing:
 *
 *   currencies      which currencies exist, how to print them, which is base
 *   currency_rates  what each was worth against the base, and since when
 *
 * Rates are an append-only log rather than a column on `currencies`. A rate is
 * only meaningful with a date attached — "the dollar is 13000" is a statement
 * about a moment — and keeping the history is what later lets an order be shown
 * at the rate that applied when it was placed rather than at today's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            // ISO 4217 where one exists. Uppercased by the model so 'usd' and
            // 'USD' cannot become two currencies.
            $table->string('code', 8)->unique();

            $table->string('name_ar');
            $table->string('name_en')->nullable();

            // Printed with the amount. Kept separate from the code because
            // "ل.س" and "SYP" are both wanted, in different places.
            $table->string('symbol', 12);

            // Rounding differs by currency, not by taste: 2 for the dollar,
            // 0 for currencies whose smallest note dwarfs a fractional unit.
            $table->unsignedTinyInteger('decimal_places')->default(2);

            // Display rounding step. A price of 12,347 SYP is noise when the
            // smallest note in circulation is 500, so a shop rounds to it.
            $table->unsignedInteger('rounding_step')->default(0);

            // Exactly one row is the base: the currency the books are kept in
            // and the one every rate is quoted against.
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();

            // Units of this currency per one unit of the base currency.
            //
            // Quoted this way round because that is how the rate is spoken in
            // the markets this serves ("the dollar is 13,000") and because it
            // keeps the stored number large and precise rather than a long tail
            // of zeros. 18,8 holds both a 13000.00000000 and a 0.00007692.
            $table->decimal('rate', 18, 8);

            // When this rate started applying. Lets a rate be entered ahead of
            // time, and lets history be corrected without deleting anything.
            $table->timestamp('effective_at')->useCurrent();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();

            $table->timestamps();

            // The lookup this table exists for: the newest rate for a currency
            // at or before a given moment.
            $table->index(['currency_id', 'effective_at']);
        });

        $this->seedBaseCurrency();
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currencies');
    }

    /**
     * Creates the base currency from whatever the platform is already using.
     *
     * Without this the first request after migrating would find no base
     * currency and have nothing to price against. The existing `default_currency`
     * setting is the honest source: it is what every hardcoded 'SAR' was
     * pretending to honour.
     */
    private function seedBaseCurrency(): void
    {
        $code = 'SAR';

        if (Schema::hasTable('settings')) {
            $configured = DB::table('settings')->where('key', 'default_currency')->value('value');
            $code = strtoupper(trim((string) $configured)) ?: 'SAR';
        }

        $known = [
            'SAR' => ['ريال سعودي', 'Saudi Riyal', 'ر.س', 2],
            'USD' => ['دولار أمريكي', 'US Dollar', '$', 2],
            'EUR' => ['يورو', 'Euro', '€', 2],
            'AED' => ['درهم إماراتي', 'UAE Dirham', 'د.إ', 2],
            'EGP' => ['جنيه مصري', 'Egyptian Pound', 'ج.م', 2],
            'SYP' => ['ليرة سورية', 'Syrian Pound', 'ل.س', 0],
        ];

        [$nameAr, $nameEn, $symbol, $decimals] = $known[$code] ?? [$code, $code, $code, 2];

        $currencyId = DB::table('currencies')->insertGetId([
            'code' => $code,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'symbol' => $symbol,
            'decimal_places' => $decimals,
            'rounding_step' => 0,
            'is_base' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The base is worth exactly one of itself. Stored rather than special
        // cased so a lookup for any currency, base included, finds a row.
        DB::table('currency_rates')->insert([
            'currency_id' => $currencyId,
            'rate' => 1,
            'effective_at' => now(),
            'note' => 'Base currency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
