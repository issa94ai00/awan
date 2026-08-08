<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\WarehouseInventory;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Services\InventoryAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransferController extends Controller
{
    protected InventoryAllocationService $allocationService;
    protected InventoryService $inventory;

    public function __construct(InventoryAllocationService $allocationService, InventoryService $inventory)
    {
        $this->allocationService = $allocationService;
        $this->inventory = $inventory;
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

    public function ship(Request $request, $id)
    {
        $transfer = InventoryTransfer::findOrFail($id);

        if (!$transfer->canShip()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن شحن هذا الطلب',
                'data' => null,
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($transfer->items as $item) {
                // Ship from the source warehouse through the service. Keyed per
                // transfer item so re-shipping is a no-op. The stock moves off
                // the shelf and consumes the reservation taken at request time.
                $this->inventory->shipReserved(
                    $item->product_id,
                    $item->quantity_requested,
                    $transfer->from_warehouse_id,
                    [
                        'key' => 'transfer:' . $transfer->id . ':item:' . $item->id . ':out',
                        'reference' => $transfer->transfer_number,
                        'source' => 'transfer',
                        'reason' => 'شحن مخزون لنقل رقم ' . $transfer->transfer_number,
                        'unit_cost' => $item->unit_cost,
                        'created_by' => auth()->id(),
                    ]
                );

                // No explicit release is needed because the reserved quantity is
                // consumed inside InventoryService when consume_reserved is true.
                $item->quantity_shipped = $item->quantity_requested;
                $item->save();
            }

            $transfer->ship();
            $transfer->shipped_by = auth()->id();
            $transfer->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم شحن الطلب بنجاح',
                'data' => $transfer->load('items'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function receive(Request $request, $id)
    {
        $transfer = InventoryTransfer::findOrFail($id);

        if (!$transfer->canReceive()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن استلام هذا الطلب',
                'data' => null,
            ], 422);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.transfer_item_id' => 'required|exists:inventory_transfer_items,id',
            'items.*.quantity_received' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->items as $itemData) {
                $item = InventoryTransferItem::find($itemData['transfer_item_id']);

                if ($item->transfer_id !== $transfer->id) {
                    continue;
                }

                $quantityReceived = min($itemData['quantity_received'], $item->quantity_shipped);
                $item->quantity_received = $quantityReceived;
                $item->save();

                // Receive into the destination warehouse through the service.
                // Keyed per transfer item so a repeated receive is a no-op and
                // cannot inflate stock at the destination.
                $this->inventory->receive(
                    $item->product_id,
                    $quantityReceived,
                    $transfer->to_warehouse_id,
                    [
                        'key' => 'transfer:' . $transfer->id . ':item:' . $item->id . ':in',
                        'reference' => $transfer->transfer_number,
                        'source' => 'transfer',
                        'reason' => 'استلام مخزون من نقل رقم ' . $transfer->transfer_number,
                        'unit_cost' => $item->unit_cost,
                        'created_by' => auth()->id(),
                    ]
                );
            }

            $transfer->receive();
            $transfer->received_by = auth()->id();
            $transfer->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم استلام الطلب بنجاح',
                'data' => $transfer->load('items'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function cancel($id)
    {
        $transfer = InventoryTransfer::findOrFail($id);

        if (!$transfer->canCancel()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الطلب في هذه الحالة',
                'data' => null,
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Release reserved inventory only for pending transfers.
            foreach ($transfer->items as $item) {
                $this->inventory->release($item->product_id, $item->quantity_requested, $transfer->from_warehouse_id);
            }

            $transfer->status = InventoryTransfer::STATUS_CANCELLED;
            $transfer->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الطلب بنجاح',
                'data' => $transfer,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}
