<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps every exchange rate with the base it was quoted against.
 *
 * A rate is "units of this currency per one unit of the base". That sentence
 * has a subject nobody was storing, so the moment the base moved — from the
 * pound to the dollar, say — every historical quote was silently re-read as
 * being per one dollar, and every converted price on the storefront was wrong
 * by the rate between the two bases. Nothing warned; the numbers just changed
 * meaning underneath.
 *
 * With the base recorded, rates from an earlier base stay in the history where
 * they are still true, and a currency simply reads as rateless until someone
 * quotes it against the new base — which is what the admin screen has been
 * telling people to do all along.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currency_rates')) {
            return;
        }

        if (! Schema::hasColumn('currency_rates', 'base_code')) {
            Schema::table('currency_rates', function (Blueprint $table) {
                $table->string('base_code', 8)->nullable()->after('currency_id');
            });

            Schema::table('currency_rates', function (Blueprint $table) {
                $table->index(['currency_id', 'base_code', 'effective_at'], 'currency_rates_currency_base_effective_index');
            });
        }

        // Everything already recorded was quoted against whatever is base now:
        // that is the only base this platform has ever had.
        $baseCode = DB::table('currencies')->where('is_base', true)->value('code');

        if ($baseCode !== null) {
            DB::table('currency_rates')->whereNull('base_code')->update(['base_code' => $baseCode]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('currency_rates') || ! Schema::hasColumn('currency_rates', 'base_code')) {
            return;
        }

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropIndex('currency_rates_currency_base_effective_index');
            $table->dropColumn('base_code');
        });
    }
};
