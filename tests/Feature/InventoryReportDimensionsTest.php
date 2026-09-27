<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

function inventoryReportWarehouse(string $code): Warehouse
{
    return Warehouse::create([
        'name' => "Warehouse {$code}",
        'code' => $code,
        'city' => 'Damascus',
        'country' => 'SY',
        'is_active' => true,
        'location_type' => 'warehouse',
    ]);
}

function inventoryReportStock(Warehouse $warehouse, Product $product, int $quantity, int $reserved, int $reorderPoint): void
{
    DB::table('warehouse_inventory')->insert([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'reserved_quantity' => $reserved,
        'available_quantity' => $quantity,
        'reorder_point' => $reorderPoint,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $this->main = inventoryReportWarehouse('INV-A');
    $this->branch = inventoryReportWarehouse('INV-B');

    $this->basin = Product::create(['name_ar' => 'مغسلة', 'name_en' => 'Basin', 'sku' => 'INV-BASIN', 'price' => 50, 'cost_price' => 30, 'is_active' => true]);
    $this->tap = Product::create(['name_ar' => 'خلاط', 'name_en' => 'Tap', 'sku' => 'INV-TAP', 'price' => 20, 'cost_price' => 12, 'is_active' => true]);
    $this->valve = Product::create(['name_ar' => 'محبس', 'name_en' => 'Valve', 'sku' => 'INV-VALVE', 'price' => 10, 'cost_price' => 4, 'is_active' => true]);

    inventoryReportStock($this->main, $this->basin, 40, 0, 5);   // healthy
    inventoryReportStock($this->main, $this->tap, 6, 2, 5);      // 4 free of a reorder point of 5: low
    inventoryReportStock($this->branch, $this->valve, 3, 3, 1);  // all of it reserved: out
});

it('reports stock health, value at cost and at price, and the largest holdings', function () {
    $this->getJson('/api/v1/admin/reports/inventory/dimensions')
        ->assertOk()
        ->assertJsonPath('data.overall.product_count', 3)
        ->assertJsonPath('data.overall.healthy_rows', 1)
        ->assertJsonPath('data.overall.low_stock_rows', 1)
        ->assertJsonPath('data.overall.out_of_stock_rows', 1)
        // Available is what can be sold: reserved stock is not.
        ->assertJsonPath('data.overall.total_available', 44)
        ->assertJsonPath('data.overall.total_value', 2150)
        ->assertJsonPath('data.overall.total_cost_value', 1284)
        ->assertJsonPath('data.overall.uncosted_products', 0)
        ->assertJsonPath('data.top_products.0.product_name', 'مغسلة')
        ->assertJsonPath('data.top_products.0.total_quantity', 40)
        ->assertJsonCount(3, 'data.top_products');
});

it('scopes every figure to the chosen warehouse', function () {
    $this->getJson('/api/v1/admin/reports/inventory/dimensions?warehouse_id='.$this->branch->id)
        ->assertOk()
        ->assertJsonPath('data.overall.product_count', 1)
        ->assertJsonPath('data.overall.healthy_rows', 0)
        ->assertJsonPath('data.overall.out_of_stock_rows', 1)
        ->assertJsonCount(1, 'data.top_products')
        ->assertJsonPath('data.top_products.0.sku', 'INV-VALVE')
        ->assertJsonCount(1, 'data.warehouse_summary');
});
