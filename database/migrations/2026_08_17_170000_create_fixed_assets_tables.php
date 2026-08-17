<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Things the business bought to keep, not to sell.
 *
 * A van, a shelving system, a computer: paid for once and used for years. With
 * no register for them there were only two places to put such a purchase, and
 * both are wrong. Booked as an expense, the month of purchase carries the whole
 * cost and every month after it carries none — the business looks to have had
 * a terrible month and then unusually good ones. Booked as inventory, it sits
 * among the goods for sale and will eventually be costed out through a sale
 * that never happens.
 *
 * The register makes the third answer possible: the cost becomes an asset and
 * is charged to the periods that actually use it, a slice at a time.
 *
 * Four accounts carry it, and the pairing matters:
 *
 *   1100  Fixed assets                what was paid, and stays at what was paid
 *   1101  Accumulated depreciation    what has been used up so far (contra)
 *   5008  Depreciation expense        this period's slice
 *   5009  Loss on disposal            what was left when an asset went
 *
 * Accumulated depreciation is kept apart from the asset rather than deducted
 * from it, so the books keep saying both what a thing cost and how much of it
 * is gone. Net book value is the difference, and an asset fully depreciated but
 * still in use stays visible at cost instead of disappearing.
 */
return new class extends Migration
{
    /** code => [name, type, parent, posting role] */
    private const ACCOUNTS = [
        '1100' => ['الأصول الثابتة', 'asset', '1000', 'fixed_assets'],
        '1101' => ['مجمع إهلاك الأصول الثابتة', 'asset', '1000', 'accumulated_depreciation'],
        '5008' => ['مصروف الإهلاك', 'expense', '5000', 'depreciation_expense'],
        '5009' => ['خسائر استبعاد أصول', 'expense', '5000', 'asset_disposal_loss'],
    ];

    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number')->unique();
            $table->string('name');
            $table->string('category', 100)->nullable();

            $table->date('acquired_on');
            $table->decimal('cost', 15, 2);
            // What it is expected to be worth at the end of its life; only the
            // difference between cost and this is ever depreciated.
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');

            // Kept on the record rather than recomputed from the entries: the
            // register has to be readable without replaying the journal.
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->date('depreciated_through')->nullable();

            $table->string('status', 20)->default('active');
            $table->date('disposed_on')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->nullable();

            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The question the depreciation run asks every month.
            $table->index(['status', 'depreciated_through']);
        });

        $now = now();

        foreach (self::ACCOUNTS as $code => [$name, $type, $parentCode, $role]) {
            $existingId = DB::table('ledger_accounts')->where('code', $code)->value('id');
            $roleTaken = DB::table('ledger_accounts')->where('posting_role', $role)->exists();

            if ($existingId) {
                // Only an account with no role of its own may be claimed —
                // overwriting one silently redirects every posting that
                // resolved through it, which has happened here before.
                if (! $roleTaken) {
                    DB::table('ledger_accounts')->where('id', $existingId)->whereNull('posting_role')->update([
                        'posting_role' => $role,
                        'is_system' => 1,
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            if ($roleTaken) {
                continue;
            }

            DB::table('ledger_accounts')->insert([
                'code' => $code,
                'parent_id' => DB::table('ledger_accounts')->where('code', $parentCode)->value('id'),
                'name' => $name,
                'type' => $type,
                'account_type' => $type,
                'posting_role' => $role,
                'currency' => DB::table('currencies')->where('is_base', 1)->value('code') ?: 'USD',
                'balance' => 0,
                'opening_balance' => 0,
                'is_active' => 1,
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');

        $ids = DB::table('ledger_accounts')->whereIn('code', array_keys(self::ACCOUNTS))->pluck('id');
        $used = DB::table('journal_entry_lines')->whereIn('account_id', $ids)->pluck('account_id')->unique();

        DB::table('ledger_accounts')->whereIn('id', $ids->diff($used))->delete();
    }
};
