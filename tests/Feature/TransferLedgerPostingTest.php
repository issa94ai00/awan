<?php

use App\Models\InventoryTransfer;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Inventory\InventoryService;

/**
 * A transfer moves value between warehouse inventory accounts through goods
 * in transit. The posting methods existed but nothing called them, so stock
 * changed warehouse while the ledger did not.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->source = Warehouse::create(['name' => 'المصدر', 'code' => 'WH-TL-1', 'is_active' => true, 'is_primary' => true]);
    $this->branch = Warehouse::create(['name' => 'الفرع', 'code' => 'WH-TL-2', 'is_active' => true]);
    $this->product = Product::factory()->create(['cost_price' => 7]);

    app(InventoryService::class)->receive($this->product->id, 50, $this->source->id, ['key' => 'tl-open', 'unit_cost' => 7]);

    $ledger = app(LedgerPostingService::class);
    $this->net = function (int $accountId) {
        $sum = JournalEntryLine::where('account_id', $accountId)->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) AS n')->value('n');

        return round((float) $sum, 2);
    };
    $this->inTransit = fn () => LedgerAccount::where('posting_role', 'inventory_in_transit')->value('id');
    $this->accountOf = fn (Warehouse $w) => $ledger->inventoryAccountIdFor($w->id);

    $this->raise = function (int $qty) {
        $this->actingAs($this->user)->postJson('/api/v1/inventory/transfers', [
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->branch->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => $qty]],
        ])->assertCreated();

        return InventoryTransfer::latest('id')->first();
    };
});

it('posts the shipment and the receipt, netting goods in transit to zero', function () {
    $sourceBefore = ($this->net)(($this->accountOf)($this->source));
    $transfer = ($this->raise)(10);

    $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/ship")->assertOk();
    expect(($this->net)(($this->inTransit)()))->toEqual(70.0)
        ->and(($this->net)(($this->accountOf)($this->source)) - $sourceBefore)->toEqual(-70.0);

    $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive")->assertOk();
    expect(($this->net)(($this->inTransit)()))->toEqual(0.0)
        ->and(($this->net)(($this->accountOf)($this->branch)))->toEqual(70.0);
});

it('leaves a short delivery in transit and never posts twice', function () {
    $transfer = ($this->raise)(10);
    $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/ship")->assertOk();

    $item = $transfer->items()->first();
    $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
        'items' => [['transfer_item_id' => $item->id, 'quantity_received' => 8]],
    ])->assertOk();

    expect(($this->net)(($this->inTransit)()))->toEqual(14.0)
        ->and(JournalEntryHeader::where('posting_key', 'transfer_ship:'.$transfer->id)->count())->toBe(1)
        ->and(JournalEntryHeader::where('posting_key', 'transfer_receive:'.$transfer->id)->count())->toBe(1);
});
