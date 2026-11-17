<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\Inventory\InventoryCostingService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cost layers per variant (docs/fifo-variant-cost-layers-plan.md).
 *
 * A product sold as a single piece and as a box of 20 used to share one FIFO
 * queue, so a piece could be costed at a box's price. These pin down that a
 * variant draws its own layers, then shared (pre-variant) layers, never
 * another variant's — and that callers without a variant see no change.
 */
class InventoryVariantCostLayersTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Product $product;
    private ProductVariant $piece;
    private ProductVariant $box;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH-VAR', 'is_active' => true]);
        $this->product = Product::factory()->create(['price' => 5, 'cost_price' => 3, 'stock_quantity' => 0]);
        $this->piece = ProductVariant::create(['product_id' => $this->product->id, 'sku' => 'PIECE-'.uniqid(), 'price' => 3.5, 'cost_price' => 3.15, 'stock_quantity' => 0]);
        $this->box = ProductVariant::create(['product_id' => $this->product->id, 'sku' => 'BOX-'.uniqid(), 'price' => 70, 'cost_price' => 63, 'stock_quantity' => 0]);
        $this->inventory = app(InventoryService::class);
    }

    private function receive(int $qty, float $cost, ?ProductVariant $variant, string $key): void
    {
        $this->inventory->receive($this->product->id, $qty, $this->warehouse->id, [
            'key' => $key, 'unit_cost' => $cost, 'source' => 'purchase_receipt',
            'cost_variant_id' => $variant?->id,
        ]);
    }

    public function test_a_variant_draws_its_own_layers_not_an_older_layer_of_another_variant(): void
    {
        // The box arrives first, so it is the oldest layer of the product.
        $this->receive(2, 63, $this->box, 'r-box');
        $this->receive(10, 3.15, $this->piece, 'r-piece');

        $out = $this->inventory->issue($this->product->id, 1, $this->warehouse->id, [
            'key' => 'sale-piece', 'cost_variant_id' => $this->piece->id,
        ]);

        $this->assertEqualsWithDelta(3.15, (float) $out->unit_cost, 0.0001);
        $this->assertSame(2, (int) DB::table('inventory_cost_layers')->where('product_variant_id', $this->box->id)->value('remaining_quantity'));
        $this->assertSame(9, (int) DB::table('inventory_cost_layers')->where('product_variant_id', $this->piece->id)->value('remaining_quantity'));
    }

    public function test_shared_layers_are_drawn_after_the_variants_own_and_never_another_variants(): void
    {
        $this->receive(5, 2.0, null, 'r-shared');      // pre-variant stock
        $this->receive(3, 63, $this->box, 'r-box');
        $this->receive(2, 3.15, $this->piece, 'r-piece');

        // 2 own piece layers at 3.15, then 3 from the shared layer at 2.0.
        $out = $this->inventory->issue($this->product->id, 5, $this->warehouse->id, [
            'key' => 'sale-piece', 'cost_variant_id' => $this->piece->id,
        ]);

        $this->assertEqualsWithDelta(2 * 3.15 + 3 * 2.0, (float) $out->total_cost, 0.0001);
        $this->assertSame(3, (int) DB::table('inventory_cost_layers')->where('product_variant_id', $this->box->id)->value('remaining_quantity'));
        $this->assertSame(2, (int) DB::table('inventory_cost_layers')->whereNull('product_variant_id')->value('remaining_quantity'));
    }

    public function test_when_a_variants_layers_run_out_the_variants_own_cost_is_the_fallback(): void
    {
        $this->receive(5, 63, $this->box, 'r-box');
        // Stock for the piece exists on the shelf (balances are per product) but
        // no piece layer: it must be costed at the piece's cost, not the box's.
        $out = $this->inventory->issue($this->product->id, 2, $this->warehouse->id, [
            'key' => 'sale-piece', 'cost_variant_id' => $this->piece->id,
        ]);

        $this->assertEqualsWithDelta(2 * 3.15, (float) $out->total_cost, 0.0001);
        $this->assertSame(5, (int) DB::table('inventory_cost_layers')->where('product_variant_id', $this->box->id)->value('remaining_quantity'));
    }

    public function test_without_a_variant_the_behaviour_is_unchanged(): void
    {
        $this->receive(2, 63, $this->box, 'r-box');
        $this->receive(10, 3.15, $this->piece, 'r-piece');

        // A caller that does not know the variant draws the oldest layer of the product, as before.
        $out = $this->inventory->issue($this->product->id, 1, $this->warehouse->id, ['key' => 'legacy']);

        $this->assertEqualsWithDelta(63, (float) $out->unit_cost, 0.0001);
    }

    public function test_balances_stay_per_product_when_a_variant_is_costed(): void
    {
        $this->receive(4, 3.15, $this->piece, 'r-piece');
        $this->inventory->issue($this->product->id, 1, $this->warehouse->id, ['key' => 's1', 'cost_variant_id' => $this->piece->id]);

        // The variant only steers costing: no per-variant warehouse row is opened.
        $rows = WarehouseInventory::where('product_id', $this->product->id)->get();
        $this->assertCount(1, $rows);
        $this->assertNull($rows->first()->product_variant_id);
        $this->assertSame(3, (int) $rows->first()->quantity);
    }

    public function test_weighted_average_is_per_variant(): void
    {
        $costing = app(InventoryCostingService::class);
        $this->receive(1, 60, $this->box, 'r-box-1');
        $this->receive(1, 66, $this->box, 'r-box-2');
        $this->receive(10, 3.0, $this->piece, 'r-piece');

        $result = $costing->consume($this->product->id, $this->warehouse->id, 1, 'WEIGHTED_AVERAGE', $this->piece->id);
        $this->assertEqualsWithDelta(3.0, $result['unit_cost'], 0.0001);

        // The box layers were not averaged with the pieces.
        $this->assertEqualsWithDelta(126, (float) DB::table('inventory_cost_layers')
            ->where('product_variant_id', $this->box->id)->sum(DB::raw('remaining_quantity * unit_cost')), 0.0001);

        $this->assertSame(1, $costing->recalculateWeightedAverageForWarehouse($this->warehouse->id) >= 1 ? 1 : 0);
        $this->assertEqualsWithDelta(63, (float) DB::table('inventory_cost_layers')
            ->where('product_variant_id', $this->box->id)->value('unit_cost'), 0.0001);
    }

    public function test_a_transfer_keeps_the_variant_of_the_layer(): void
    {
        $other = Warehouse::create(['name' => 'Branch', 'code' => 'WH-VAR-2', 'is_active' => true]);
        $this->receive(3, 63, $this->box, 'r-box');

        // No key: per-leg idempotency keys are a separate concern of transfer().
        $this->inventory->transfer($this->product->id, 1, $this->warehouse->id, $other->id, [
            'cost_variant_id' => $this->box->id,
        ]);

        // The destination layer is the same variant (what it is priced at is
        // transfer()'s own concern, covered by its tests).
        $layer = DB::table('inventory_cost_layers')->where('warehouse_id', $other->id)->first();
        $this->assertNotNull($layer);
        $this->assertSame($this->box->id, (int) $layer->product_variant_id);
    }
}
