<?php

use App\Models\Customer;
use App\Models\Invoice;
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
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_phone' => $customer->phone,
        'subtotal' => 120.50,
        'tax' => 0,
        'discount' => 0,
        'total' => 120.50,
        'paid_amount' => 120.50,
        'due_amount' => 0,
        'status' => Invoice::STATUS_PAID,
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
