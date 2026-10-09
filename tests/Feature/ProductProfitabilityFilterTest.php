<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $warehouse = Warehouse::create(['name' => 'KPI Warehouse', 'code' => 'KPI-WH', 'is_active' => true]);
    $this->basin = Product::create(['name_ar' => 'مغسلة', 'name_en' => 'Basin', 'sku' => 'KPI-BASIN', 'price' => 100, 'cost_price' => 60, 'is_active' => true]);
    $this->tap = Product::create(['name_ar' => 'خلاط', 'name_en' => 'Tap', 'sku' => 'KPI-TAP', 'price' => 40, 'cost_price' => 25, 'is_active' => true]);

    $invoice = function (string $number, string $status, array $lines) use ($warehouse) {
        $total = collect($lines)->sum(fn ($line) => $line[1] * $line[2]);
        $invoice = Invoice::create([
            'invoice_number' => $number, 'status' => $status,
            'subtotal' => $total, 'tax' => 0, 'discount' => 0, 'total' => $total,
            'paid_amount' => 0, 'due_amount' => $total, 'currency' => 'SYP',
        ]);
        foreach ($lines as [$product, $quantity, $price]) {
            $invoice->items()->create([
                'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'product_name' => $product->name_en,
                'quantity' => $quantity, 'unit_price' => $price,
            ]);
        }
    };

    $invoice('INV-KPI-1', Invoice::STATUS_CONFIRMED, [[$this->basin, 2, 100], [$this->tap, 1, 40]]);
    $invoice('INV-KPI-2', Invoice::STATUS_CONFIRMED, [[$this->tap, 3, 40]]);
    $invoice('INV-KPI-3', Invoice::STATUS_CANCELLED, [[$this->basin, 5, 100]]);
});

it('narrows product profitability to the chosen product', function () {
    $this->getJson('/api/v1/admin/reports/invoices/product-profitability?product_id='.$this->tap->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.product_summary')
        ->assertJsonPath('data.product_summary.0.product_id', $this->tap->id)
        ->assertJsonPath('data.product_summary.0.quantity', 4)
        ->assertJsonPath('data.summary.distinct_products', 1);
});

it('can leave cancelled invoices out of product profitability', function () {
    $all = $this->getJson('/api/v1/admin/reports/invoices/product-profitability')->json('data.summary');
    $kept = $this->getJson('/api/v1/admin/reports/invoices/product-profitability?exclude_cancelled=1')->json('data.summary');

    expect($all['total_revenue'])->toEqual(860)
        ->and($kept['total_revenue'])->toEqual(360)
        ->and($kept['distinct_products'])->toBe(2);
});
