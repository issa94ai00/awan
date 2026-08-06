<?php

use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\RmaRequest;
use App\Models\WarehouseBin;
use App\Services\ErpUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;
    $this->service = new ErpUpgradeService();
});

test('it can allocate landed cost by value', function () {
    $supplier = Supplier::create([
        'name' => 'Supplier Test',
        'email' => 'supplier@test.com',
        'phone' => '1234567890',
    ]);

    $productA = Product::create([
        'name_ar' => 'منتج أ',
        'name_en' => 'Product A',
        'price' => 100,
        'stock_quantity' => 10,
    ]);

    $productB = Product::create([
        'name_ar' => 'منتج ب',
        'name_en' => 'Product B',
        'price' => 200,
        'stock_quantity' => 5,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'REC-0001',
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
    ]);

    $itemA = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'product_id' => $productA->id,
        'description' => 'Item A',
        'quantity' => 10,
        'unit_price' => 10.00,
        'total' => 100.00,
    ]);

    $itemB = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'product_id' => $productB->id,
        'description' => 'Item B',
        'quantity' => 5,
        'unit_price' => 20.00,
        'total' => 100.00,
    ]);

    $landedCost = $this->service->allocateLandedCost(
        $receipt->id,
        40.00,
        30.00,
        20.00,
        10.00,
        'value'
    );

    expect($landedCost)->not->toBeNull();
    expect($landedCost->shipping_charges)->toEqual(40.00);

    $itemA->refresh();
    $itemB->refresh();

    expect($itemA->unit_price)->toEqual(15.00);
    expect($itemB->unit_price)->toEqual(30.00);
});

test('it can reserve and release inventory', function () {
    $warehouse = Warehouse::create([
        'name' => 'Main Warehouse',
        'code' => 'WH-MAIN',
        'address' => 'Test Street',
        'city' => 'Riyadh',
        'country' => 'Saudi Arabia',
        'is_active' => true,
    ]);

    $product = Product::create([
        'name_ar' => 'منتج تجريبي',
        'name_en' => 'Test Product',
        'price' => 50,
        'stock_quantity' => 100,
    ]);

    $inventory = WarehouseInventory::create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 50,
        'reserved_quantity' => 0,
    ]);

    $success = $this->service->reserveInventory($warehouse->id, $product->id, null, 20);
    expect($success)->toBeTrue();

    $inventory->refresh();
    expect($inventory->reserved_quantity)->toEqual(20);
});

test('it can call allocate landed cost api', function () {
    $supplier = Supplier::create([
        'name' => 'Supplier Test',
        'email' => 'supplier@test.com',
        'phone' => '1234567890',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج أ',
        'name_en' => 'Product A',
        'price' => 100,
        'stock_quantity' => 10,
    ]);

    $receipt = PurchaseReceipt::create([
        'receipt_number' => 'REC-0001',
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
    ]);

    $item = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'product_id' => $product->id,
        'description' => 'Item A',
        'quantity' => 10,
        'unit_price' => 10.00,
        'total' => 100.00,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/purchase-receipts/{$receipt->id}/landed-costs", [
            'shipping_charges' => 50.00,
            'customs_duties' => 30.00,
            'insurance_cost' => 10.00,
            'other_charges' => 10.00,
            'allocation_method' => 'value',
        ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $item->refresh();
    expect($item->unit_price)->toEqual(20.00); // 10.00 + (100.00/10)
});

test('it can manage rma requests via api', function () {
    $customer = \App\Models\Customer::create([
        'name' => 'Customer Test',
        'email' => 'customer@test.com',
        'phone' => '0987654321',
    ]);

    $salesOrder = \App\Models\SalesOrder::create([
        'order_number' => 'SO-0001',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'pending',
        'subtotal' => 100,
        'total' => 100,
    ]);

    // Create RMA via API
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/rmas", [
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'reason' => 'Defected product',
            'resolution_type' => 'refund',
        ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $rmaId = $response->json('data.id');

    // Get RMAs
    $responseGet = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/rmas");

    $responseGet->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Update status
    $responsePut = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/rmas/{$rmaId}/status", [
            'status' => 'received',
        ]);

    $responsePut->assertStatus(200)
        ->assertJsonPath('data.status', 'received');
});

test('it can manage warehouse bins via api', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bin Warehouse',
        'code' => 'WH-BIN',
        'is_active' => true,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/warehouse-bins", [
            'warehouse_id' => $warehouse->id,
            'bin_code' => 'ZONE-A-RACK1',
            'zone' => 'A',
            'rack' => '1',
        ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $responseGet = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/warehouse-bins");

    $responseGet->assertStatus(200)
        ->assertJsonCount(1, 'data');
});
