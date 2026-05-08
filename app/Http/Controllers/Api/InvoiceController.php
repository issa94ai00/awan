<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class InvoiceController extends Controller
{
    /**
     * List all invoices with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Invoice::query()->with(['items.product']);

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment method
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Filter by customer name or phone
            if ($request->filled('search')) {
                $search = '%' . $request->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', $search)
                        ->orWhere('customer_phone', 'like', $search)
                        ->orWhere('invoice_number', 'like', $search);
                });
            }

            $invoices = $query->latest()->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الفواتير بنجاح',
                'data' => InvoiceResource::collection($invoices->items()),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'has_more_pages' => $invoices->hasMorePages(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الفواتير',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new invoice with items
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_name' => 'nullable|string|max:255',
                'customer_email' => 'nullable|email|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:500',
                'tax' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,card,transfer',
                'notes' => 'nullable|string|max:2000',
                'status' => 'nullable|string|in:pending,paid,cancelled',
            ], [
                'items.required' => 'يجب إضافة منتج واحد على الأقل',
                'items.min' => 'يجب إضافة منتج واحد على الأقل',
                'items.*.product_id.required' => 'معرف المنتج مطلوب',
                'items.*.product_id.exists' => 'المنتج غير موجود',
                'items.*.quantity.required' => 'الكمية مطلوبة',
                'items.*.quantity.min' => 'الكمية يجب أن تكون على الأقل 1',
                'items.*.unit_price.required' => 'السعر مطلوب',
                'items.*.unit_price.min' => 'السعر يجب أن يكون 0 أو أكثر',
                'payment_method.in' => 'طريقة الدفع غير صالحة',
                'status.in' => 'الحالة غير صالحة',
            ]);

            // Fetch all products to get their names
            $productIds = collect($validated['items'])->pluck('product_id')->unique()->toArray();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            // Calculate subtotal from items
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'المنتج غير موجود: ' . $item['product_id'],
                    ], 422);
                }

                $unitPrice = (float) $item['unit_price'];
                $quantity = (int) $item['quantity'];
                $totalPrice = $unitPrice * $quantity;

                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name_ar ?? $product->name_en ?? 'منتج غير معروف',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'notes' => $item['notes'] ?? null,
                ];

                $subtotal += $totalPrice;
            }

            // Calculate totals
            $tax = (float) ($validated['tax'] ?? 0);
            $discount = (float) ($validated['discount'] ?? 0);
            $total = $subtotal + $tax - $discount;
            if ($total < 0) $total = 0;

            // Generate invoice number
            $invoice = new Invoice();
            $invoiceNumber = $invoice->generateInvoiceNumber();

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $validated['payment_method'] ?? Invoice::PAYMENT_CASH,
                'status' => $validated['status'] ?? Invoice::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->check() ? auth()->id() : null,
                'paid_at' => ($validated['status'] ?? null) === Invoice::STATUS_PAID ? now() : null,
            ]);

            // Create invoice items
            foreach ($itemsData as $itemData) {
                $itemData['invoice_id'] = $invoice->id;
                InvoiceItem::create($itemData);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفاتورة بنجاح',
                'data' => new InvoiceResource($invoice->load('items.product')),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في إنشاء الفاتورة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single invoice with items
     */
    public function show(Invoice $invoice): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'تم جلب الفاتورة بنجاح',
                'data' => new InvoiceResource($invoice->load('items.product')),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الفاتورة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update invoice status (pay or cancel)
     */
    public function updateStatus(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:pending,paid,cancelled',
            ], [
                'status.required' => 'الحالة مطلوبة',
                'status.in' => 'الحالة غير صالحة',
            ]);

            $oldStatus = $invoice->status;
            $newStatus = $validated['status'];

            if ($newStatus === Invoice::STATUS_PAID) {
                $invoice->markAsPaid();
            } elseif ($newStatus === Invoice::STATUS_CANCELLED) {
                $invoice->cancel();
            } else {
                $invoice->update(['status' => $newStatus, 'paid_at' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الفاتورة بنجاح',
                'data' => new InvoiceResource($invoice->load('items.product')),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث حالة الفاتورة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an invoice
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        try {
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الفاتورة بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في حذف الفاتورة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoice statistics summary
     */
    public function summary(): JsonResponse
    {
        try {
            $today = Invoice::whereDate('created_at', today())->sum('total');
            $week = Invoice::whereDate('created_at', '>=', now()->startOfWeek())->sum('total');
            $month = Invoice::whereDate('created_at', '>=', now()->startOfMonth())->sum('total');
            $total = Invoice::sum('total');

            $countToday = Invoice::whereDate('created_at', today())->count();
            $countWeek = Invoice::whereDate('created_at', '>=', now()->startOfWeek())->count();
            $countMonth = Invoice::whereDate('created_at', '>=', now()->startOfMonth())->count();
            $countTotal = Invoice::count();

            $paidToday = Invoice::where('status', Invoice::STATUS_PAID)->whereDate('created_at', today())->sum('total');
            $pendingToday = Invoice::where('status', Invoice::STATUS_PENDING)->whereDate('created_at', today())->sum('total');

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإحصائيات بنجاح',
                'data' => [
                    'revenue' => [
                        'today' => (float) $today,
                        'week' => (float) $week,
                        'month' => (float) $month,
                        'total' => (float) $total,
                    ],
                    'count' => [
                        'today' => (int) $countToday,
                        'week' => (int) $countWeek,
                        'month' => (int) $countMonth,
                        'total' => (int) $countTotal,
                    ],
                    'today_breakdown' => [
                        'paid' => (float) $paidToday,
                        'pending' => (float) $pendingToday,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
