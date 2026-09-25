<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api-token')->plainTextToken;
});

test('dashboard stats endpoint returns summary data', function () {
    $product = Product::create([
        'name_ar' => 'منتج تجريبي',
        'name_en' => 'Test Product',
        'price' => 120.50,
        'stock_quantity' => 25,
        'is_active' => true,
        'is_featured' => false,
        'in_stock' => true,
    ]);

    $customer = Customer::create([
        'name' => 'عميل تجريبي',
        'email' => 'customer@example.com',
        'phone' => '+966500000000',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-0001',
        'customer_id' => $customer->id,
        'subtotal' => 120.50,
        'tax' => 0,
        'discount' => 0,
        'total' => 120.50,
        'paid_amount' => 120.50,
        'due_amount' => 0,
        // STATUS_PAID no longer exists on the model — payment is tracked by
        // paid_amount/due_amount, and the stage is what `status` records. A
        // settled, delivered invoice is DELIVERED, which is also what the
        // revenue figure asserted below counts.
        'status' => Invoice::STATUS_DELIVERED,
        'created_by' => $this->user->id,
    ]);

    Payment::create([
        'payment_number' => 'PAY-0001',
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'amount' => 120.50,
        'payment_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/dashboard/stats');

    $response->assertStatus(200)
        ->assertJson([ 'success' => true ])
        ->assertJsonPath('data.products.total', 1)
        ->assertJsonPath('data.invoices.total', 1)
        ->assertJsonPath('data.invoices.revenue.total', 120.50)
        ->assertJsonPath('data.payments.completed', 1)
        ->assertJsonPath('data.top_products.0.name_ar', 'منتج تجريبي');
});

test('dashboard ranks best sellers by base units sold and breaks invoices down by status', function () {
    $customer = Customer::create(['name' => 'عميل', 'email' => 'c@example.com', 'phone' => '+963900000001']);
    $boxed = Product::create(['name_ar' => 'بالعلبة', 'name_en' => 'Boxed', 'price' => 10, 'stock_quantity' => 500, 'is_active' => true]);
    $single = Product::create(['name_ar' => 'بالقطعة', 'name_en' => 'Single', 'price' => 10, 'stock_quantity' => 1, 'is_active' => true]);

    $invoice = fn (string $number, string $status) => Invoice::create([
        'invoice_number' => $number,
        'customer_id' => $customer->id,
        'subtotal' => 100, 'tax' => 0, 'discount' => 0, 'total' => 100,
        'paid_amount' => 0, 'due_amount' => 100,
        'status' => $status,
        'created_by' => $this->user->id,
    ]);

    $sold = $invoice('INV-A', Invoice::STATUS_CONFIRMED);
    $cancelled = $invoice('INV-B', Invoice::STATUS_CANCELLED);
    $invoice('INV-C', Invoice::STATUS_PENDING);

    // 2 boxes of 12 = 24 base units, which outsells 5 single pieces.
    InvoiceItem::create(['invoice_id' => $sold->id, 'product_id' => $boxed->id, 'product_name' => 'Boxed', 'quantity' => 2, 'unit_multiplier' => 12, 'unit_price' => 50, 'total_price' => 100]);
    InvoiceItem::create(['invoice_id' => $sold->id, 'product_id' => $single->id, 'product_name' => 'Single', 'quantity' => 5, 'unit_price' => 10, 'total_price' => 50]);
    // A cancelled invoice's lines don't count as sales.
    InvoiceItem::create(['invoice_id' => $cancelled->id, 'product_id' => $single->id, 'product_name' => 'Single', 'quantity' => 100, 'unit_price' => 10, 'total_price' => 1000]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.best_sellers.0.name_en', 'Boxed')
        ->assertJsonPath('data.best_sellers.0.units_sold', 24)
        ->assertJsonPath('data.best_sellers.1.name_en', 'Single')
        ->assertJsonPath('data.best_sellers.1.units_sold', 5)
        ->assertJsonPath('data.invoices.status_breakdown.confirmed', 1)
        ->assertJsonPath('data.invoices.status_breakdown.cancelled', 1)
        ->assertJsonPath('data.invoices.status_breakdown.pending', 1)
        ->assertJsonPath('data.invoices.revenue.previous_month_to_date', 0);
});
