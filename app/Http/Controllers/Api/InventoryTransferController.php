<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\WarehouseInventory;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReplenishmentWorkflowService;
use App\Services\InventoryAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Back-office view of stock transfers between warehouses.
 *
 * Ship, receive and cancel all go through ReplenishmentWorkflowService — the
 * same door the field app uses. This controller had its own copy of those rules
 * and the two had already diverged: a receipt with no explicit quantity meant
 * "nothing" here and "everything that shipped" there, so the same transfer
 * settled differently depending on which screen closed it.
 */
class InventoryTransferController extends Controller
{
    protected InventoryAllocationService $allocationService;
    protected InventoryService $inventory;
    protected ReplenishmentWorkflowService $workflow;

    public function __construct(
        InventoryAllocationService $allocationService,
        InventoryService $inventory,
        ReplenishmentWorkflowService $workflow
    ) {
        $this->allocationService = $allocationService;
        $this->inventory = $inventory;
        $this->workflow = $workflow;
    }

    public function index(Request $request)
    {
        $query = InventoryTransfer::with(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }

        if ($request->has('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $transfers,
        ]);
    }

    public function show($id)
    {
        $transfer = InventoryTransfer::with([
            'fromWarehouse',
            'toWarehouse',
            'items.product',
            'items.variant',
            'createdBy',
            'shippedBy',
            'receivedBy'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transfer,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $transfer = InventoryTransfer::create([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'status' => InventoryTransfer::STATUS_PENDING,
                'requested_at' => now(),
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                // Reserve through the service so availability is judged against
                // the same buckets every other path uses, and the reservation is
                // tracked in one place.
                if (!$this->inventory->reserve($item['product_id'], $item['quantity'], $request->from_warehouse_id)) {
                    throw new \Exception("Insufficient stock for product ID: {$item['product_id']}");
                }

                InventoryTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity_requested' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب النقل بنجاح',
                'data' => $transfer->load('items'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    /**
     * Approve the transfer and put the goods on their way.
     *
     * Kept at `ship` because that is what the back office calls it and what the
     * existing screens post to, but it is the same approval the field app makes:
     * `fulfillment_method` decides whether the stock leaves now (delivery) or is
     * set aside for the requester to collect (pickup). Shipping is the default,
     * which is what this endpoint always did.
     */
    public function ship(Request $request, $id)
    {
        $transfer = InventoryTransfer::with('items.product')->findOrFail($id);

        $validated = $request->validate([
            'fulfillment_method' => 'nullable|in:delivery,pickup',
        ]);

        try {
            $effects = $this->workflow->approve(
                $transfer,
                $validated['fulfillment_method'] ?? InventoryTransfer::METHOD_DELIVERY,
                auth()->id()
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
            'message' => $effects['method'] === InventoryTransfer::METHOD_DELIVERY
                ? 'تم شحن الطلب بنجاح'
                : 'الطلب جاهز للاستلام من المستودع المصدر',
            'data' => $transfer->refresh()->load('items'),
        ]);
    }

    /**
     * The destination confirms what arrived, and the stock lands there.
     *
     * Lines are optional now. They used to be required, and a caller that sent
     * none received nothing at all while the transfer still closed as complete —
     * goods issued from the source and never booked in anywhere. Omitting them
     * now means "everything that was sent", which is both the common case and
     * the safe one.
     */
    public function receive(Request $request, $id)
    {
        $transfer = InventoryTransfer::with('items.product')->findOrFail($id);

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.transfer_item_id' => 'required_with:items|exists:inventory_transfer_items,id',
            'items.*.quantity_received' => 'required_with:items|integer|min:0',
        ]);

        $byItem = [];
        foreach ($validated['items'] ?? [] as $row) {
            $byItem[(int) $row['transfer_item_id']] = (int) $row['quantity_received'];
        }

        try {
            $effects = $this->workflow->receive($transfer, $byItem, auth()->id());
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $effects['discrepancies'] === []
                ? 'تم استلام الطلب بنجاح'
                : 'تم الاستلام مع فروقات في ' . count($effects['discrepancies']) . ' صنف',
            'data' => $transfer->refresh()->load('items'),
            'effects' => $effects,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $transfer = InventoryTransfer::with('items')->findOrFail($id);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $effects = $this->workflow->cancel($transfer, auth()->id(), $validated['reason'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الطلب وأُعيدت الكميات المحجوزة',
            'data' => $transfer->refresh(),
            'effects' => $effects,
        ]);
    }
}
