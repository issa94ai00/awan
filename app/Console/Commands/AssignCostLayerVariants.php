<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ties existing open cost layers to the variant they were bought as
 * (docs/fifo-variant-cost-layers-plan.md).
 *
 * Layers written before variant tracking have product_variant_id null, and a
 * null layer is drawable by every variant — so a piece can still be costed at
 * a box's price until the old layers are used up. This assigns a layer only
 * when the evidence points to exactly one variant:
 *
 *  - purchase_receipt layers: the receipt (layer.reference = receipt_number)
 *    has exactly one variant of the product, or exactly one line of it whose
 *    quantity and net unit price match the layer;
 *  - any other layer: exactly one variant of the product has a cost_price
 *    equal to the layer's unit cost.
 *
 * Anything ambiguous stays null — drawable by all, as before; nothing is worse
 * off than today. Only open layers of products that have variants are touched.
 * Reversible: set product_variant_id back to null.
 */
class AssignCostLayerVariants extends Command
{
    protected $signature = 'inventory:assign-layer-variants {--dry-run : Report what would be assigned without changing anything}';

    protected $description = 'Assign open pre-variant cost layers to their product variant where it is unambiguous';

    public function handle(): int
    {
        $layers = DB::table('inventory_cost_layers as l')
            ->whereNull('l.product_variant_id')
            ->where('l.remaining_quantity', '>', 0)
            ->whereExists(fn ($q) => $q->from('product_variants as v')->whereColumn('v.product_id', 'l.product_id'))
            ->orderBy('l.id')
            ->get();

        $variantsByProduct = DB::table('product_variants')
            ->whereIn('product_id', $layers->pluck('product_id')->unique())
            ->get(['id', 'product_id', 'cost_price'])
            ->groupBy('product_id');

        $stats = ['receipt' => 0, 'cost_match' => 0, 'ambiguous' => 0, 'no_evidence' => 0];
        $assign = [];

        foreach ($layers as $layer) {
            $variantId = null;
            $how = null;

            if ($layer->source === 'purchase_receipt' && $layer->reference) {
                $lines = DB::table('purchase_receipt_items as ri')
                    ->join('purchase_receipts as r', 'r.id', '=', 'ri.purchase_receipt_id')
                    ->where('r.receipt_number', $layer->reference)
                    ->where('ri.product_id', $layer->product_id)
                    ->whereNotNull('ri.product_variant_id')
                    ->get(['ri.product_variant_id', 'ri.quantity', 'ri.unit_price', 'r.discount', 'r.discount_percent']);

                $distinct = $lines->pluck('product_variant_id')->unique();
                if ($distinct->count() === 1) {
                    $variantId = (int) $distinct->first();
                } elseif ($distinct->count() > 1) {
                    $match = $lines->filter(fn ($ri) => (int) $ri->quantity === (int) $layer->received_quantity
                        && abs((float) $ri->unit_price - (float) $layer->unit_cost) <= max(0.0005, 0.05 * (float) $layer->unit_cost));
                    if ($match->pluck('product_variant_id')->unique()->count() === 1) {
                        $variantId = (int) $match->first()->product_variant_id;
                    }
                }
                $how = 'receipt';
            }

            if ($variantId === null) {
                $byCost = ($variantsByProduct[$layer->product_id] ?? collect())
                    ->filter(fn ($v) => (float) $v->cost_price > 0 && abs((float) $v->cost_price - (float) $layer->unit_cost) < 0.0005);
                if ($byCost->count() === 1) {
                    $variantId = (int) $byCost->first()->id;
                    $how = 'cost_match';
                } else {
                    $stats[$byCost->count() > 1 || $how === 'receipt' ? 'ambiguous' : 'no_evidence']++;
                    continue;
                }
            }

            $stats[$how]++;
            $assign[$layer->id] = $variantId;
        }

        if (! $this->option('dry-run') && $assign !== []) {
            DB::transaction(function () use ($assign) {
                foreach ($assign as $layerId => $variantId) {
                    DB::table('inventory_cost_layers')->where('id', $layerId)->whereNull('product_variant_id')
                        ->update(['product_variant_id' => $variantId, 'updated_at' => now()]);
                }
            });
        }

        $this->table(['', 'layers'], [
            ['open layers without variant (products with variants)', $layers->count()],
            ['assigned from purchase receipt', $stats['receipt']],
            ['assigned by unique cost match', $stats['cost_match']],
            ['left shared: ambiguous', $stats['ambiguous']],
            ['left shared: no evidence', $stats['no_evidence']],
        ]);
        $this->info(($this->option('dry-run') ? 'Would assign ' : 'Assigned ').count($assign).' layer(s).');

        return self::SUCCESS;
    }
}
