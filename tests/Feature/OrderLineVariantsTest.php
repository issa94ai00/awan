<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesOrderWorkflowService;

/**
 * Sales orders, purchase requests and goods receipts can name one variant of
 * a product on a line — the 4" floor drain, not "floor drain".
 *
 * Warehouse stock, reservations and cost stay per product. The line keeps the
 * variant, is named for it, and the variant's own stock count follows the
 * goods in and out.
 */
beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي', 'code' => 'WH-VAR', 'status' => 'active',
        'is_active' => true, 'is_primary' => true, 'location_type' => Warehouse::TYPE_WAREHOUSE,
    ]);

    $this->drain = Product::create(['name_ar' => 'جريدة تصريف', 'sku' => 'FD', 'price' => 5, 'cost_price' => 2]);
    $this->four = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-4', 'size' => '4"', 'price' => 0.95, 'cost_price' => 0.5, 'stock_quantity' => 10]);
    $this->five = ProductVariant::create(['product_id' => $this->drain->id, 'sku' => 'FD-5', 'size' => '5"', 'price' => 1.7, 'cost_price' => 0.9, 'stock_quantity' => 10]);

    $this->tap = Product::create(['name_ar' => 'حنفية', 'sku' => 'TAP', 'price' => 20]);

    $this->customer = Customer::create(['name' => 'عميل', 'phone' => '0999111222', 'status' => 'active']);
    $this->supplier = Supplier::create(['name' => 'مورّد', 'status' => 'active', 'balance' => 0]);
});

test('a sales order line keeps its variant and is named for it', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'fulfillment_warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->four->id, 'quantity' => 2, 'unit_price' => 0.95],
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->five->id, 'quantity' => 1, 'unit_price' => 1.7],
            ['product_id' => $this->tap->id, 'quantity' => 1, 'unit_price' => 20],
        ],
    ])->assertCreated();

    $items = collect($response->json('data.items'));
    $four = $items->firstWhere('product_variant_id', $this->four->id);

    expect($four['description'])->toBe('جريدة تصريف - 4"')
        ->and($four['variant']['sku'])->toBe('FD-4')
        ->and($items->firstWhere('product_id', $this->tap->id)['product_variant_id'])->toBeNull();
});

test('a sales order refuses a variant of another product', function () {
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/sales-orders', [
        'customer_id' => $this->customer->id,
        'items' => [
            ['product_id' => $this->tap->id, 'product_variant_id' => $this->four->id, 'quantity' => 1, 'unit_price' => 1],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_variant_id');
});

test('shipping two variants of one product issues both and lowers each variant count', function () {
    app(InventoryService::class)->receive($this->drain->id, 50, $this->warehouse->id, ['key' => 'seed', 'unit_cost' => 1]);

    $order = SalesOrder::create([
        'order_number' => 'SO-VAR-1', 'customer_id' => $this->customer->id,
        'status' => SalesOrder::STATUS_PENDING, 'order_date' => now()->toDateString(),
        'subtotal' => 0, 'total' => 0, 'currency' => 'SYP',
        'fulfillment_warehouse_id' => $this->warehouse->id, 'created_by' => $this->admin->id,
    ]);
    $order->items()->create(['product_id' => $this->drain->id, 'product_variant_id' => $this->four->id, 'description' => 'جريدة تصريف - 4"', 'quantity' => 3, 'unit_price' => 0.95]);
    $order->items()->create(['product_id' => $this->drain->id, 'product_variant_id' => $this->five->id, 'description' => 'جريدة تصريف - 5"', 'quantity' => 2, 'unit_price' => 1.7]);

    $this->actingAs($this->admin);
    $workflow = app(SalesOrderWorkflowService::class);
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CONFIRMED);
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_PROCESSING);
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_SHIPPED);

    // Both lines left the shelf: the product's stock fell by 5, not by 3.
    expect((int) $this->drain->refresh()->stock_quantity)->toBe(45);
    expect(StockMovement::where('movement_type', 'out')->where('product_id', $this->drain->id)->sum('quantity'))->toBe(5);

    expect($this->four->refresh()->stock_quantity)->toBe(7)
        ->and($this->five->refresh()->stock_quantity)->toBe(8);

    // The invoice says which size was sold.
    $invoiceItems = $order->refresh()->invoices()->first()->items;
    expect($invoiceItems->pluck('product_name')->sort()->values()->all())
        ->toBe(['جريدة تصريف - 4"', 'جريدة تصريف - 5"'])
        ->and($invoiceItems->pluck('product_variant_id')->filter()->count())->toBe(2);

    // Cancelling after shipment puts the counts back.
    $workflow->transitionTo($order->refresh(), SalesOrder::STATUS_CANCELLED, ['note' => 'إرجاع']);
    expect($this->four->refresh()->stock_quantity)->toBe(10)
        ->and($this->five->refresh()->stock_quantity)->toBe(10);
});

test('an order from the mobile app is priced at the variant', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/purchase-requests', [
        'name' => 'عميل', 'phone' => '0999111222',
        'items' => [
            ['product_id' => $this->drain->id, 'variant_id' => $this->five->id, 'quantity' => 2,
                'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 2]]],
        ],
    ])->assertSuccessful();

    $line = $response->json('data.items.0');
    expect($line['variant_id'])->toBe($this->five->id)
        ->and($line['product_name'])->toBe('جريدة تصريف - 5"')
        ->and((float) $line['unit_price'])->toBe(1.7);
});

test('a purchase request line keeps its variant and a receipt fills it', function () {
    $order = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/purchase-orders', [
        'supplier_id' => $this->supplier->id,
        'items' => [
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->four->id, 'quantity' => 10, 'unit_price' => 0.6],
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->five->id, 'quantity' => 4, 'unit_price' => 1.1, 'sale_price' => 1.95],
        ],
    ])->assertSuccessful()->json('data');

    expect(collect($order['items'])->pluck('product_name')->all())
        ->toBe(['جريدة تصريف - 4"', 'جريدة تصريف - 5"']);

    // The receipt form prefills from the request, variant by variant.
    $details = $this->getJson('/api/v1/purchase-receipts/purchase-order/'.$order['id'])->assertOk()->json('data.items');
    expect($details[0]['product_variant_id'])->toBe($this->four->id)
        ->and($details[0]['variant_label'])->toBe('4"');

    // Two sizes of one product on one receipt are two lines, not a duplicate.
    $this->postJson('/api/v1/purchase-receipts', [
        'purchase_order_id' => $order['id'],
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->four->id, 'quantity' => 6, 'unit_price' => 0.6],
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->five->id, 'quantity' => 4, 'unit_price' => 1.1, 'sale_price' => 1.95],
        ],
    ])->assertSuccessful();

    // Warehouse stock is per product: all 10 arrived.
    expect((int) $this->drain->refresh()->stock_quantity)->toBe(10);
    // The product's own price is untouched; the 5" took the shelf price.
    expect((float) $this->drain->price)->toBe(5.0)
        ->and((float) $this->five->refresh()->price)->toBe(1.95);

    // Each variant's count and cost moved with its own goods.
    expect($this->four->refresh()->stock_quantity)->toBe(16)
        ->and((float) $this->four->cost_price)->toBe(round((10 * 0.5 + 6 * 0.6) / 16, 5))
        ->and($this->five->stock_quantity)->toBe(14);

    // Received quantities land on the right order line, not split by product.
    $lines = PurchaseOrder::find($order['id'])->items->keyBy('product_variant_id');
    expect($lines[$this->four->id]->received_quantity)->toBe(6)
        ->and($lines[$this->five->id]->received_quantity)->toBe(4)
        ->and((float) $lines[$this->five->id]->received_unit_cost)->toBe(1.1);
});

test('the same variant twice on one receipt is still refused', function () {
    $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/purchase-receipts', [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->four->id, 'quantity' => 1, 'unit_price' => 1],
            ['product_id' => $this->drain->id, 'product_variant_id' => $this->four->id, 'quantity' => 1, 'unit_price' => 1],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.1.product_id');
});
