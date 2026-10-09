<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'role_id' => Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'admin'])->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'المستودع الرئيسي',
        'code' => 'WH-MAIN',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'name' => 'شركة التجارة الحديثة',
        'email' => 'trade@example.test',
        'phone' => '0999123456',
        'status' => 'active',
    ]);

    $this->product = Product::create([
        'name_ar' => 'مغسلة ديكور رخامية',
        'sku' => 'SINK-001',
        'price' => 1000,
        'cost_price' => 600,
        'stock_quantity' => 100,
        'is_active' => true,
    ]);

    // Stock the warehouse
    app(\App\Services\Inventory\InventoryService::class)->receive(
        $this->product->id,
        100,
        $this->warehouse->id,
        ['key' => uniqid('recv-', true), 'unit_cost' => 600]
    );
});

test('creating sales order with expenses records expenses and links to invoice on confirmation', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'fulfillment_warehouse_id' => $this->warehouse->id,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 1000,
                'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 2],
                ],
            ],
        ],
        'expenses' => [
            [
                'category' => 'shipping',
                'description' => 'أجور شحن مع شركة القدموس',
                'amount' => 150,
                'status' => 'paid',
                'notes' => 'بوليصة رقم 98765',
            ],
            [
                'category' => 'packaging',
                'description' => 'تغليف وتثبيت خشبي',
                'amount' => 50,
                'status' => 'pending',
                'notes' => null,
            ],
        ],
    ];

    $res = $this->actingAs($this->admin)->postJson('/api/v1/sales-orders', $payload);
    $res->assertStatus(201);

    $orderId = $res->json('data.id');
    $order = SalesOrder::with('expenses')->findOrFail($orderId);

    // Total should include shipping_cost derived from expenses (150 + 50 = 200)
    expect((float) $order->shipping_cost)->toEqual(200.0);
    expect((float) $order->total)->toEqual(2200.0); // 2000 items + 200 expenses

    // Verify expense records
    expect($order->expenses)->toHaveCount(2);
    $shippingExp = $order->expenses->firstWhere('category', 'shipping');
    expect($shippingExp)->not->toBeNull();
    expect((float) $shippingExp->amount)->toEqual(150.0);
    expect($shippingExp->status)->toBe('paid');
    expect($shippingExp->sales_order_id)->toBe($order->id);
    expect($shippingExp->invoice_id)->toBeNull();

    // Verify ledger entry for paid shipping expense
    $entry = JournalEntryHeader::where('posting_key', $shippingExp->postingKey())->first();
    expect($entry)->not->toBeNull();

    // Now confirm the sales order -> should generate invoice and link expenses!
    $confirmRes = $this->actingAs($this->admin)->postJson("/api/v1/sales-orders/{$order->id}/confirm");
    $confirmRes->assertOk();

    // Refresh expenses
    $order->refresh();
    $invoice = Invoice::where('sales_order_id', $order->id)->first();
    expect($invoice)->not->toBeNull();

    $reloadedExpenses = Expense::where('sales_order_id', $order->id)->get();
    foreach ($reloadedExpenses as $exp) {
        expect($exp->invoice_id)->toBe($invoice->id);
    }
});

test('creating invoice directly with expenses records expenses and shows them in resource', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'warehouse_id' => $this->warehouse->id,
            ],
        ],
        'expenses' => [
            [
                'category' => 'shipping',
                'description' => 'شحن وتوصيل فوري',
                'amount' => 75,
                'status' => 'paid',
                'notes' => 'تم الدفع نقداً للسائق',
            ],
        ],
    ];

    $res = $this->actingAs($this->admin)->postJson('/api/v1/invoices', $payload);
    $res->assertStatus(201);

    $invoiceId = $res->json('data.id');
    $invoice = Invoice::with('expenses')->findOrFail($invoiceId);

    expect((float) $invoice->additional_charges)->toEqual(75.0);
    expect((float) $invoice->total)->toEqual(1075.0);
    expect($invoice->expenses)->toHaveCount(1);
    expect($invoice->expenses->first()->invoice_id)->toBe($invoice->id);
    expect($invoice->expenses->first()->status)->toBe('paid');

    // Check show endpoint returns expenses
    $showRes = $this->actingAs($this->admin)->getJson("/api/v1/invoices/{$invoiceId}");
    $showRes->assertOk();
    $showData = $showRes->json('data');
    expect($showData['expenses'])->toBeArray();
    expect(count($showData['expenses']))->toBe(1);
    expect($showData['expenses'][0]['category'])->toBe('shipping');
    expect((float) $showData['expenses'][0]['amount'])->toEqual(75.0);
});

test('creating expense via expense endpoint with sales_order_id links customer and invoice', function () {
    $order = SalesOrder::create([
        'order_number' => 'SO-TEST-99',
        'customer_id' => $this->customer->id,
        'subtotal' => 1000,
        'total' => 1000,
        'status' => 'confirmed',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-99',
        'customer_id' => $this->customer->id,
        'sales_order_id' => $order->id,
        'subtotal' => 1000,
        'total' => 1000,
        'status' => 'confirmed',
    ]);

    $payload = [
        'description' => 'شحن متأخر للطلب',
        'amount' => 120,
        'category' => 'shipping',
        'status' => 'paid',
        'expense_date' => now()->toDateString(),
        'sales_order_id' => $order->id,
    ];

    $res = $this->actingAs($this->admin)->postJson('/api/v1/expenses', $payload);
    $res->assertStatus(201);

    $expenseData = $res->json('data');
    expect($expenseData['sales_order_id'])->toBe($order->id);
    expect($expenseData['customer_id'])->toBe($this->customer->id);
    expect($expenseData['invoice_id'])->toBe($invoice->id);
});

test('updating invoice expenses reverses old ledger entries and creates new ones', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'warehouse_id' => $this->warehouse->id,
            ],
        ],
        'expenses' => [
            [
                'category' => 'shipping',
                'description' => 'شحن أولي',
                'amount' => 50,
                'status' => 'paid',
            ],
        ],
    ];

    $res = $this->actingAs($this->admin)->postJson('/api/v1/invoices', $payload);
    $res->assertStatus(201);
    $invoiceId = $res->json('data.id');
    $invoice = Invoice::findOrFail($invoiceId);
    $initialExp = Expense::where('invoice_id', $invoice->id)->first();
    expect($initialExp)->not->toBeNull();

    // Now update invoice with different expenses
    $updatePayload = [
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'warehouse_id' => $this->warehouse->id,
            ],
        ],
        'expenses' => [
            [
                'category' => 'shipping',
                'description' => 'شحن معدل سريع',
                'amount' => 90,
                'status' => 'paid',
            ],
            [
                'category' => 'packaging',
                'description' => 'تغليف إضافي',
                'amount' => 30,
                'status' => 'pending',
            ],
        ],
    ];

    $updateRes = $this->actingAs($this->admin)->putJson("/api/v1/invoices/{$invoiceId}", $updatePayload);
    $updateRes->assertOk();

    $reloaded = Invoice::with('expenses')->findOrFail($invoiceId);
    expect((float) $reloaded->additional_charges)->toEqual(120.0);
    expect((float) $reloaded->total)->toEqual(1120.0);
    expect($reloaded->expenses)->toHaveCount(2);

    // Initial expense should be gone, and reversal entry should exist
    expect(Expense::find($initialExp->id))->toBeNull();
    $reversal = JournalEntryHeader::where('posting_key', $initialExp->postingKey().':reversal')->first();
    expect($reversal)->not->toBeNull();
});

test('updating pending sales order expenses updates expenses and reflects in shipping_cost', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'fulfillment_warehouse_id' => $this->warehouse->id,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 1],
                ],
            ],
        ],
        'expenses' => [
            [
                'category' => 'shipping',
                'description' => 'شحن عادي',
                'amount' => 40,
                'status' => 'pending',
            ],
        ],
    ];

    $res = $this->actingAs($this->admin)->postJson('/api/v1/sales-orders', $payload);
    $res->assertStatus(201);
    $orderId = $res->json('data.id');

    $order = SalesOrder::with('expenses')->findOrFail($orderId);
    expect((float) $order->shipping_cost)->toEqual(40.0);
    expect($order->expenses)->toHaveCount(1);

    // Update with new expenses
    $updatePayload = [
        'customer_id' => $this->customer->id,
        'fulfillment_warehouse_id' => $this->warehouse->id,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 1],
                ],
            ],
        ],
        'expenses' => [
            [
                'category' => 'shipping',
                'description' => 'شحن سريع ممتاز',
                'amount' => 85,
                'status' => 'paid',
            ],
        ],
    ];

    $updateRes = $this->actingAs($this->admin)->putJson("/api/v1/sales-orders/{$orderId}", $updatePayload);
    $updateRes->assertOk();

    $reloaded = SalesOrder::with('expenses')->findOrFail($orderId);
    expect((float) $reloaded->shipping_cost)->toEqual(85.0);
    expect((float) $reloaded->total)->toEqual(1085.0);
    expect($reloaded->expenses)->toHaveCount(1);
    expect($reloaded->expenses->first()->description)->toBe('شحن سريع ممتاز');
    expect((float) $reloaded->expenses->first()->amount)->toEqual(85.0);
});
