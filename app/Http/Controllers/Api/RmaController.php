<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RmaItem;
use App\Models\RmaRequest;
use App\Models\SalesOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RmaController extends Controller
{
    public function index(Request $request)
    {
        $query = RmaRequest::with(['customer', 'salesOrder', 'items.product', 'items.variant', 'approver', 'completer']);

        if ($request->filled('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('sales_order_id')) {
            $query->where('sales_order_id', $request->sales_order_id);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->where('requested_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('requested_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rma_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesOrder', function ($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'like', "%{$search}%");
                  });
            });
        }

        $rmaRequests = $query->orderBy('requested_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $rmaRequests,
        ]);
    }

    public function show($id)
    {
        $rmaRequest = RmaRequest::with([
            'customer',
            'salesOrder',
            'salesOrder.items',
            'items.product',
            'items.variant',
            'items.exchangeProduct',
            'items.exchangeVariant',
            'approver',
            'completer'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rmaRequest,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sales_order_id' => 'required|exists:sales_orders,id',
            'reason' => 'required|in:defective,wrong_item,damaged,not_as_described,changed_mind,other',
            'type' => 'required|in:refund,exchange,store_credit',
            'reason_description' => 'nullable|string|max:1000',
            'return_address' => 'nullable|array',
            'return_address.address_line1' => 'required_with:return_address|string',
            'return_address.city' => 'required_with:return_address|string',
            'return_address.country' => 'required_with:return_address|string',
            'return_address.postal_code' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity_requested' => 'required|integer|min:1|max:999',
            'items.*.condition' => 'required|in:new,used,damaged,missing',
            'items.*.resolution' => 'required|in:refund,exchange,repair,discard',
            'items.*.exchange_product_id' => 'nullable|exists:products,id|required_if:items.*.resolution,exchange',
            'items.*.exchange_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.notes' => 'nullable|string|max:500',
        ], [
            'customer_id.required' => 'حقل العميل مطلوب',
            'customer_id.exists' => 'العميل المحدد غير موجود',
            'sales_order_id.required' => 'حقل الطلب مطلوب',
            'sales_order_id.exists' => 'الطلب المحدد غير موجود',
            'reason.required' => 'حقل سبب الإرجاع مطلوب',
            'type.required' => 'حقل نوع الإرجاع مطلوب',
            'items.required' => 'يجب إضافة منتج واحد على الأقل',
            'items.min' => 'يجب إضافة منتج واحد على الأقل',
            'items.*.quantity_requested.max' => 'الكمية المطلوبة لا يمكن أن تتجاوز 999',
            'items.*.exchange_product_id.required_if' => 'يجب تحديد المنتج البديل عند اختيار التبديل',
        ]);

        try {
            DB::beginTransaction();

            // Check if sales order belongs to the customer
            $salesOrder = \App\Models\SalesOrder::findOrFail($request->sales_order_id);
            if ($salesOrder->customer_id != $request->customer_id) {
                throw new \Exception('الطلب لا ينتمي للعميل المحدد');
            }

            // Check if sales order is delivered
            if ($salesOrder->status !== 'delivered') {
                throw new \Exception('لا يمكن إنشاء طلب إرجاع لطلب لم يتم تسليمه');
            }

            // Check that the returned quantities do not exceed original purchased quantities
            foreach ($request->items as $item) {
                $orderItem = SalesOrderItem::findOrFail($item['sales_order_item_id']);

                // Sum previously requested quantities for this order item in active requests
                $previouslyRequested = RmaItem::where('sales_order_item_id', $item['sales_order_item_id'])
                    ->whereHas('rmaRequest', function($query) {
                        $query->whereNotIn('status', ['rejected', 'cancelled']);
                    })
                    ->sum('quantity_requested');

                $totalRequested = $previouslyRequested + $item['quantity_requested'];
                if ($totalRequested > $orderItem->quantity) {
                    $availableToReturn = $orderItem->quantity - $previouslyRequested;
                    throw new \Exception("الكمية المطلوبة ({$item['quantity_requested']}) تتجاوز الكمية المتاحة للإرجاع ({$availableToReturn}) للمنتج: " . ($orderItem->product ? $orderItem->product->name : ''));
                }
            }

            // Generate unique RMA number using database lock
            $rmaNumber = DB::transaction(function() {
                $lastRma = RmaRequest::orderBy('id', 'desc')->lockForUpdate()->first();
                $nextId = $lastRma ? $lastRma->id + 1 : 1;
                return 'RMA-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            });

            $rmaRequest = RmaRequest::create([
                'customer_id' => $request->customer_id,
                'sales_order_id' => $request->sales_order_id,
                'rma_number' => $rmaNumber,
                'reason' => $request->reason,
                'type' => $request->type,
                'status' => RmaRequest::STATUS_PENDING,
                'reason_description' => $request->reason_description,
                'return_address' => $request->return_address,
                'requested_at' => now(),
            ]);

            $totalRefundAmount = 0;
            foreach ($request->items as $item) {
                $orderItem = SalesOrderItem::findOrFail($item['sales_order_item_id']);

                $rmaItem = $rmaRequest->items()->create([
                    'sales_order_item_id' => $item['sales_order_item_id'],
                    'product_id' => $orderItem->product_id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'quantity_requested' => $item['quantity_requested'],
                    'quantity_received' => 0,
                    'condition' => $item['condition'],
                    'resolution' => $item['resolution'],
                    'exchange_product_id' => $item['exchange_product_id'] ?? null,
                    'exchange_variant_id' => $item['exchange_variant_id'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);

                // Calculate refund amount based on condition
                $originalPrice = $orderItem->unit_price;
                $refundAmount = $rmaItem->calculateRefundAmount($originalPrice);
                $rmaItem->refund_amount = $refundAmount;
                $rmaItem->save();
                $totalRefundAmount += $refundAmount;
            }

            // Update total refund amount on RMA request
            $rmaRequest->refund_amount = $totalRefundAmount;
            $rmaRequest->save();

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'create_rma',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تم إنشاء طلب إرجاع جديد بالرقم {$rmaRequest->rma_number}",
                null,
                $rmaRequest->toArray(),
                'rma'
            );

            DB::commit();

            Log::info('RMA Request Created', ['rma_id' => $rmaRequest->id, 'rma_number' => $rmaRequest->rma_number]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الإرجاع بنجاح',
                'data' => $rmaRequest->load('items'),
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
                'data' => null,
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RMA Request Creation Failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function approve(Request $request, $id)
    {
        $rmaRequest = RmaRequest::with('items')->findOrFail($id);

        if (!$rmaRequest->canApprove()) {
            Log::warning('RMA Approval Failed - Invalid Status', ['rma_id' => $id, 'status' => $rmaRequest->status]);
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن الموافقة على هذا الطلب. الحالة الحالية: ' . $rmaRequest->status_text,
                'data' => null,
            ], 422);
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $rmaRequest->approve(auth()->id());
            if ($request->admin_notes) {
                $rmaRequest->admin_notes = $request->admin_notes;
                $rmaRequest->save();
            }

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'approve_rma',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تمت الموافقة على طلب الإرجاع {$rmaRequest->rma_number}",
                null,
                ['admin_notes' => $request->admin_notes],
                'rma'
            );

            DB::commit();

            Log::info('RMA Request Approved', ['rma_id' => $rmaRequest->id, 'approved_by' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'تم الموافقة على طلب الإرجاع بنجاح',
                'data' => $rmaRequest->load('items'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RMA Approval Failed', ['rma_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function reject(Request $request, $id)
    {
        $rmaRequest = RmaRequest::findOrFail($id);

        if (!$rmaRequest->canReject()) {
            Log::warning('RMA Rejection Failed - Invalid Status', ['rma_id' => $id, 'status' => $rmaRequest->status]);
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفض هذا الطلب. الحالة الحالية: ' . $rmaRequest->status_text,
                'data' => null,
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ], [
            'reason.required' => 'يجب إدخال سبب الرفض',
            'reason.max' => 'سبب الرفض لا يمكن أن يتجاوز 1000 حرف',
        ]);

        try {
            DB::beginTransaction();
            $rmaRequest->reject(auth()->id(), $request->reason);

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'reject_rma',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تم رفض طلب الإرجاع {$rmaRequest->rma_number} بسبب: {$request->reason}",
                null,
                ['reject_reason' => $request->reason],
                'rma'
            );

            DB::commit();

            Log::info('RMA Request Rejected', ['rma_id' => $rmaRequest->id, 'rejected_by' => auth()->id(), 'reason' => $request->reason]);

            return response()->json([
                'success' => true,
                'message' => 'تم رفض طلب الإرجاع بنجاح',
                'data' => $rmaRequest,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RMA Rejection Failed', ['rma_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function receiveItems(Request $request, $id)
    {
        $rmaRequest = RmaRequest::with(['items', 'salesOrder'])->findOrFail($id);

        if ($rmaRequest->status !== RmaRequest::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن استلام المنتجات لهذا الطلب. يجب أن يكون الطلب موافق عليه أولاً.',
                'data' => null,
            ], 422);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.rma_item_id' => 'required|exists:rma_items,id',
            'items.*.quantity_received' => 'required|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'bin_id' => 'nullable|exists:warehouse_bins,id',
        ]);

        try {
            DB::beginTransaction();

            $warehouseId = $request->warehouse_id ?? $rmaRequest->salesOrder->fulfillment_warehouse_id ?? (\App\Models\Warehouse::first()?->id);
            if (!$warehouseId) {
                throw new \Exception('يجب تحديد مستودع لاستلام المنتجات المرتجعة');
            }

            $binId = $request->bin_id ?? (\App\Models\WarehouseBin::where('warehouse_id', $warehouseId)->where('status', 'active')->first()?->id ?? \App\Models\WarehouseBin::where('warehouse_id', $warehouseId)->first()?->id);

            foreach ($request->items as $item) {
                $rmaItem = RmaItem::findOrFail($item['rma_item_id']);

                if ($rmaItem->rma_request_id !== $rmaRequest->id) {
                    continue;
                }

                $rmaItem->markAsReceived($item['quantity_received']);

                if ($item['quantity_received'] > 0) {
                    // Find or create Warehouse Inventory record
                    $inventory = \App\Models\WarehouseInventory::firstOrCreate([
                        'warehouse_id' => $warehouseId,
                        'product_id' => $rmaItem->product_id,
                        'product_variant_id' => $rmaItem->product_variant_id,
                        'bin_id' => $binId,
                    ], [
                        'quantity' => 0,
                        'available_quantity' => 0,
                        'damaged_quantity' => 0,
                        'quarantined_quantity' => 0,
                        'cost_basis' => 'FIFO',
                    ]);

                    // Adjust quantities depending on condition
                    $condition = $rmaItem->condition;
                    if ($condition === RmaItem::CONDITION_NEW) {
                        $inventory->increment('quantity', $item['quantity_received']);
                        $inventory->increment('available_quantity', $item['quantity_received']);

                        // Create Stock Movement (boots will auto-increment product->stock_quantity)
                        \App\Models\StockMovement::create([
                            'product_id' => $rmaItem->product_id,
                            'movement_type' => \App\Models\StockMovement::TYPE_IN,
                            'quantity' => $item['quantity_received'],
                            'reference' => $rmaRequest->rma_number,
                            'source' => 'rma',
                            'notes' => 'مرتجع سليم من العميل لطلب ' . ($rmaRequest->salesOrder?->order_number ?? ''),
                            'warehouse_id' => $warehouseId,
                            'created_by' => auth()->id(),
                        ]);
                    } elseif ($condition === RmaItem::CONDITION_DAMAGED) {
                        $inventory->increment('quantity', $item['quantity_received']);
                        $inventory->increment('damaged_quantity', $item['quantity_received']);

                        \App\Models\StockMovement::create([
                            'product_id' => $rmaItem->product_id,
                            'movement_type' => \App\Models\StockMovement::TYPE_ADJUSTMENT,
                            'quantity' => $item['quantity_received'],
                            'reference' => $rmaRequest->rma_number,
                            'source' => 'rma',
                            'notes' => 'مرتجع تالف من العميل لطلب ' . ($rmaRequest->salesOrder?->order_number ?? ''),
                            'warehouse_id' => $warehouseId,
                            'created_by' => auth()->id(),
                        ]);
                    } else { // used
                        $inventory->increment('quantity', $item['quantity_received']);
                        $inventory->increment('quarantined_quantity', $item['quantity_received']);

                        \App\Models\StockMovement::create([
                            'product_id' => $rmaItem->product_id,
                            'movement_type' => \App\Models\StockMovement::TYPE_ADJUSTMENT,
                            'quantity' => $item['quantity_received'],
                            'reference' => $rmaRequest->rma_number,
                            'source' => 'rma',
                            'notes' => 'مرتجع مستعمل (قيد المعاينة) من العميل لطلب ' . ($rmaRequest->salesOrder?->order_number ?? ''),
                            'warehouse_id' => $warehouseId,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'receive_items',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تم استلام المنتجات المرتجعة لطلب الإرجاع {$rmaRequest->rma_number}",
                null,
                ['received_items' => $request->items],
                'rma'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم استلام المنتجات وتحديث المخزون بنجاح',
                'data' => $rmaRequest->load('items'),
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

    public function complete(Request $request, $id)
    {
        $rmaRequest = RmaRequest::with(['items', 'customer', 'salesOrder'])->findOrFail($id);

        if (!$rmaRequest->canComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إكمال هذا الطلب',
                'data' => null,
            ], 422);
        }

        $request->validate([
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|string',
            'admin_notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $totalRefund = $rmaRequest->getTotalRefundAmountAttribute();
            $refundAmount = $request->refund_amount ?? $totalRefund;
            $refundMethod = $request->refund_method ?? $rmaRequest->refund_method ?? 'store_credit';

            $rmaRequest->complete(auth()->id(), $refundAmount);

            $rmaRequest->refund_method = $refundMethod;

            if ($request->admin_notes) {
                $rmaRequest->admin_notes = $request->admin_notes;
            }

            $rmaRequest->save();

            // Financial Adjustment: Store Credit decreases customer balance (debt)
            if ($refundMethod === 'store_credit' && $rmaRequest->customer) {
                $rmaRequest->customer->updateBalance(-$refundAmount);
            }

            // Generate replacement SalesOrder if any items are exchange resolution
            $exchangeItems = $rmaRequest->items()->where('resolution', 'exchange')->get();
            if ($exchangeItems->isNotEmpty()) {
                $rplOrderNumber = 'SO-RPL-' . str_pad(\App\Models\SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT);
                $replacementOrder = \App\Models\SalesOrder::create([
                    'order_number' => $rplOrderNumber,
                    'customer_id' => $rmaRequest->customer_id,
                    'status' => \App\Models\SalesOrder::STATUS_PENDING,
                    'order_date' => now(),
                    'subtotal' => 0.00,
                    'tax' => 0.00,
                    'discount' => 0.00,
                    'total' => 0.00,
                    'created_by' => auth()->id(),
                    'notes' => 'طلب بديل تلقائي لطلب الإرجاع: ' . $rmaRequest->rma_number,
                    'fulfillment_warehouse_id' => $rmaRequest->salesOrder?->fulfillment_warehouse_id ?? \App\Models\Warehouse::first()?->id,
                    'shipping_address' => $rmaRequest->salesOrder?->shipping_address,
                    'billing_address' => $rmaRequest->salesOrder?->billing_address,
                ]);

                foreach ($exchangeItems as $item) {
                    $replacementOrder->items()->create([
                        'product_id' => $item->exchange_product_id,
                        'product_variant_id' => $item->exchange_variant_id,
                        'quantity' => $item->quantity_requested,
                        'unit_price' => 0.00,
                        'discount' => 0.00,
                        'tax' => 0.00,
                    ]);
                }
            }

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'complete_rma',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تم إكمال طلب الإرجاع {$rmaRequest->rma_number} بطريقة استرداد {$refundMethod} وقيمة {$refundAmount}",
                null,
                ['refund_amount' => $refundAmount, 'refund_method' => $refundMethod],
                'rma'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إكمال طلب الإرجاع بنجاح وتحديث الحسابات والمخازن',
                'data' => $rmaRequest->load('items'),
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
        $rmaRequest = RmaRequest::findOrFail($id);

        if ($rmaRequest->status === RmaRequest::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء طلب مكتمل',
                'data' => null,
            ], 422);
        }

        try {
            DB::beginTransaction();

            $rmaRequest->cancel();

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'cancel_rma',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تم إلغاء طلب الإرجاع {$rmaRequest->rma_number}",
                null,
                null,
                'rma'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الإرجاع بنجاح',
                'data' => $rmaRequest,
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

    public function getStatistics(Request $request)
    {
        $query = RmaRequest::query();

        if ($request->has('from_date')) {
            $query->where('requested_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('requested_at', '<=', $request->to_date);
        }

        $stats = [
            'total_requests' => $query->count(),
            'pending' => (clone $query)->pending()->count(),
            'approved' => (clone $query)->approved()->count(),
            'rejected' => (clone $query)->where('status', RmaRequest::STATUS_REJECTED)->count(),
            'completed' => (clone $query)->where('status', RmaRequest::STATUS_COMPLETED)->count(),
            'total_refund_amount' => (clone $query)->where('status', RmaRequest::STATUS_COMPLETED)->sum('refund_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function getCustomersWithOrders(Request $request)
    {
        // Debug: Log all customers with their orders
        $allCustomers = \App\Models\Customer::with('salesOrders')->get();
        \Log::info('All customers count: ' . $allCustomers->count());
        foreach ($allCustomers as $customer) {
            \Log::info("Customer: {$customer->name}, Orders count: {$customer->salesOrders->count()}");
            foreach ($customer->salesOrders as $order) {
                \Log::info("  Order #{$order->order_number}, Status: {$order->status}");
            }
        }

        $query = \App\Models\Customer::withCount(['salesOrders as delivered_orders_count' => function ($query) {
            $query->where('status', 'delivered');
        }])->whereHas('salesOrders', function ($query) {
            $query->where('status', 'delivered');
        });

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name', 'asc')
            ->paginate($request->per_page ?? 50);

        \Log::info('Filtered customers with delivered orders count: ' . $customers->count());

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    public function getItems($rmaRequestId)
    {
        $items = RmaItem::with(['product', 'variant', 'exchangeProduct', 'exchangeVariant'])
            ->where('rma_request_id', $rmaRequestId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function updateItem(Request $request, $id)
    {
        $rmaItem = RmaItem::findOrFail($id);

        $request->validate([
            'quantity_received' => 'nullable|integer|min:0',
            'condition' => 'nullable|in:new,used,damaged,missing',
            'resolution' => 'nullable|in:refund,exchange,repair,discard',
            'notes' => 'nullable|string',
        ]);

        $rmaItem->update($request->only([
            'quantity_received',
            'condition',
            'resolution',
            'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بند الإرجاع بنجاح',
            'data' => $rmaItem->load(['product', 'variant']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $rmaRequest = RmaRequest::with('items')->findOrFail($id);

        // Only allow updates for pending requests
        if ($rmaRequest->status !== RmaRequest::STATUS_PENDING) {
            Log::warning('RMA Update Failed - Invalid Status', ['rma_id' => $id, 'status' => $rmaRequest->status]);
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تحديث طلب الإرجاع. الحالة الحالية: ' . $rmaRequest->status_text,
                'data' => null,
            ], 422);
        }

        $request->validate([
            'reason_description' => 'nullable|string|max:1000',
            'return_address' => 'nullable|array',
            'return_address.address_line1' => 'required_with:return_address|string',
            'return_address.city' => 'required_with:return_address|string',
            'return_address.country' => 'required_with:return_address|string',
            'return_address.postal_code' => 'nullable|string',
            'admin_notes' => 'nullable|string|max:1000',
            'refund_method' => 'nullable|in:original,store_credit,bank_transfer,check',
            'items' => 'nullable|array',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity_requested' => 'required|integer|min:1|max:999',
            'items.*.condition' => 'required|in:new,used,damaged,missing',
            'items.*.resolution' => 'required|in:refund,exchange,repair,discard',
            'items.*.exchange_product_id' => 'nullable|exists:products,id|required_if:items.*.resolution,exchange',
            'items.*.exchange_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Update basic fields
            $rmaRequest->update($request->only([
                'reason_description',
                'return_address',
                'admin_notes',
                'refund_method'
            ]));

            // Update items if provided
            if ($request->has('items')) {
                // Delete existing items
                $rmaRequest->items()->delete();

                $totalRefundAmount = 0;
                foreach ($request->items as $item) {
                    $orderItem = SalesOrderItem::findOrFail($item['sales_order_item_id']);

                    // Sum previously requested quantities for this order item in other active requests
                    $previouslyRequested = RmaItem::where('sales_order_item_id', $item['sales_order_item_id'])
                        ->whereHas('rmaRequest', function($query) use ($id) {
                            $query->whereNotIn('status', ['rejected', 'cancelled'])
                                ->where('id', '!=', $id);
                        })
                        ->sum('quantity_requested');

                    $totalRequested = $previouslyRequested + $item['quantity_requested'];
                    if ($totalRequested > $orderItem->quantity) {
                        $availableToReturn = $orderItem->quantity - $previouslyRequested;
                        throw new \Exception("الكمية المطلوبة ({$item['quantity_requested']}) تتجاوز الكمية المتاحة للإرجاع ({$availableToReturn}) للمنتج: " . ($orderItem->product ? $orderItem->product->name : ''));
                    }

                    $rmaItem = $rmaRequest->items()->create([
                        'sales_order_item_id' => $item['sales_order_item_id'],
                        'product_id' => $orderItem->product_id,
                        'product_variant_id' => $orderItem->product_variant_id,
                        'quantity_requested' => $item['quantity_requested'],
                        'quantity_received' => 0,
                        'condition' => $item['condition'],
                        'resolution' => $item['resolution'],
                        'exchange_product_id' => $item['exchange_product_id'] ?? null,
                        'exchange_variant_id' => $item['exchange_variant_id'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);

                    $originalPrice = $orderItem->unit_price;
                    $refundAmount = $rmaItem->calculateRefundAmount($originalPrice);
                    $rmaItem->refund_amount = $refundAmount;
                    $rmaItem->save();
                    $totalRefundAmount += $refundAmount;
                }

                $rmaRequest->refund_amount = $totalRefundAmount;
                $rmaRequest->save();
            }

            // Log action in AuditLog
            $auditService = app(\App\Services\AuditService::class);
            $auditService->log(
                'update_rma',
                get_class($rmaRequest),
                $rmaRequest->id,
                "تم تحديث طلب الإرجاع {$rmaRequest->rma_number}",
                null,
                $rmaRequest->toArray(),
                'rma'
            );

            DB::commit();

            Log::info('RMA Request Updated', ['rma_id' => $rmaRequest->id, 'updated_by' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث طلب الإرجاع بنجاح',
                'data' => $rmaRequest->load('items'),
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
                'data' => null,
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RMA Update Failed', ['rma_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function destroy($id)
    {
        $rmaRequest = RmaRequest::findOrFail($id);

        if ($rmaRequest->status === RmaRequest::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف طلب مكتمل',
                'data' => null,
            ], 422);
        }

        $rmaRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف طلب الإرجاع بنجاح',
            'data' => null,
        ]);
    }

    public function getActivity($id)
    {
        $activities = \App\Models\AuditLog::with('user')
            ->where('entity_type', \App\Models\RmaRequest::class)
            ->where('entity_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action_text,
                    'description' => $log->description,
                    'created_at' => $log->created_at->toDateTimeString(),
                    'user' => $log->user ? $log->user->name : 'النظام',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }
}
