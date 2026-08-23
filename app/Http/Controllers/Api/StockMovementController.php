<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class StockMovementController extends Controller
{
    protected InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'warehouse', 'creator']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $movements = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Stock movements retrieved successfully',
            'data' => [
                'movements' => $movements->items(),
                'pagination' => [
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'has_more_pages' => $movements->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Records a movement through InventoryService, the single path that keeps
     * stock_movements, warehouse_inventory and products.stock_quantity in step.
     *
     * `movement_key` makes a retry a no-op: the same document re-posted returns
     * the previously recorded movement instead of moving stock a second time.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'movement_type' => 'required|string|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'movement_key' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'unit_cost' => 'nullable|numeric|min:0',
            'condition' => 'nullable|in:available,damaged,quarantined',
        ]);

        try {
            $movement = match ($validated['movement_type']) {
                StockMovement::TYPE_IN => $this->inventory->receive(
                    $validated['product_id'],
                    (int) $validated['quantity'],
                    $validated['warehouse_id'] ?? null,
                    $this->movementOptions($validated)
                ),
                StockMovement::TYPE_OUT => $this->inventory->issue(
                    $validated['product_id'],
                    (int) $validated['quantity'],
                    $validated['warehouse_id'] ?? null,
                    $this->movementOptions($validated)
                ),
                default => $this->inventory->adjust(
                    $validated['product_id'],
                    (int) $validated['quantity'],
                    $validated['warehouse_id'] ?? null,
                    $this->movementOptions($validated)
                ),
            };
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الحركة المخزنية بنجاح',
            'data' => $movement?->load(['product', 'warehouse', 'creator']),
        ], 201);
    }

    /**
     * Moves stock from one warehouse straight into another via
     * InventoryService::transfer() — the atomic primitive, not the
     * InventoryTransfer ship/receive workflow. There is nothing in transit to
     * track here: the destination has the stock the moment this returns.
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            [$out, $in] = $this->inventory->transfer(
                $validated['product_id'],
                $validated['quantity'],
                $validated['from_warehouse_id'],
                $validated['to_warehouse_id'],
                [
                    'reference' => $validated['reference'] ?? null,
                    'reason' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم نقل الكمية بين المستودعين بنجاح',
            'data' => [
                'out' => $out?->load(['product', 'warehouse']),
                'in' => $in?->load(['product', 'warehouse']),
            ],
        ], 201);
    }

    private function movementOptions(array $validated): array
    {
        return [
            'key' => $validated['movement_key'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'source' => $validated['source'] ?? null,
            'reason' => $validated['notes'] ?? null,
            'unit_cost' => $validated['unit_cost'] ?? 0,
            'condition' => $validated['condition'] ?? InventoryService::CONDITION_AVAILABLE,
            'created_by' => auth()->id(),
        ];
    }
}
