<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderItemAllocation;
use App\Models\SalesOrderStatusHistory;
use App\Services\OrderAllocationService;
use App\Services\Sales\SalesOrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private SalesOrderWorkflowService $workflow
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            // The rep who raised the order from the mobile app. Optional: a
            // walk-in shopper checking out on their own sends no employee.
            'employee_id' => 'nullable|integer|exists:employees,id',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'required_without:items.*.product_id|nullable|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'items.*.allocations.*.quantity' => 'required|integer|min:1',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود',
            'items.*.product_name.required_without' => 'اسم المنتج مطلوب',
            'items.*.quantity.required_with' => 'الكمية مطلوبة',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.allocations.*.warehouse_id.exists' => 'أحد المستودعات المحددة غير موجود',
            'items.*.allocations.*.quantity.min' => 'كمية التخصيص يجب أن تكون 1 على الأقل',
        ]);

        $customer = Customer::where('phone', $validated['phone'])->first();
        if (! $customer && ! empty($validated['email'])) {
            $customer = Customer::where('email', $validated['email'])->first();
        }

        if ($customer) {
            $customer->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? $customer->email,
                'address' => $validated['address'] ?? $customer->address,
            ]);
        } else {
            $customer = Customer::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'source' => 'purchase_request',
                'status' => 'active',
            ]);
        }

        // The rep who took the sale owns both the order and, from now on, the
        // customer: the order used to arrive with nobody named on it, so it
        // never showed up under anyone's work and the customer stayed
        // unassigned no matter how many orders that rep placed for them.
        $employeeId = $validated['employee_id'] ?? null;
        if ($employeeId !== null) {
            $customer->assignEmployee($employeeId);
        }

        $subtotal = 0;
        $itemsData = [];
        if (! empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $unitPrice = 0;
                $itemTotal = 0;
                $productId = null;

                if (! empty($item['product_id'])) {
                    $product = Product::find($item['product_id']);
                } else {
                    $product = Product::where('name_ar', $item['product_name'])
                        ->orWhere('name_en', $item['product_name'])
                        ->orWhere('sku', $item['product_name'])
                        ->first();
                }

                if ($product) {
                    $productId = $product->id;
                    $unitPrice = $product->price ?? 0;
                    $itemTotal = $unitPrice * $item['quantity'];
                }

                // The storefront cart plans its warehouse split up front and
                // sends it along; when the plan is absent (older clients, or a
                // quick checkout) the order stays a draft with no allocations.
                $allocations = $item['allocations'] ?? [];

                if ($allocations !== []) {
                    $this->assertAllocationsSum($allocations, $item['quantity']);
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'product_name' => $item['product_name'] ?? ($product->name_ar ?? $product->name_en ?? ''),
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                    'notes' => $item['notes'] ?? null,
                    'allocations' => $allocations,
                ];
                $subtotal += $itemTotal;
            }
        }

        $salesOrder = SalesOrder::create([
            // Derived from the last id under a lock — counting reuses a number
            // as soon as any order is deleted, and concurrent requests collide.
            'order_number' => 'SO-'.str_pad(
                (string) (((int) SalesOrder::lockForUpdate()->max('id')) + 1),
                6,
                '0',
                STR_PAD_LEFT
            ),
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_PENDING,
            'order_date' => now(),
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'shipping_address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => null,
            'assigned_employee_id' => $employeeId,
        ]);

        $this->createOrderItemsWithAllocations($salesOrder, $itemsData);

        // Storefront checkout is a draft only: no stock reservation, no invoice,
        // no customer balance charge, no ledger posting. Those start when staff
        // confirm the order through the sales workflow.
        SalesOrderStatusHistory::create([
            'sales_order_id' => $salesOrder->id,
            'from_status' => null,
            'to_status' => SalesOrder::STATUS_PENDING,
            'note' => 'طلب شراء من العميل',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب الشراء بنجاح',
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ],
                'order_number' => $salesOrder->order_number,
                'status' => $salesOrder->status,
                'total' => $subtotal,
                'assigned_employee_id' => $salesOrder->assigned_employee_id,
            ],
        ], 201);
    }

    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $customer = Customer::where('phone', $validated['phone'])->first();

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد عميل بهذا الرقم',
                'data' => null,
            ], 404);
        }

        $salesOrders = $customer->salesOrders()
            ->with(['items', 'invoices'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_text' => $order->status_text,
                    'total' => (float) $order->total,
                    'order_date' => $order->order_date?->format('Y-m-d'),
                    'notes' => $order->notes,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_name' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'total' => (float) $item->total,
                        ];
                    }),
                    'invoices' => $order->invoices->map(function ($inv) {
                        return [
                            'id' => $inv->id,
                            'invoice_number' => $inv->invoice_number,
                            'status' => $inv->status,
                            'status_label' => $inv->status_label,
                            'total' => (float) $inv->total,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'تم جلب الطلبات بنجاح',
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                ],
                'orders' => $salesOrders,
            ],
        ]);
    }

    /**
     * Staff-created internal order ("طلب محلي").
     *
     * Creates a pending draft with optional warehouse allocation plans.
     * Stock reservation, invoice and ledger posting happen only on confirmation.
     */
    public function adminStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'assigned_employee_id' => 'nullable|integer|exists:employees,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'items.*.allocations.*.quantity' => 'required|integer|min:1',
        ], [
            'name.required' => 'اسم العميل مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'items.required' => 'يجب أن تحتوي الطلبية على منتج واحد على الأقل',
            'items.min' => 'يجب أن تحتوي الطلبية على منتج واحد على الأقل',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.allocations.*.warehouse_id.exists' => 'أحد المستودعات المحددة غير موجود',
            'items.*.allocations.*.quantity.min' => 'كمية التخصيص يجب أن تكون 1 على الأقل',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $customer = Customer::where('phone', $validated['phone'])->first();
                if (! $customer && ! empty($validated['email'])) {
                    $customer = Customer::where('email', $validated['email'])->first();
                }

                if ($customer) {
                    $customer->update([
                        'name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'email' => $validated['email'] ?? $customer->email,
                        'address' => $validated['address'] ?? $customer->address,
                    ]);
                } else {
                    $customer = Customer::create([
                        'name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'email' => $validated['email'] ?? null,
                        'address' => $validated['address'] ?? null,
                        'source' => 'purchase_request',
                        'status' => 'active',
                    ]);
                }

                $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))
                    ->get()
                    ->keyBy('id');

                $itemsData = [];
                $subtotal = 0;

                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    $unitPrice = $product->price ?? 0;
                    $quantity = $item['quantity'];
                    $itemTotal = $unitPrice * $quantity;

                    $allocations = $item['allocations'] ?? $this->suggestAllocationsFor($item, $product);

                    $this->assertAllocationsSum($allocations, $quantity);

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name_ar ?? $product->name_en ?? '',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $itemTotal,
                        'allocations' => $allocations,
                    ];
                    $subtotal += $itemTotal;
                }

                $salesOrder = SalesOrder::create([
                    // Derived from the last id under a lock. Counting reuses a
                    // number the moment any order is deleted, and two reps
                    // submitting at once always collided on it.
                    'order_number' => 'SO-'.str_pad(
                        (string) (((int) SalesOrder::lockForUpdate()->max('id')) + 1),
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'customer_id' => $customer->id,
                    'status' => SalesOrder::STATUS_PENDING,
                    'order_date' => now(),
                    'subtotal' => $subtotal,
                    'tax' => 0,
                    'discount' => 0,
                    'total' => $subtotal,
                    'shipping_address' => $validated['address'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                    // Sent by the app for the rep raising it; falls back to the
                    // signed-in user's own employee record so an order is never
                    // left unattributed just because the client omitted it.
                    'assigned_employee_id' => $validated['assigned_employee_id']
                        ?? Employee::where('user_id', auth()->id())->value('id'),
                ]);

                // Serving the customer is what makes them the rep's own: the
                // link is what the customer picker reads back on the next order.
                if ($salesOrder->assigned_employee_id !== null) {
                    $customer->assignEmployee($salesOrder->assigned_employee_id);
                }

                $this->createOrderItemsWithAllocations($salesOrder, $itemsData);

                // Staff-raised local orders are drafts too: confirmation is what
                // reserves stock, raises the invoice and posts the ledger.
                SalesOrderStatusHistory::create([
                    'sales_order_id' => $salesOrder->id,
                    'from_status' => null,
                    'to_status' => SalesOrder::STATUS_PENDING,
                    'note' => 'طلب محلي من فريق العمل',
                    'user_id' => auth()->id(),
                ]);

                $salesOrder->load(['customer', 'items.allocations.warehouse', 'invoices', 'assignedEmployee']);

                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الطلبية بنجاح',
                    'data' => $this->orderPayload($salesOrder),
                ], 201);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = SalesOrder::whereHas('customer', function ($q) {
            $q->where('source', 'purchase_request');
        })->with(['customer', 'items.allocations.warehouse', 'invoices', 'assignedEmployee']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', $search)
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
            });
        }

        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);
        $orders = $query->latest()->paginate($perPage);

        $items = collect($orders->items())->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_text' => $order->status_text,
                'total' => (float) $order->total,
                'subtotal' => (float) $order->subtotal,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'notes' => $order->notes,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'phone' => $order->customer->phone,
                    'email' => $order->customer->email,
                    'address' => $order->customer->address,
                ] : null,
                'assigned_employee' => $order->assignedEmployee ? [
                    'id' => $order->assignedEmployee->id,
                    'name' => $order->assignedEmployee->name,
                ] : null,
                'items' => $order->items->map(function ($item) {
                    return $this->itemPayload($item);
                }),
                'invoices' => $order->invoices->map(function ($inv) {
                    return [
                        'id' => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'status' => $inv->status,
                        'total' => (float) $inv->total,
                    ];
                }),
                'created_at' => $order->created_at?->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'تم جلب طلبات الشراء بنجاح',
            'data' => [
                'orders' => $items,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'has_more_pages' => $orders->hasMorePages(),
                ],
            ],
        ]);
    }

    public function adminShow(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder->load(['customer', 'items.allocations.warehouse', 'invoices', 'assignedEmployee']);

        return response()->json([
            'success' => true,
            'data' => $this->orderPayload($salesOrder),
        ]);
    }

    public function adminUpdateStatus(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'note' => 'nullable|string|max:1000',
        ]);

        // Never flip the column alone — confirmation must reserve stock, raise
        // the invoice and post the ledger through the workflow.
        if ($validated['status'] === SalesOrder::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إرجاع الطلبية إلى مسودة بعد انتقالها.',
                'data' => null,
            ], 422);
        }

        try {
            $result = $this->workflow->transitionTo(
                $salesOrder,
                $validated['status'],
                ['note' => $validated['note'] ?? null]
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        $salesOrder->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => [
                'id' => $salesOrder->id,
                'status' => $salesOrder->status,
                'status_text' => $salesOrder->status_text,
                'transition' => $result,
            ],
        ]);
    }

    public function adminAssignEmployee(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer|exists:employees,id',
        ], [
            'employee_id.exists' => 'الموظف المحدد غير موجود',
        ]);

        $salesOrder->update(['assigned_employee_id' => $validated['employee_id'] ?? null]);
        $salesOrder->load('assignedEmployee');

        return response()->json([
            'success' => true,
            'message' => $validated['employee_id'] ?? null
                ? 'تم تعيين الموظف المسؤول عن الطلبية بنجاح'
                : 'تم إلغاء تعيين الموظف المسؤول عن الطلبية',
            'data' => [
                'id' => $salesOrder->id,
                'assigned_employee' => $salesOrder->assignedEmployee ? [
                    'id' => $salesOrder->assignedEmployee->id,
                    'name' => $salesOrder->assignedEmployee->name,
                ] : null,
            ],
        ]);
    }

    public function adminUpdateItems(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== SalesOrder::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل بنود طلب بعد تأكيده.',
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.allocations' => 'nullable|array',
            'items.*.allocations.*.warehouse_id' => 'required|integer|exists:warehouses,id',
            'items.*.allocations.*.quantity' => 'required|integer|min:1',
        ], [
            'items.required' => 'يجب أن تحتوي الطلبية على منتج واحد على الأقل',
            'items.min' => 'يجب أن تحتوي الطلبية على منتج واحد على الأقل',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.allocations.*.warehouse_id.exists' => 'أحد المستودعات المحددة غير موجود',
            'items.*.allocations.*.quantity.min' => 'كمية التخصيص يجب أن تكون 1 على الأقل',
        ]);

        try {
            return DB::transaction(function () use ($validated, $salesOrder) {
                $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))
                    ->get()
                    ->keyBy('id');

                $itemsData = [];
                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    $unitPrice = $product->price ?? 0;
                    $quantity = $item['quantity'];
                    $itemTotal = $unitPrice * $quantity;

                    $allocations = $item['allocations'] ?? $this->suggestAllocationsFor($item, $product);
                    $this->assertAllocationsSum($allocations, $quantity);

                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $product->name_ar ?? $product->name_en ?? '',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $itemTotal,
                        'allocations' => $allocations,
                    ];
                    $subtotal += $itemTotal;
                }

                $salesOrder->items()->delete();
                $this->createOrderItemsWithAllocations($salesOrder, $itemsData);

                $salesOrder->update([
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                ]);

                $salesOrder->load(['customer', 'items.allocations.warehouse', 'invoices', 'assignedEmployee']);

                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث منتجات الطلبية بنجاح',
                    'data' => $this->orderPayload($salesOrder),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    /**
     * Build the per-warehouse plan for a single line when the client did not
     * send one — the server suggests the greedy split.
     */
    private function suggestAllocationsFor(array $item, ?Product $product): array
    {
        if (! $product) {
            return [];
        }

        $suggestion = app(OrderAllocationService::class)->suggestAllocations([
            ['product_id' => $product->id, 'quantity' => $item['quantity']],
        ]);

        return collect($suggestion[0]['allocations'] ?? [])
            ->map(fn ($a) => ['warehouse_id' => $a['warehouse_id'], 'quantity' => $a['quantity']])
            ->values()
            ->all();
    }

    /**
     * Guard against a client plan that does not add up to the line quantity.
     */
    private function assertAllocationsSum(array $allocations, int $quantity): void
    {
        if (empty($allocations)) {
            return;
        }

        $sum = array_sum(array_column($allocations, 'quantity'));
        if ((int) $sum !== $quantity) {
            throw new RuntimeException(
                "مجموع كميات التخصيص ({$sum}) لا يساوي كمية الصنف ({$quantity})"
            );
        }
    }

    /**
     * Persist order items and their confirmed warehouse allocations.
     */
    private function createOrderItemsWithAllocations(SalesOrder $salesOrder, array $itemsData): void
    {
        foreach ($itemsData as $itemData) {
            $item = $salesOrder->items()->create([
                'product_id' => $itemData['product_id'],
                'description' => $itemData['product_name'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount' => 0,
                'tax' => 0,
                'total' => $itemData['total_price'],
            ]);

            foreach ($itemData['allocations'] ?? [] as $allocation) {
                SalesOrderItemAllocation::create([
                    'sales_order_item_id' => $item->id,
                    'warehouse_id' => $allocation['warehouse_id'],
                    'quantity' => $allocation['quantity'],
                    'status' => SalesOrderItemAllocation::STATUS_PENDING,
                ]);
            }
        }
    }

    /**
     * Normalized order payload shared by adminShow/adminStore/adminUpdateItems.
     */
    private function orderPayload(SalesOrder $salesOrder): array
    {
        return [
            'id' => $salesOrder->id,
            'order_number' => $salesOrder->order_number,
            'status' => $salesOrder->status,
            'status_text' => $salesOrder->status_text,
            'total' => (float) $salesOrder->total,
            'subtotal' => (float) $salesOrder->subtotal,
            'order_date' => $salesOrder->order_date?->format('Y-m-d'),
            'notes' => $salesOrder->notes,
            'customer' => $salesOrder->customer ? [
                'id' => $salesOrder->customer->id,
                'name' => $salesOrder->customer->name,
                'phone' => $salesOrder->customer->phone,
                'email' => $salesOrder->customer->email,
                'address' => $salesOrder->customer->address,
            ] : null,
            'assigned_employee' => $salesOrder->assignedEmployee ? [
                'id' => $salesOrder->assignedEmployee->id,
                'name' => $salesOrder->assignedEmployee->name,
            ] : null,
            'items' => $salesOrder->items->map(fn ($item) => $this->itemPayload($item))->values(),
            'invoices' => $salesOrder->invoices->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'status' => $inv->status,
                    'total' => (float) $inv->total,
                ];
            }),
            'created_at' => $salesOrder->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * A single order line including its per-warehouse plan.
     */
    private function itemPayload(SalesOrderItem $item): array
    {
        $allocations = $item->relationLoaded('allocations')
            ? $item->allocations
            : $item->allocations()->get();

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => (float) $item->total,
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'warehouse_id' => $allocation->warehouse_id,
                    'warehouse_name' => $allocation->warehouse?->name,
                    'quantity' => $allocation->quantity,
                    'status' => $allocation->status,
                ];
            })->values(),
        ];
    }
}
