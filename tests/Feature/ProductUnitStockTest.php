<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * A line sold in a unit — a box of 12, a kilo of 1000 g — moves that many
 * base units of stock. Units used to change only the price: selling two
 * boxes took two pieces off the shelf.
 */
beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->workflow = app(SalesOrderWorkflowService::class);

    $this->warehouse = Warehouse::create([
        'name' => 'مستودع الوحدات',
        'code' => 'WH-UNITS',
        'is_active' => true,
        'is_primary' => true,
    ]);

    $this->customer = Customer::create(['name' => 'عميل الوحدات', 'status' => 'active']);

    $this->product = Product::factory()->create(['price' => 10, 'cost_price' => 4, 'unit' => 'قطعة']);
    $this->box = ProductUnit::create([
        'product_id' => $this->product->id,
        'name' => 'Box',
        'name_ar' => 'علبة',
        'base_unit_multiplier' => 12,
        'price_multiplier' => 12,
    ]);

    app(InventoryService::class)->receive($this->product->id, 100, $this->warehouse->id, [
        'key' => 'units-opening', 'unit_cost' => 4,
    ]);

    $this->onHand = fn () => (int) WarehouseInventory::where('product_id', $this->product->id)->sum('quantity');
    $this->held = fn () => (int) WarehouseInventory::where('product_id', $this->product->id)->sum('reserved_quantity');
});

it('reserves, ships and returns a sales order line in base units', function () {
    $this->actingAs($this->user);

    $order = SalesOrder::create([
        'order_number' => 'SO-UNITS-1',
        'customer_id' => $this->customer->id,
        'status' => SalesOrder::STATUS_PENDING,
        'subtotal' => 240,
        'total' => 240,
        'currency' => 'SYP',
        'fulfillment_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
    ]);
    $order->items()->create([
        'product_id' => $this->product->id,
        'product_unit_id' => $this->box->id,
        'unit_name' => 'علبة',
        'unit_multiplier' => 12,
        'quantity' => 2,
        'unit_price' => 120,
    ]);

    $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED);
    expect(($this->held)())->toBe(24);

    // The invoice sells in the same unit as the order.
    $line = InvoiceItem::first();
    expect((int) $line->product_unit_id)->toBe($this->box->id)
        ->and($line->stockFactor())->toBe(12);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    $item = $order->refresh()->items->first();
    expect(($this->onHand)())->toBe(76)
        ->and(($this->held)())->toBe(0)
        // Per box, like the price: 12 pieces at 4.
        ->and((float) $item->unit_cost)->toBe(48.0)
        ->and((float) $item->total_cost)->toBe(96.0);

    $this->workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CANCELLED, ['note' => 'اختبار']);
    expect(($this->onHand)())->toBe(100);
});

it('refuses to confirm an order whose boxes the shelf cannot fill', function () {
    $this->actingAs($this->user);

    $order = SalesOrder::create([
        'order_number' => 'SO-UNITS-2',
        'customer_id' => $this->customer->id,
        'status' => SalesOrder::STATUS_PENDING,
        'subtotal' => 1080,
        'total' => 1080,
        'currency' => 'SYP',
        'fulfillment_warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
    ]);
    // Nine boxes are 108 pieces; there are 100.
    $order->items()->create([
        'product_id' => $this->product->id,
        'product_unit_id' => $this->box->id,
        'unit_multiplier' => 12,
        'quantity' => 9,
        'unit_price' => 120,
    ]);

    expect(fn () => $this->workflow->transitionTo($order, SalesOrder::STATUS_CONFIRMED))
        ->toThrow(RuntimeException::class);
    expect(($this->held)())->toBe(0);
});

it('issues and gives back a direct invoice line in base units', function () {
    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [[
            'product_id' => $this->product->id,
            'product_unit_id' => $this->box->id,
            'quantity' => 3,
            'unit_price' => 120,
            'warehouse_id' => $this->warehouse->id,
        ]],
    ])->assertCreated();

    $line = InvoiceItem::first();
    expect(($this->onHand)())->toBe(64)
        ->and((float) $line->unit_multiplier)->toBe(12.0)
        ->and((float) $line->unit_cost)->toBe(48.0);

    $this->actingAs($this->user)
        ->putJson("/api/v1/invoices/{$line->invoice_id}", ['status' => 'cancelled'])
        ->assertOk();

    expect(($this->onHand)())->toBe(100);
});

it('refuses an invoice for more boxes than the shelf holds', function () {
    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [[
            'product_id' => $this->product->id,
            'product_unit_id' => $this->box->id,
            'quantity' => 9,
            'unit_price' => 120,
            'warehouse_id' => $this->warehouse->id,
        ]],
    ])->assertUnprocessable();

    expect(($this->onHand)())->toBe(100);
});

it('ignores a unit that belongs to another product', function () {
    $other = Product::factory()->create();
    $foreign = ProductUnit::create([
        'product_id' => $other->id,
        'name' => 'Pallet',
        'base_unit_multiplier' => 50,
        'price_multiplier' => 50,
    ]);

    $this->actingAs($this->user)->postJson('/api/v1/invoices', [
        'items' => [[
            'product_id' => $this->product->id,
            'product_unit_id' => $foreign->id,
            'quantity' => 1,
            'unit_price' => 10,
            'warehouse_id' => $this->warehouse->id,
        ]],
    ])->assertCreated();

    expect(($this->onHand)())->toBe(99)
        ->and(InvoiceItem::first()->product_unit_id)->toBeNull();
});

it('suggests sourcing in the unit the line is sold in', function () {
    $this->actingAs($this->user)->postJson('/api/v1/sales-orders/suggest-routing', [
        'items' => [['product_id' => $this->product->id, 'product_unit_id' => $this->box->id, 'quantity' => 9]],
    ])->assertOk()->assertJsonPath('data.lines.0.shortfall', 1)
        ->assertJsonPath('data.lines.0.allocations.0.quantity', 8);
});

it('accepts only whole base units as a unit factor', function () {
    $this->actingAs($this->user)
        ->postJson("/api/v1/admin/products/{$this->product->id}/units", [
            'name' => 'Half', 'base_unit_multiplier' => 0.5, 'price_multiplier' => 0.5,
        ])->assertUnprocessable()->assertJsonValidationErrors('base_unit_multiplier');

    $this->actingAs($this->user)
        ->postJson("/api/v1/admin/products/{$this->product->id}/units", [
            'name' => 'Sample', 'base_unit_multiplier' => 1, 'price_multiplier' => 0.0025,
        ])->assertCreated();
});

describe('weight units', function () {
    beforeEach(function () {
        // Counted in kilos, as products sold by weight were.
        $this->hose = Product::factory()->create([
            'unit' => 'كغ', 'price' => 3.6, 'cost_price' => 2, 'stock_quantity' => 0,
        ]);

        app(InventoryService::class)->receive($this->hose->id, 5, $this->warehouse->id, [
            'key' => 'hose-opening', 'unit_cost' => 2,
        ]);

        // Sold two kilos before it had any unit.
        $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'items' => [['product_id' => $this->hose->id, 'quantity' => 2, 'unit_price' => 3.6, 'warehouse_id' => $this->warehouse->id]],
        ])->assertCreated();

        $this->generate = fn () => $this->actingAs($this->user)
            ->postJson("/api/v1/admin/products/{$this->hose->id}/units/generate-weight");
    });

    it('moves a kilo product onto grams and gives it a gram and a kilo unit', function () {
        ($this->generate)()->assertOk()
            ->assertJsonPath('data.converted', true)
            ->assertJsonPath('data.price_rounded', false);

        $hose = $this->hose->refresh();
        expect($hose->unit)->toBe('غرام')
            ->and((float) $hose->price)->toBe(0.0036)
            ->and((float) $hose->cost_price)->toBe(0.002)
            ->and((int) $hose->stock_quantity)->toBe(3000)
            ->and((int) WarehouseInventory::where('product_id', $hose->id)->sum('quantity'))->toBe(3000);

        // The trail and the shelf still agree, and the value is unchanged.
        $in = (int) StockMovement::where('product_id', $hose->id)->where('movement_type', 'in')->sum('quantity');
        $out = (int) StockMovement::where('product_id', $hose->id)->where('movement_type', 'out')->sum('quantity');
        expect($in - $out)->toBe(3000)
            ->and(round((float) StockMovement::where('product_id', $hose->id)->where('movement_type', 'in')->value('unit_cost'), 5))->toBe(0.002);

        $gram = $hose->units()->where('name', 'g')->first();
        $kilo = $hose->units()->where('name', 'kg')->first();
        expect($gram->base_unit_multiplier)->toBe(1)
            ->and((float) $gram->price_multiplier)->toBe(1.0)
            ->and($kilo->base_unit_multiplier)->toBe(1000)
            ->and((float) $kilo->price_multiplier)->toBe(1000.0)
            ->and($kilo->is_default)->toBeTrue()
            ->and($gram->is_default)->toBeFalse();

        // The old sale sold kilos, and now says so: cancelling it puts 2000 g back.
        $line = InvoiceItem::where('product_id', $hose->id)->first();
        expect((int) $line->product_unit_id)->toBe($kilo->id)
            ->and($line->stockFactor())->toBe(1000)
            ->and((float) $line->unit_price)->toBe(3.6);

        $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$line->invoice_id}", ['status' => 'cancelled'])
            ->assertOk();
        expect((int) $hose->refresh()->stock_quantity)->toBe(5000);
    });

    it('sells grams and kilos off the same gram shelf', function () {
        ($this->generate)()->assertOk();
        $gram = $this->hose->units()->where('name', 'g')->first();
        $kilo = $this->hose->units()->where('name', 'kg')->first();

        $this->actingAs($this->user)->postJson('/api/v1/invoices', [
            'items' => [
                ['product_id' => $this->hose->id, 'product_unit_id' => $kilo->id, 'quantity' => 1, 'unit_price' => 3.6, 'warehouse_id' => $this->warehouse->id],
                ['product_id' => $this->hose->id, 'product_unit_id' => $gram->id, 'quantity' => 250, 'unit_price' => 0.0036, 'warehouse_id' => $this->warehouse->id],
            ],
        ])->assertCreated();

        expect((int) $this->hose->refresh()->stock_quantity)->toBe(3000 - 1000 - 250);
    });

    it('is safe to run twice', function () {
        ($this->generate)()->assertOk();
        ($this->generate)()->assertOk()->assertJsonPath('data.converted', false);

        $hose = $this->hose->refresh();
        expect((int) $hose->stock_quantity)->toBe(3000)
            ->and($hose->units()->count())->toBe(2);
    });

    it('reuses a broken gram unit rather than adding a second one', function () {
        // As one was saved on the live site: 1 غرام = 0 كغ.
        $broken = ProductUnit::create([
            'product_id' => $this->hose->id, 'name' => 'غ', 'name_ar' => 'غرام',
            'base_unit_multiplier' => 0, 'price_multiplier' => 0.01, 'is_default' => true,
        ]);

        ($this->generate)()->assertOk();

        expect($this->hose->units()->count())->toBe(2)
            ->and($broken->refresh()->base_unit_multiplier)->toBe(1)
            ->and($broken->is_default)->toBeFalse();
    });

    it('refuses a product that is not counted by weight', function () {
        $this->actingAs($this->user)
            ->postJson("/api/v1/admin/products/{$this->product->id}/units/generate-weight")
            ->assertUnprocessable();

        expect($this->product->units()->count())->toBe(1);
    });
});
