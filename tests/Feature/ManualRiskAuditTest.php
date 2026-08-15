<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\ManualRiskAuditService;

it('detects manual-risk anomalies across orders, stock and finance data', function () {
    $warehouse = Warehouse::create([
        'name' => 'Audit Risk Warehouse',
        'code' => 'ARW-01',
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);

    $customer = Customer::create([
        'name' => 'Risk Audit Customer',
        'email' => 'risk.audit@example.com',
        'phone' => '0999111122',
        'status' => 'active',
    ]);

    $product = Product::create([
        'name_ar' => 'منتج مراقبة',
        'name_en' => 'Audit Product',
        'sku' => 'AUDIT-SKU-01',
        'price' => 50,
        'cost_price' => 30,
        'stock_quantity' => 5,
        'is_active' => true,
    ]);

    $order = SalesOrder::create([
        'order_number' => 'SO-AUDIT-001',
        'customer_id' => $customer->id,
        'status' => SalesOrder::STATUS_SHIPPED,
        'order_date' => now()->toDateString(),
        'subtotal' => 100,
        'discount' => 0,
        'tax' => 0,
        'total' => 100,
        'currency' => 'SYP',
        'fulfillment_warehouse_id' => $warehouse->id,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'discount' => 0,
        'tax' => 0,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-AUDIT-001',
        'customer_id' => $customer->id,
        'sales_order_id' => $order->id,
        'warehouse_id' => $warehouse->id,
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'paid_amount' => 0,
        'due_amount' => 100,
        'status' => Invoice::STATUS_SHIPPED,
        'currency' => 'SYP',
    ]);

    Payment::create([
        'payment_number' => 'PAY-AUDIT-001',
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'amount' => 50,
        'payment_date' => now()->toDateString(),
        'currency' => 'SYP',
        'sales_order_id' => $order->id,
    ]);

    $result = app(ManualRiskAuditService::class)->scan();

    expect($result['issues'])->not->toBeEmpty();
    expect($result['summary']['total_issues'])->toBeGreaterThan(0);

    $user = \App\Models\User::factory()->create([
        'is_admin' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/audit/risk-scan');
    $response->assertStatus(200)
        ->assertJsonPath('summary.total_issues', $result['summary']['total_issues']);

    $exportResponse = $this->actingAs($user, 'sanctum')->get('/api/v1/audit/risk-scan/export');
    $exportResponse->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $this->assertStringContainsString('type,severity,reference,message', $exportResponse->baseResponse->getContent());

    $reconciliationResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/audit/reconciliation');
    $reconciliationResponse->assertStatus(200)
        ->assertJsonPath('summary.total_issues', $result['summary']['total_issues'])
        ->assertJsonPath('summary.has_issues', true);
});
