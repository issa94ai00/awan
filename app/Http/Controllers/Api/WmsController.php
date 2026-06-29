<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseBin;
use App\Models\Warehouse;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\PackingList;
use App\Models\PackingListItem;
use App\Models\ShippingManifest;
use App\Models\ShippingManifestItem;
use App\Models\CycleCount;
use App\Models\CycleCountItem;
use App\Models\SalesOrder;
use App\Services\PickingService;
use App\Services\PackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WmsController extends Controller
{
    protected PickingService $pickingService;
    protected PackingService $packingService;

    public function __construct(PickingService $pickingService, PackingService $packingService)
    {
        $this->pickingService = $pickingService;
        $this->packingService = $packingService;
    }

    // ==================== Warehouse Bins ====================

    public function indexBins(Request $request)
    {
        $query = WarehouseBin::with(['warehouse', 'inventory.product']);

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->zone) {
            $query->byZone($request->zone);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->active_only) {
            $query->active();
        }

        return response()->json($query->paginate(20));
    }

    public function showBin($id)
    {
        $bin = WarehouseBin::with(['warehouse', 'inventory.product', 'inventory.productVariant'])
            ->findOrFail($id);

        return response()->json($bin);
    }

    public function storeBin(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'code' => 'required|string|unique:warehouse_bins,code',
            'name' => 'required|string',
            'zone' => 'nullable|string',
            'aisle' => 'nullable|string',
            'shelf' => 'nullable|string',
            'level' => 'nullable|string',
            'type' => 'required|in:storage,picking,receiving,shipping,quarantine,returns',
            'capacity_type' => 'required|in:volume,weight,count',
            'capacity_value' => 'nullable|numeric',
            'is_active' => 'boolean',
            'requires_equipment' => 'boolean',
            'dimensions' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $bin = WarehouseBin::create($validated);

        return response()->json($bin, 201);
    }

    public function updateBin(Request $request, $id)
    {
        $bin = WarehouseBin::findOrFail($id);

        $validated = $request->validate([
            'code' => 'string|unique:warehouse_bins,code,' . $id,
            'name' => 'string',
            'zone' => 'nullable|string',
            'aisle' => 'nullable|string',
            'shelf' => 'nullable|string',
            'level' => 'nullable|string',
            'type' => 'in:storage,picking,receiving,shipping,quarantine,returns',
            'capacity_type' => 'in:volume,weight,count',
            'capacity_value' => 'nullable|numeric',
            'is_active' => 'boolean',
            'requires_equipment' => 'boolean',
            'dimensions' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $bin->update($validated);

        return response()->json($bin);
    }

    public function destroyBin($id)
    {
        $bin = WarehouseBin::findOrFail($id);

        if ($bin->inventory()->count() > 0) {
            return response()->json(['message' => 'Cannot delete bin with inventory'], 400);
        }

        $bin->delete();

        return response()->json(['message' => 'Bin deleted successfully']);
    }

    // ==================== Picking Lists ====================

    public function indexPickingLists(Request $request)
    {
        $query = PickingList::with(['warehouse', 'salesOrder.customer', 'picker', 'items.product', 'items.bin']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->picker_id) {
            $query->byPicker($request->picker_id);
        }

        return response()->json($query->paginate(20));
    }

    public function showPickingList($id)
    {
        $list = PickingList::with(['warehouse', 'salesOrder.customer', 'picker', 'items.product', 'items.bin', 'items.productVariant'])
            ->findOrFail($id);

        return response()->json($list);
    }

    public function createPickingList(Request $request)
    {
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $order = SalesOrder::findOrFail($validated['sales_order_id']);

        try {
            $pickingList = $this->pickingService->createPickingList($order, $validated['warehouse_id'] ?? null);
            $pickingList->load(['warehouse', 'salesOrder', 'items.product', 'items.bin']);

            return response()->json($pickingList, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function startPicking(Request $request, $id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->startPicking($list, $request->user()->id);
            return response()->json(['message' => 'Picking started']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function pickItem(Request $request, $itemId)
    {
        $item = PickingListItem::findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'verified' => 'boolean',
        ]);

        try {
            $this->pickingService->pickItem($item, $validated['quantity'], $validated['verified'] ?? false);
            return response()->json(['message' => 'Item picked successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function completePicking($id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->completePicking($list);
            return response()->json(['message' => 'Picking completed']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function cancelPicking($id)
    {
        $list = PickingList::findOrFail($id);

        try {
            $this->pickingService->cancelPicking($list);
            return response()->json(['message' => 'Picking cancelled']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getPickingStatistics(Request $request)
    {
        $stats = $this->pickingService->getPickingStatistics(
            $request->warehouse_id,
            $request->from_date ? now()->parse($request->from_date) : null,
            $request->to_date ? now()->parse($request->to_date) : null
        );

        return response()->json($stats);
    }

    // ==================== Packing Lists ====================

    public function indexPackingLists(Request $request)
    {
        $query = PackingList::with(['warehouse', 'pickingList', 'salesOrder.customer', 'packer', 'items.product']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->packer_id) {
            $query->byPacker($request->packer_id);
        }

        return response()->json($query->paginate(20));
    }

    public function showPackingList($id)
    {
        $list = PackingList::with(['warehouse', 'pickingList', 'salesOrder.customer', 'packer', 'items.product', 'items.productVariant'])
            ->findOrFail($id);

        return response()->json($list);
    }

    public function createPackingList(Request $request)
    {
        $validated = $request->validate([
            'picking_list_id' => 'required|exists:picking_lists,id',
        ]);

        $pickingList = PickingList::findOrFail($validated['picking_list_id']);

        try {
            $packingList = $this->packingService->createPackingList($pickingList);
            $packingList->load(['warehouse', 'pickingList', 'salesOrder', 'items.product']);

            return response()->json($packingList, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function startPacking(Request $request, $id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->startPacking($list, $request->user()->id);
            return response()->json(['message' => 'Packing started']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function updatePackageDetails(Request $request, $itemId)
    {
        $item = PackingListItem::findOrFail($itemId);

        $validated = $request->validate([
            'package_number' => 'nullable|string',
            'dimensions' => 'nullable|array',
            'weight' => 'nullable|numeric',
            'fragile' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->packingService->updatePackageDetails($item, $validated);
            return response()->json(['message' => 'Package details updated']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function completePacking($id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->completePacking($list);
            return response()->json(['message' => 'Packing completed']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function cancelPacking($id)
    {
        $list = PackingList::findOrFail($id);

        try {
            $this->packingService->cancelPacking($list);
            return response()->json(['message' => 'Packing cancelled']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getPackingLabels($id)
    {
        $list = PackingList::findOrFail($id);
        $labels = $this->packingService->generatePackingLabels($list);

        return response()->json($labels);
    }

    public function validatePacking($id)
    {
        $list = PackingList::findOrFail($id);
        $validation = $this->packingService->validatePacking($list);

        return response()->json($validation);
    }

    public function getPackingStatistics(Request $request)
    {
        $stats = $this->packingService->getPackingStatistics(
            $request->warehouse_id,
            $request->from_date ? now()->parse($request->from_date) : null,
            $request->to_date ? now()->parse($request->to_date) : null
        );

        return response()->json($stats);
    }

    // ==================== Shipping Manifests ====================

    public function indexShippingManifests(Request $request)
    {
        $query = ShippingManifest::with(['warehouse', 'carrier', 'driver', 'items.salesOrder']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->carrier_id) {
            $query->byCarrier($request->carrier_id);
        }

        return response()->json($query->paginate(20));
    }

    public function showShippingManifest($id)
    {
        $manifest = ShippingManifest::with(['warehouse', 'carrier', 'driver', 'items.salesOrder', 'items.packingList'])
            ->findOrFail($id);

        return response()->json($manifest);
    }

    public function createShippingManifest(Request $request)
    {
        $validated = $request->validate([
            'packing_list_ids' => 'required|array',
            'packing_list_ids.*' => 'exists:packing_lists,id',
            'carrier_id' => 'nullable|exists:carriers,id',
            'carrier_name' => 'nullable|string',
        ]);

        try {
            $manifest = $this->packingService->createShippingManifest(
                $validated['packing_list_ids'],
                $validated['carrier_id'] ?? null,
                $validated['carrier_name'] ?? null
            );

            return response()->json($manifest, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function dispatchManifest($id)
    {
        $manifest = ShippingManifest::findOrFail($id);

        try {
            $this->packingService->dispatchManifest($manifest);
            return response()->json(['message' => 'Manifest dispatched']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function markItemDelivered(Request $request, $itemId)
    {
        $item = ShippingManifestItem::findOrFail($itemId);

        try {
            $this->packingService->markItemDelivered($item, $request->signature ?? null);
            return response()->json(['message' => 'Item marked as delivered']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // ==================== Cycle Counts ====================

    public function indexCycleCounts(Request $request)
    {
        $query = CycleCount::with(['warehouse', 'bin', 'counter', 'reviewer', 'items.product']);

        if ($request->warehouse_id) {
            $query->byWarehouse($request->warehouse_id);
        }

        if ($request->bin_id) {
            $query->byBin($request->bin_id);
        }

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        return response()->json($query->paginate(20));
    }

    public function showCycleCount($id)
    {
        $count = CycleCount::with(['warehouse', 'bin', 'counter', 'reviewer', 'items.product', 'items.productVariant', 'items.bin'])
            ->findOrFail($id);

        return response()->json($count);
    }

    public function storeCycleCount(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
            'type' => 'required|in:full,partial,abc,blind',
            'notes' => 'nullable|string',
        ]);

        $count = CycleCount::create([
            'warehouse_id' => $validated['warehouse_id'],
            'bin_id' => $validated['bin_id'],
            'count_number' => 'CC-' . str_pad(CycleCount::count() + 1, 6, '0', STR_PAD_LEFT),
            'type' => $validated['type'],
            'status' => CycleCount::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($count, 201);
    }

    public function startCycleCount(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if (!$count->canStart()) {
            return response()->json(['message' => 'Cycle count cannot be started'], 400);
        }

        $count->start($request->user()->id);

        return response()->json(['message' => 'Cycle count started']);
    }

    public function addCycleCountItem(Request $request, $countId)
    {
        $count = CycleCount::findOrFail($countId);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
            'expected_quantity' => 'required|integer|min:0',
            'counted_quantity' => 'required|integer|min:0',
            'unit_cost' => 'nullable|numeric',
            'variance_reason' => 'nullable|in:theft,damage,data_entry,unknown',
            'notes' => 'nullable|string',
        ]);

        $item = $count->items()->create($validated);
        $item->calculateVariance();

        return response()->json($item, 201);
    }

    public function completeCycleCount($id)
    {
        $count = CycleCount::findOrFail($id);

        if ($count->status !== CycleCount::STATUS_IN_PROGRESS) {
            return response()->json(['message' => 'Cycle count is not in progress'], 400);
        }

        $count->complete();
        $count->calculateVariance();

        return response()->json(['message' => 'Cycle count completed']);
    }

    public function reviewCycleCount(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if ($count->status !== CycleCount::STATUS_COMPLETED) {
            return response()->json(['message' => 'Cycle count must be completed before review'], 400);
        }

        $count->review($request->user()->id);

        return response()->json(['message' => 'Cycle count reviewed']);
    }

    public function applyAdjustment(Request $request, $id)
    {
        $count = CycleCount::findOrFail($id);

        if (!$count->requires_adjustment) {
            return response()->json(['message' => 'No adjustment required'], 400);
        }

        $count->applyAdjustment($request->user()->id);

        return response()->json(['message' => 'Adjustment applied']);
    }

    public function cancelCycleCount($id)
    {
        $count = CycleCount::findOrFail($id);
        $count->cancel();

        return response()->json(['message' => 'Cycle count cancelled']);
    }

    // ==================== Warehouses CRUD ====================

    public function indexWarehouses(Request $request)
    {
        $query = Warehouse::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $warehouses = $query->latest()->paginate($request->input('per_page', 20));
        return response()->json($warehouses);
    }

    public function showWarehouse($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        return response()->json($warehouse);
    }

    public function storeWarehouse(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:255',
            'location_type' => 'required|in:warehouse,branch,distribution_center,3pl',
            'capacity' => 'nullable|numeric',
            'is_active' => 'sometimes|boolean',
            'is_primary' => 'sometimes|boolean',
        ]);

        $warehouse = Warehouse::create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
            'is_primary' => $validated['is_primary'] ?? false,
        ]));

        return response()->json($warehouse, 201);
    }

    public function updateWarehouse(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:50|unique:warehouses,code,' . $id,
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:255',
            'location_type' => 'in:warehouse,branch,distribution_center,3pl',
            'capacity' => 'nullable|numeric',
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ]);

        $warehouse->update($validated);

        return response()->json($warehouse);
    }

    public function destroyWarehouse($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
        ]);
    }

    public function getWmsStats()
    {
        return response()->json([
            'warehouses' => Warehouse::count(),
            'bins' => WarehouseBin::count(),
            'pickingLists' => PickingList::count(),
            'packingLists' => PackingList::count(),
            'pickingPending' => PickingList::where('status', 'pending')->count(),
            'packingPending' => PackingList::where('status', 'pending')->count(),
            'cycleCounts' => CycleCount::count(),
        ]);
    }
}

