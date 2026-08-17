<?php

use App\Models\FixedAsset;
use App\Models\JournalEntryHeader;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;

/**
 * Things bought to keep, and the cost of using them up.
 *
 * With no register, such a purchase had two possible homes and both lie. As an
 * expense, the month of purchase carries the whole cost and every month after
 * it carries none — a terrible month followed by unusually good ones, for a van
 * that will serve for years. As inventory, it waits among the goods for sale
 * to be costed out through a sale that never comes.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->supplier = Supplier::create(['name' => 'معرض السيارات', 'status' => 'active', 'balance' => 0]);

    $this->balanceOf = fn (string $role) => round(
        (float) LedgerAccount::where('posting_role', $role)->value('balance'),
        2
    );

    $this->buy = fn (array $overrides = []) => $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/fixed-assets', array_merge([
            'name' => 'سيارة توصيل',
            'category' => 'مركبات',
            'acquired_on' => '2026-01-15',
            'cost' => 12000,
            'salvage_value' => 0,
            'useful_life_months' => 60,
            'settlement' => 'credit',
            'supplier_id' => $this->supplier->id,
        ], $overrides));
});

test('buying an asset makes it an asset, not a cost of that month', function () {
    ($this->buy)()->assertCreated();

    expect(($this->balanceOf)('fixed_assets'))->toBe(12000.0);
    expect(($this->balanceOf)('accounts_payable'))->toBe(12000.0);
    expect(round((float) $this->supplier->fresh()->balance, 2))->toBe(12000.0);
    // Nothing has been used up yet, so no expense has been incurred.
    expect(($this->balanceOf)('depreciation_expense'))->toBe(0.0);
});

test('a month of depreciation charges one slice and no more', function () {
    ($this->buy)()->assertCreated();

    $this->artisan('accounting:depreciate --month=2026-02')->assertSuccessful();

    // 12,000 over 60 months.
    expect(($this->balanceOf)('depreciation_expense'))->toBe(200.0);
    expect(($this->balanceOf)('accumulated_depreciation'))->toBe(-200.0);

    $asset = FixedAsset::first();
    expect($asset->netBookValue())->toBe(11800.0);
    // The asset itself stays at what it cost.
    expect(($this->balanceOf)('fixed_assets'))->toBe(12000.0);
});

test('running the same month twice does not charge it twice', function () {
    ($this->buy)()->assertCreated();

    $this->artisan('accounting:depreciate --month=2026-02')->assertSuccessful();
    $this->artisan('accounting:depreciate --month=2026-02')->assertSuccessful();

    // Depreciation has no document behind it to notice a duplicate, and a month
    // charged twice quietly halves the profit.
    expect(($this->balanceOf)('depreciation_expense'))->toBe(200.0);
    expect(round((float) FixedAsset::first()->accumulated_depreciation, 2))->toBe(200.0);
});

test('the charge is dated to the month it belongs to', function () {
    ($this->buy)()->assertCreated();

    $this->artisan('accounting:depreciate --month=2026-03')->assertSuccessful();

    $entry = JournalEntryHeader::where('source_module', 'assets')
        ->where('description', 'like', 'إهلاك 2026-03%')->first();

    // Not the day the command happened to be run: a month closed later must
    // carry its own charge.
    expect($entry->entry_date->toDateString())->toBe('2026-03-31');
});

test('nothing is charged for a month before the asset arrived', function () {
    ($this->buy)()->assertCreated();

    $this->artisan('accounting:depreciate --month=2025-12')->assertSuccessful();

    expect(($this->balanceOf)('depreciation_expense'))->toBe(0.0);
});

test('an asset never depreciates below what it is expected to be worth', function () {
    ($this->buy)([
        'cost' => 1000,
        'salvage_value' => 400,
        'useful_life_months' => 3,
        'acquired_on' => '2026-01-01',
    ])->assertCreated();

    foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05'] as $month) {
        $this->artisan('accounting:depreciate --month='.$month)->assertSuccessful();
    }

    // Only the 600 between cost and salvage is ever charged, however many
    // times the run happens afterwards.
    expect(($this->balanceOf)('depreciation_expense'))->toBe(600.0);
    expect(FixedAsset::first()->netBookValue())->toBe(400.0);
});

test('a future month is refused — it has not been used up yet', function () {
    ($this->buy)()->assertCreated();

    $this->artisan('accounting:depreciate --month='.now()->addMonth()->format('Y-m'))
        ->assertSuccessful();

    expect(($this->balanceOf)('depreciation_expense'))->toBe(0.0);
});

test('disposing of an asset clears its cost and its depreciation together', function () {
    ($this->buy)()->assertCreated();
    $this->artisan('accounting:depreciate --month=2026-02')->assertSuccessful();

    // Carried at 11,800 and sold for 11,000: an 800 loss.
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/fixed-assets/'.FixedAsset::first()->id.'/dispose', [
            'disposed_on' => '2026-03-10',
            'proceeds' => 11000,
            'settlement' => 'cash',
        ])->assertOk();

    // Leaving one behind would show a business owning depreciation on an asset
    // it no longer has.
    expect(($this->balanceOf)('fixed_assets'))->toBe(0.0);
    expect(($this->balanceOf)('accumulated_depreciation'))->toBe(0.0);
    expect(($this->balanceOf)('asset_disposal_loss'))->toBe(800.0);
    expect(($this->balanceOf)('cash'))->toBe(11000.0);
});

test('selling for more than the carrying value is a gain', function () {
    ($this->buy)(['cost' => 5000, 'useful_life_months' => 10, 'acquired_on' => '2026-01-01'])
        ->assertCreated();

    $this->artisan('accounting:depreciate --month=2026-01')->assertSuccessful();

    // Carried at 4,500 and sold for 5,200.
    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/fixed-assets/'.FixedAsset::first()->id.'/dispose', [
            'proceeds' => 5200,
        ])->assertOk();

    expect(($this->balanceOf)('asset_disposal_loss'))->toBe(-700.0);
});

test('a disposed asset stops depreciating', function () {
    ($this->buy)()->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/accounting/fixed-assets/'.FixedAsset::first()->id.'/dispose', [
            'disposed_on' => '2026-02-01',
            'proceeds' => 12000,
        ])->assertOk();

    $this->artisan('accounting:depreciate --month=2026-03')->assertSuccessful();

    expect(($this->balanceOf)('depreciation_expense'))->toBe(0.0);
});

test('an asset on the books cannot be deleted', function () {
    ($this->buy)()->assertCreated();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/accounting/fixed-assets/'.FixedAsset::first()->id)
        ->assertStatus(422);

    expect(FixedAsset::count())->toBe(1);
});

test('a salvage value at or above cost is refused', function () {
    ($this->buy)(['cost' => 1000, 'salvage_value' => 1000])->assertStatus(422);

    expect(FixedAsset::count())->toBe(0);
});

test('the register is refused to a sales account', function () {
    $sales = User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'sells'], ['display_name' => 'sells'])->id,
    ]);

    $this->actingAs($sales)
        ->getJson('/api/v1/admin/accounting/fixed-assets')
        ->assertForbidden();
});
