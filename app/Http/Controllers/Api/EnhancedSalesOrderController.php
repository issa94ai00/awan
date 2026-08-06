<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderChannel;
use App\Models\SalesContract;
use App\Models\SalesOrder;
use App\Services\OrderAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnhancedSalesOrderController extends Controller
{
    protected OrderAllocationService $allocationService;

    public function __construct(OrderAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'channel', 'contract', 'fulfillmentWarehouse', 'items']);

        if ($request->has('channel_id')) {
            $query->byChannel($request->channel_id);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('contract_id')) {
            $query->byContract($request->contract_id);
        }

        if ($request->has('fulfillment_type')) {
            $query->where('fulfillment_type', $request->fulfillment_type);
        }

        if ($request->has('from_date')) {
            $query->where('order_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('order_date', '<=', $request->to_date);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show($id)
    {
        $order = SalesOrder::with([
            'customer',
            'channel',
            'contract',
            'fulfillmentWarehouse',
            'items.product',
            'items.variant',
            'invoices',
            'creator'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'channel_id' => 'nullable|exists:order_channels,id',
            'contract_id' => 'nullable|exists:sales_contracts,id',
            'fulfillment_type' => 'required|in:ship,pickup,delivery',
            'fulfillment_warehouse_id' => 'nullable|exists:warehouses,id',
            'shipping_address' => 'nullable|array',
            'billing_address' => 'nullable|array',
            'shipping_cost' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'coupon_code' => 'nullable|string',
            'customer_notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        try {
            DB::beginTransaction();

            $order = SalesOrder::create([
                'order_number' => 'SO-' . str_pad(SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT),
                'customer_id' => $request->customer_id,
                'channel_id' => $request->channel_id,
                'contract_id' => $request->contract_id,
                'fulfillment_type' => $request->fulfillment_type,
                'fulfillment_warehouse_id' => $request->fulfillment_warehouse_id,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'shipping_cost' => $request->shipping_cost,
                'tax_amount' => $request->tax_amount,
                'discount_amount' => $request->discount_amount,
                'coupon_code' => $request->coupon_code,
                'customer_notes' => $request->customer_notes,
                'internal_notes' => $request->internal_notes,
                'status' => SalesOrder::STATUS_PENDING,
                'order_date' => now(),
                'created_by' => auth()->id(),
            ]);

            $subtotal = 0;
            foreach ($request->items as $item) {
                $orderItem = $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'total' => $item['quantity'] * $item['unit_price'],
                ]);
                $subtotal += $orderItem->total;
            }

            $order->subtotal = $subtotal;
            $order->total = $subtotal + $request->shipping_cost + $request->tax_amount - $request->discount_amount;
            $order->due_amount = $order->total;
            $order->save();

            // Auto-select fulfillment warehouse if not specified
            if (!$order->fulfillment_warehouse_id) {
                $warehouseId = $this->allocationService->selectFulfillmentWarehouse($order);
                if ($warehouseId) {
                    $order->fulfillment_warehouse_id = $warehouseId;
                    $order->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'data' => $order->load('items'),
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

    public function allocateInventory(Request $request, $id)
    {
        $order = SalesOrder::with('items')->findOrFail($id);

        if ($order->status !== SalesOrder::STATUS_PENDING && $order->status !== SalesOrder::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تخصيص المخزون لهذا الطلب',
                'data' => null,
            ], 422);
        }

        try {
            DB::beginTransaction();

            $allocations = $this->allocationService->allocateOrder($order);

            $order->status = SalesOrder::STATUS_PROCESSING;
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تخصيص المخزون بنجاح',
                'data' => $allocations,
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

    public function checkFulfillment(Request $request, $id)
    {
        $order = SalesOrder::with('items')->findOrFail($id);

        $canFulfill = $this->allocationService->canFulfillOrder($order);
        $summary = $this->allocationService->getFulfillmentSummary($order);

        return response()->json([
            'success' => true,
            'data' => [
                'can_fulfill' => $canFulfill,
                'summary' => $summary,
            ],
        ]);
    }

    public function updateTracking(Request $request, $id)
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'carrier' => 'required|string',
        ]);

        $order = SalesOrder::findOrFail($id);

        $order->tracking_number = $request->tracking_number;
        $order->carrier = $request->carrier;
        $order->status = SalesOrder::STATUS_SHIPPED;
        $order->shipped_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات التتبع بنجاح',
            'data' => $order,
        ]);
    }

    public function markAsDelivered(Request $request, $id)
    {
        $order = SalesOrder::findOrFail($id);

        $order->status = SalesOrder::STATUS_DELIVERED;
        $order->actual_delivery_date = now();
        $order->delivered_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تسليم الطلب بنجاح',
            'data' => $order,
        ]);
    }

    public function getChannels(Request $request)
    {
        $query = OrderChannel::query();

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $query->active();
            }
        }

        $channels = $query->get();

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }

    public function getContracts(Request $request)
    {
        $query = SalesContract::with(['customer', 'creator']);

        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->has('status')) {
            match($request->status) {
                'active' => $query->active(),
                'expired' => $query->expired(),
                'draft' => $query->draft(),
                default => $query->where('status', $request->status),
            };
        }

        $contracts = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    public function createContract(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'total_value' => 'required|numeric|min:0',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'terms' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $contract = SalesContract::create([
                'customer_id' => $request->customer_id,
                'contract_number' => 'CTR-' . str_pad(SalesContract::count() + 1, 6, '0', STR_PAD_LEFT),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_value' => $request->total_value,
                'discount_percentage' => $request->discount_percentage,
                'terms' => $request->terms,
                'notes' => $request->notes,
                'status' => SalesContract::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العقد بنجاح',
                'data' => $contract,
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

    public function approveContract(Request $request, $id)
    {
        $contract = SalesContract::findOrFail($id);

        if ($contract->status !== SalesContract::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن الموافقة على هذا العقد',
                'data' => null,
            ], 422);
        }

        $contract->approve(auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'تم الموافقة على العقد بنجاح',
            'data' => $contract,
        ]);
    }

    public function syncOrder(Request $request, $id)
    {
        $order = SalesOrder::findOrFail($id);

        if (!$order->isMultiChannel()) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب ليس من قناة متعددة',
                'data' => null,
            ], 422);
        }

        $order->markAsSynced();

        return response()->json([
            'success' => true,
            'message' => 'تم مزامنة الطلب بنجاح',
            'data' => $order,
        ]);
    }
}
