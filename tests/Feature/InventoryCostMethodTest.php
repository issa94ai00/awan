<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryCostingService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCostMethodTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-TEST',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'name_ar' => 'منتج تجريبي',
            'name_en' => 'Test Product',
            'price' => 100,
            'cost_price' => 50,
            'stock_quantity' => 0,
        ]);
    }

    public function test_it_returns_cost_calculation_method_config(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('api.admin.inventory.costing-method.show'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_method', 'FIFO')
            ->assertJsonStructure([
                'data' => [
                    'current_method',
                    'available_methods',
                    'warehouse_methods',
                    'warehouses',
                    'stats' => [
                        'total_value',
                        'active_layers_count',
                        'warehouses_count',
                    ],
                ],
            ]);
    }

    public function test_it_updates_global_and_warehouse_cost_calculation_methods(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('api.admin.inventory.costing-method.update'), [
            'method' => 'WEIGHTED_AVERAGE',
            'warehouse_methods' => [
                $this->warehouse->id => 'LIFO',
            ],
            'apply_to_existing' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_method', 'WEIGHTED_AVERAGE');

        $service = app(InventoryCostingService::class);
        $this->assertEquals('WEIGHTED_AVERAGE', $service->getGlobalMethod());
        $this->assertEquals('LIFO', $service->resolveCostMethod($this->product->id, $this->warehouse->id));
    }

    public function test_fifo_consumes_oldest_layers_first(): void
    {
        $costing = app(InventoryCostingService::class);

        // Layer 1: 10 units at 20
        $costing->addLayer($this->product->id, $this->warehouse->id, 10, 20.0, [
            'received_at' => now()->subDays(2),
        ]);

        // Layer 2: 10 units at 40
        $costing->addLayer($this->product->id, $this->warehouse->id, 10, 40.0, [
            'received_at' => now()->subDay(),
        ]);

        // Consume 5 units under FIFO -> should cost 5 * 20 = 100
        $result = $costing->consume($this->product->id, $this->warehouse->id, 5, 'FIFO');

        $this->assertEquals(100.0, $result['cost']);
        $this->assertEquals(20.0, $result['unit_cost']);
    }

    public function test_lifo_consumes_newest_layers_first(): void
    {
        $costing = app(InventoryCostingService::class);

        // Layer 1: 10 units at 20 (older)
        $costing->addLayer($this->product->id, $this->warehouse->id, 10, 20.0, [
            'received_at' => now()->subDays(2),
        ]);

        // Layer 2: 10 units at 40 (newer)
        $costing->addLayer($this->product->id, $this->warehouse->id, 10, 40.0, [
            'received_at' => now()->subDay(),
        ]);

        // Consume 5 units under LIFO -> should cost 5 * 40 = 200
        $result = $costing->consume($this->product->id, $this->warehouse->id, 5, 'LIFO');

        $this->assertEquals(200.0, $result['cost']);
        $this->assertEquals(40.0, $result['unit_cost']);
    }

    public function test_weighted_average_costs_at_average_unit_cost(): void
    {
        $costing = app(InventoryCostingService::class);

        // Layer 1: 10 units at 20 (total 200)
        $costing->addLayer($this->product->id, $this->warehouse->id, 10, 20.0, [
            'received_at' => now()->subDays(2),
        ]);

        // Layer 2: 10 units at 40 (total 400)
        // Combined: 20 units, 600 total -> average 30.0
        $costing->addLayer($this->product->id, $this->warehouse->id, 10, 40.0, [
            'received_at' => now()->subDay(),
        ]);

        // Consume 5 units under WEIGHTED_AVERAGE -> should cost 5 * 30 = 150
        $result = $costing->consume($this->product->id, $this->warehouse->id, 5, 'WEIGHTED_AVERAGE');

        $this->assertEquals(150.0, $result['cost']);
        $this->assertEquals(30.0, $result['unit_cost']);
    }
}
