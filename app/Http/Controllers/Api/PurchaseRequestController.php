<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'nullable|array',
            'items.*.product_name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
        ], [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'items.*.product_name.required_with' => 'اسم المنتج مطلوب',
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
                $product = Product::where('name_ar', $item['product_name'])
                    ->orWhere('name_en', $item['product_name'])
                    ->orWhere('sku', $item['product_name'])
                    ->first();

                if ($product) {
                    $productId = $product->id;
                    $unitPrice = $product->price ?? 0;
                    $itemTotal = $unitPrice * $item['quantity'];
                }

                $itemsData[] = [
                    'product_id' => $productId,
                    'product_name' => $item['product_name'],
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
        })->with(['customer', 'items', 'invoices']);

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
        $salesOrder->load(['customer', 'items', 'invoices']);

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
                'items' => $salesOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
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
}
