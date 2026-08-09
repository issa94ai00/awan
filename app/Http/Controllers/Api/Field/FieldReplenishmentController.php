<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Field\FieldScope;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stock requests a branch raises against the main warehouse.
 *
 * This is a different thing from a sales order and is modelled as one: nothing
 * is sold, no customer exists, no revenue is earned — goods simply move between
 * two of the company's own locations. It runs on `inventory_transfers`, the same
 * records the back office ships against, so a request raised on a phone appears
 * in the warehouse's queue rather than in a mobile-only list nobody reads.
 *
 * The lifecycle is deliberately split between the two ends:
 *
 *   requested   the branch asks; stock is held at the source so it cannot be
 *               promised twice while the request waits
 *   in transit  the main warehouse ships — that end owns the decision
 *   completed   the branch confirms what physically arrived
 *
 * Stock only ever moves through InventoryService, so every leg writes a movement
 * and the warehouse figures stay in step with the audit trail.
 */
class FieldReplenishmentController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /** Requests raised for the branches this person covers. */
    public function index(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        $query = InventoryTransfer::query()
            ->with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'items.product:id,sku,name_ar,name_en'])
            ->whereIn('to_warehouse_id', $scope->warehouseIds());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('open_only')) {
            $query->whereIn('status', [InventoryTransfer::STATUS_PENDING, InventoryTransfer::STATUS_IN_TRANSIT]);
        }

        $rows = $query->latest('id')->paginate(min((int) $request->input('per_page', 20) ?: 20, 100));

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => collect($rows->items())->map(fn ($t) => $this->present($t))->all(),
                'pagination' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'has_more_pages' => $rows->hasMorePages(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transfer = InventoryTransfer::with([
            'fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'items.product:id,sku,name_ar,name_en',
        ])->findOrFail($id);

        if ($denied = $this->guard($request, $transfer)) {
            return $denied;
        }

        return response()->json(['success' => true, 'data' => ['request' => $this->present($transfer)]]);
    }

    /**
     * Raises a request against the main warehouse.
     *
     * The source defaults to the warehouse flagged primary — a branch asking
     * "send me stock" means the main store, and making the app name it would
     * only invite it to name the wrong one.
     */
    public function store(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());

        $validated = $request->validate([
            'to_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'from_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $destination = $scope->resolveWarehouseId($validated['to_warehouse_id'] ?? null);
            $source = $this->resolveSource($validated['from_warehouse_id'] ?? null, $destination);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        try {
            $transfer = DB::transaction(function () use ($validated, $source, $destination, $request) {
                $transfer = InventoryTransfer::create([
                    'transfer_number' => 'TRF-' . str_pad((string) (((int) InventoryTransfer::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                    'from_warehouse_id' => $source,
                    'to_warehouse_id' => $destination,
                    'status' => InventoryTransfer::STATUS_PENDING,
                    'requested_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))->get()->keyBy('id');

                foreach ($validated['items'] as $item) {
                    $product = $products[$item['product_id']];

                    // Held at the source while the request waits, so the same
                    // units cannot be promised to a sale in the meantime.
                    if (!$this->inventory->reserve((int) $item['product_id'], (int) $item['quantity'], $source)) {
                        throw new RuntimeException(sprintf(
                            'الكمية المتاحة غير كافية من "%s" في مستودع المصدر.',
                            $product->name_ar ?? $product->name_en ?? ('#' . $product->id)
                        ));
                    }

                    InventoryTransferItem::create([
                        'transfer_id' => $transfer->id,
                        'product_id' => $product->id,
                        'quantity_requested' => (int) $item['quantity'],
                        // Costed from the catalogue: the app has no business
                        // deciding what the company's own goods are worth.
                        'unit_cost' => (float) ($product->cost_price ?? 0),
                    ]);
                }

                return $transfer;
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        $transfer->load(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'items.product:id,sku,name_ar,name_en']);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب التزويد إلى المستودع الرئيسي وحُجزت الكميات.',
            'data' => ['request' => $this->present($transfer)],
        ], 201);
    }

    /**
     * The branch confirms what actually arrived.
     *
     * Received quantities are taken per line rather than assumed, because a
     * shipment that arrives short is the normal case this step exists to catch;
     * booking the requested amount would invent stock that never came.
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $transfer = InventoryTransfer::with('items')->findOrFail($id);

        if ($denied = $this->guard($request, $transfer)) {
            return $denied;
        }

        if ($transfer->status !== InventoryTransfer::STATUS_IN_TRANSIT) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن استلام طلب لم يُشحن بعد من المستودع الرئيسي. الحالة الحالية: ' . $this->statusText($transfer->status),
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.quantity_received' => 'required_with:items|integer|min:0',
        ]);

        $received = collect($validated['items'] ?? [])->keyBy('product_id');

        DB::transaction(function () use ($transfer, $received, $request) {
            foreach ($transfer->items as $item) {
                $qty = $received->has($item->product_id)
                    ? (int) $received[$item->product_id]['quantity_received']
                    : (int) ($item->quantity_shipped ?: $item->quantity_requested);

                if ($qty <= 0) {
                    continue;
                }

                $this->inventory->receive(
                    (int) $item->product_id,
                    $qty,
                    (int) $transfer->to_warehouse_id,
                    [
                        'key' => 'transfer:' . $transfer->id . ':in:' . $item->product_id,
                        'reference' => 'inventory_transfer',
                        'source' => (string) $transfer->id,
                        'reason' => 'استلام تزويد من المستودع الرئيسي - ' . $transfer->transfer_number,
                        'unit_cost' => (float) $item->unit_cost,
                        'created_by' => $request->user()->id,
                    ]
                );

                $item->update(['quantity_received' => $qty]);
            }

            $transfer->update([
                'status' => InventoryTransfer::STATUS_COMPLETED,
                'received_at' => now(),
                'received_by' => $request->user()->id,
            ]);
        });

        $transfer->load(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'items.product:id,sku,name_ar,name_en']);

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الاستلام وإدخال الكميات لمخزون الفرع.',
            'data' => ['request' => $this->present($transfer)],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @throws RuntimeException */
    private function resolveSource(?int $requested, int $destination): int
    {
        $source = $requested ?: (int) (Warehouse::where('is_primary', true)->value('id') ?? 0);

        if (!$source) {
            throw new RuntimeException('لا يوجد مستودع رئيسي محدد في النظام.');
        }

        if ($source === $destination) {
            throw new RuntimeException('لا يمكن طلب تزويد من المستودع نفسه.');
        }

        return $source;
    }

    private function guard(Request $request, InventoryTransfer $transfer): ?JsonResponse
    {
        $allowed = FieldScope::for($request->user())->warehouseIds();

        if (in_array((int) $transfer->to_warehouse_id, $allowed, true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'هذا الطلب يخص مستودعاً خارج نطاق صلاحيتك.',
            'data' => null,
        ], 403);
    }

    private function statusText(string $status): string
    {
        return [
            InventoryTransfer::STATUS_PENDING => 'بانتظار الشحن من المستودع الرئيسي',
            InventoryTransfer::STATUS_IN_TRANSIT => 'قيد النقل',
            InventoryTransfer::STATUS_COMPLETED => 'مكتمل',
            InventoryTransfer::STATUS_CANCELLED => 'ملغي',
        ][$status] ?? $status;
    }

    /** @return array<string,mixed> */
    private function present(InventoryTransfer $t): array
    {
        return [
            'id' => $t->id,
            'request_number' => $t->transfer_number,
            'status' => $t->status,
            'status_text' => $this->statusText($t->status),
            'from_warehouse_id' => (int) $t->from_warehouse_id,
            'from_warehouse_name' => $t->fromWarehouse?->name,
            'to_warehouse_id' => (int) $t->to_warehouse_id,
            'to_warehouse_name' => $t->toWarehouse?->name,
            // The app shows the receive button off this rather than re-deriving
            // the rule, so the two can never disagree about what is allowed.
            'can_receive' => $t->status === InventoryTransfer::STATUS_IN_TRANSIT,
            'notes' => $t->notes,
            'requested_at' => $t->requested_at?->toDateTimeString(),
            'shipped_at' => $t->shipped_at?->toDateTimeString(),
            'received_at' => $t->received_at?->toDateTimeString(),
            'items' => $t->items->map(fn ($i) => [
                'product_id' => (int) $i->product_id,
                'sku' => $i->product?->sku,
                'product_name' => $i->product?->name_ar ?? $i->product?->name_en,
                'quantity_requested' => (int) $i->quantity_requested,
                'quantity_shipped' => (int) ($i->quantity_shipped ?? 0),
                'quantity_received' => (int) ($i->quantity_received ?? 0),
            ])->values(),
        ];
    }
}
