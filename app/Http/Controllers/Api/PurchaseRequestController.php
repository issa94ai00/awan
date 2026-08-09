<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Http\Resources\CustomerResource;
use App\Models\SalesOrderStatusHistory;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.product_name' => 'required_without:items.*.product_id|nullable|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود',
            'items.*.product_name.required_without' => 'اسم المنتج مطلوب',
            'items.*.quantity.required_with' => 'الكمية مطلوبة',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
        ]);

        $customer = Customer::where('phone', $validated['phone'])->first();
        if (!$customer && !empty($validated['email'])) {
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

        $subtotal = 0;
        $itemsData = [];
        if (!empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $unitPrice = 0;
                $itemTotal = 0;
                $productId = null;

                if (!empty($item['product_id'])) {
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

                $itemsData[] = [
                    'product_id' => $productId,
                    'product_name' => $item['product_name'] ?? ($product->name_ar ?? $product->name_en ?? ''),
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                    'notes' => $item['notes'] ?? null,
                ];
                $subtotal += $itemTotal;
            }
        }

        $salesOrder = SalesOrder::create([
            'order_number' => 'SO-' . str_pad(SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT),
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
        ]);

        foreach ($itemsData as $itemData) {
            $salesOrder->items()->create([
                'product_id' => $itemData['product_id'],
                'description' => $itemData['product_name'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount' => 0,
                'tax' => 0,
                'total' => $itemData['total_price'],
            ]);
        }

        $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'paid_amount' => 0,
            'due_amount' => $subtotal,
            'payment_method' => Invoice::PAYMENT_CASH,
            'status' => Invoice::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
            'created_by' => null,
        ]);

        foreach ($itemsData as $itemData) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $itemData['product_id'],
                'product_name' => $itemData['product_name'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'total_price' => $itemData['total_price'],
                'notes' => $itemData['notes'] ?? null,
            ]);
        }

        $customer->updateBalance($subtotal);

        // This path raised an invoice and charged the customer without the
        // ledger ever hearing about it, so every request placed through the
        // storefront left revenue missing from the income statement. Posting is
        // keyed on the invoice, so confirming the order later will not
        // double-post it.
        //
        // Deliberately non-fatal. This is a customer pressing "order" on a
        // storefront: a misconfigured chart of accounts is the shop's problem,
        // not theirs, and letting a posting failure throw here turned an
        // accounting gap into a checkout that rejects every order. The failure
        // is logged, and the unposted invoice is already reported by the system
        // health check and repairable with `accounting:backfill`.
        $postingError = null;
        try {
            $this->ledger->postInvoice($invoice);
        } catch (\Throwable $e) {
            $postingError = $e->getMessage();
            report($e);
        }

        // Opens the stage history, so a storefront order carries the same trail
        // as one raised by staff.
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
                'invoice_number' => $invoice->invoice_number,
                'total' => $subtotal,
            ],
            // Surfaced for the operator, never shown to the customer: their
            // order went through either way.
            'accounting_warning' => $postingError,
        ], 201);
    }

    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $customer = Customer::where('phone', $validated['phone'])->first();

        if (!$customer) {
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

    public function adminIndex(Request $request): JsonResponse
    {
        $query = SalesOrder::whereHas('customer', function ($q) {
            $q->where('source', 'purchase_request');
        })->with(['customer', 'items', 'invoices', 'assignedEmployee']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
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
        $salesOrder->load(['customer', 'items', 'invoices', 'assignedEmployee']);

        return response()->json([
            'success' => true,
            'data' => [
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
                'items' => $salesOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total' => (float) $item->total,
                    ];
                }),
                'invoices' => $salesOrder->invoices->map(function ($inv) {
                    return [
                        'id' => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'status' => $inv->status,
                        'total' => (float) $inv->total,
                    ];
                }),
                'created_at' => $salesOrder->created_at?->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function adminUpdateStatus(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $salesOrder->update(['status' => $validated['status']]);

        if ($validated['status'] === SalesOrder::STATUS_CONFIRMED) {
            $salesOrder->update(['confirmed_at' => now()]);
        } elseif ($validated['status'] === SalesOrder::STATUS_SHIPPED) {
            $salesOrder->update(['shipped_at' => now()]);
        } elseif ($validated['status'] === SalesOrder::STATUS_DELIVERED) {
            $salesOrder->update(['delivered_at' => now()]);
        }

        // Update related invoices
        $invoiceStatus = match ($validated['status']) {
            SalesOrder::STATUS_DELIVERED => Invoice::STATUS_PAID,
            SalesOrder::STATUS_CANCELLED => Invoice::STATUS_CANCELLED,
            default => null,
        };
        if ($invoiceStatus) {
            $salesOrder->invoices()->update(['status' => $invoiceStatus]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => [
                'id' => $salesOrder->id,
                'status' => $salesOrder->status,
                'status_text' => $salesOrder->status_text,
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
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ], [
            'items.required' => 'يجب أن تحتوي الطلبية على منتج واحد على الأقل',
            'items.min' => 'يجب أن تحتوي الطلبية على منتج واحد على الأقل',
            'items.*.product_id.exists' => 'أحد المنتجات المحددة غير موجود',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
        ]);

        $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $itemsData = [];
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $product = $products->get($item['product_id']);
            $unitPrice = $product->price ?? 0;
            $itemTotal = $unitPrice * $item['quantity'];

            $itemsData[] = [
                'product_id' => $item['product_id'],
                'product_name' => $product->name_ar ?? $product->name_en ?? '',
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $itemTotal,
            ];
            $subtotal += $itemTotal;
        }

        $salesOrder->items()->delete();
        foreach ($itemsData as $itemData) {
            $salesOrder->items()->create([
                'product_id' => $itemData['product_id'],
                'description' => $itemData['product_name'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount' => 0,
                'tax' => 0,
                'total' => $itemData['total_price'],
            ]);
        }
        $salesOrder->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);

        // Keep the linked invoice(s) in sync, mirroring how store() creates both together.
        foreach ($salesOrder->invoices as $invoice) {
            $invoice->items()->delete();
            foreach ($itemsData as $itemData) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $itemData['product_id'],
                    'product_name' => $itemData['product_name'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                ]);
            }
            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'due_amount' => max($subtotal - $invoice->paid_amount, 0),
            ]);
        }

        $salesOrder->load(['customer', 'items', 'invoices']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث منتجات الطلبية بنجاح',
            'data' => [
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
                'items' => $salesOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total' => (float) $item->total,
                    ];
                }),
                'invoices' => $salesOrder->invoices->map(function ($inv) {
                    return [
                        'id' => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'status' => $inv->status,
                        'total' => (float) $inv->total,
                    ];
                }),
            ],
        ]);
    }
}
