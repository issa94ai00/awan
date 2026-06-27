<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Expense;
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
            $query = Invoice::query()->with(['items.product', 'customer']);

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

            // Filter by customer name or phone or invoice number
            if ($request->filled('search')) {
                $search = '%' . $request->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', $search)
                        ->orWhereHas('customer', function ($qCustomer) use ($search) {
                            $qCustomer->where('name', 'like', $search)
                                ->orWhere('phone', 'like', $search);
                        });
                });
            }

            $invoices = $query->latest()->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الفواتير بنجاح',
                'data' => [
                    'invoices' => InvoiceResource::collection($invoices->items()),
                    'pagination' => [
                        'current_page' => $invoices->currentPage(),
                        'last_page' => $invoices->lastPage(),
                        'per_page' => $invoices->perPage(),
                        'total' => $invoices->total(),
                        'has_more_pages' => $invoices->hasMorePages(),
                    ],
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
                'customer_id' => 'nullable|integer|exists:customers,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:500',
                'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
                'tax' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,card,transfer',
                'notes' => 'nullable|string|max:2000',
                'status' => 'nullable|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
                'expenses' => 'nullable|array',
                'expenses.*.description' => 'required_with:expenses|string|max:255',
                'expenses.*.amount' => 'required_with:expenses|numeric|min:0',
                'expenses.*.category' => 'nullable|string|in:shipping,packaging,handling,other',
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

            // Fetch all product units if any
            $unitIds = collect($validated['items'])->pluck('product_unit_id')->filter()->unique()->toArray();
            $units = ProductUnit::whereIn('id', $unitIds)->get()->keyBy('id');

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

                // Get unit info if provided
                $unit = null;
                $unitName = null;
                $unitMultiplier = 1;

                if (!empty($item['product_unit_id'])) {
                    $unit = $units->get($item['product_unit_id']);
                    if ($unit) {
                        $unitName = $unit->name;
                        $unitMultiplier = (float) $unit->base_unit_multiplier;
                    }
                }

                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name_ar ?? $product->name_en ?? 'منتج غير معروف',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'notes' => $item['notes'] ?? null,
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'unit_name' => $unitName,
                    'unit_multiplier' => $unitMultiplier,
                ];

                $subtotal += $totalPrice;
            }

            // Calculate totals
            $tax = (float) ($validated['tax'] ?? 0);
            $discount = (float) ($validated['discount'] ?? 0);
            
            // Calculate expenses total
            $expensesTotal = 0;
            if (isset($validated['expenses']) && is_array($validated['expenses'])) {
                $expensesTotal = collect($validated['expenses'])->sum('amount');
            }
            
            $total = $subtotal + $tax - $discount + $expensesTotal;
            if ($total < 0) $total = 0;

            // Generate invoice number
            $invoice = new Invoice();
            $invoiceNumber = $invoice->generateInvoiceNumber();

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $validated['customer_id'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $validated['payment_method'] ?? Invoice::PAYMENT_CASH,
                'status' => $validated['status'] ?? Invoice::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->check() ? auth()->id() : null,
                'paid_at' => ($validated['status'] ?? null) === Invoice::STATUS_DELIVERED ? now() : null,
            ]);

            // Create invoice items
            foreach ($itemsData as $itemData) {
                $itemData['invoice_id'] = $invoice->id;
                InvoiceItem::create($itemData);
            }

            // Create expenses if provided
            if (isset($validated['expenses']) && is_array($validated['expenses'])) {
                foreach ($validated['expenses'] as $expense) {
                    if (!empty($expense['description']) && $expense['amount'] > 0) {
                        Expense::create([
                            'expense_number' => 'EXP-' . str_pad(Expense::count() + 1, 6, '0', STR_PAD_LEFT),
                            'invoice_id' => $invoice->id,
                            'customer_id' => $invoice->customer_id,
                            'description' => $expense['description'],
                            'amount' => $expense['amount'],
                            'category' => $expense['category'] ?? 'other',
                            'expense_date' => now(),
                            'status' => 'pending',
                            'created_by' => auth()->check() ? auth()->id() : null,
                            'currency' => 'SAR',
                            'exchange_rate' => 1.0000,
                        ]);
                    }
                }
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
                'data' => null,
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
     * Update an invoice with items
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|integer|exists:customers,id',
                'items' => 'nullable|array|min:1',
                'items.*.product_id' => 'required_with:items|integer|exists:products,id',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:500',
                'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
                'tax' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,card,transfer',
                'notes' => 'nullable|string|max:2000',
                'status' => 'nullable|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
                'expenses' => 'nullable|array',
                'expenses.*.description' => 'required_with:expenses|string|max:255',
                'expenses.*.amount' => 'required_with:expenses|numeric|min:0',
                'expenses.*.category' => 'nullable|string|in:shipping,packaging,handling,other',
            ], [
                'items.required' => 'يجب إضافة منتج واحد على الأقل',
                'items.min' => 'يجب إضافة منتج واحد على الأقل',
                'items.*.product_id.required' => 'معرف المنتج مطلوب',
                'items.*.product_id.exists' => 'المنتج غير موجود',
                'items.*.quantity.required' => 'الكمية مطلوبة',
                'items.*.quantity.min' => 'الكمية يجب أن تكون على الأقل 1',
                'items.*.unit_price.required' => 'السعر مطلوب',
                'items.*.unit_price.min' => 'السعر يجب أن يكون 0 أو أكثر',
            ]);

            // Only process items if they are provided
            if (isset($validated['items'])) {
                // Fetch all products to get their names
                $productIds = collect($validated['items'])->pluck('product_id')->unique()->toArray();
                $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

                // Fetch all product units if any
                $unitIds = collect($validated['items'])->pluck('product_unit_id')->filter()->unique()->toArray();
                $units = ProductUnit::whereIn('id', $unitIds)->get()->keyBy('id');

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

                    // Get unit info if provided
                    $unit = null;
                    $unitName = null;
                    $unitMultiplier = 1;

                    if (!empty($item['product_unit_id'])) {
                        $unit = $units->get($item['product_unit_id']);
                        if ($unit) {
                            $unitName = $unit->name;
                            $unitMultiplier = (float) $unit->base_unit_multiplier;
                        }
                    }

                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $product->name_ar ?? $product->name_en ?? 'منتج غير معروف',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'notes' => $item['notes'] ?? null,
                        'product_unit_id' => $item['product_unit_id'] ?? null,
                        'unit_name' => $unitName,
                        'unit_multiplier' => $unitMultiplier,
                    ];

                    $subtotal += $totalPrice;
                }

                // Calculate totals
                $tax = (float) ($validated['tax'] ?? 0);
                $discount = (float) ($validated['discount'] ?? 0);
                
                // Calculate expenses total
                $expensesTotal = 0;
                if (isset($validated['expenses']) && is_array($validated['expenses'])) {
                    $expensesTotal = collect($validated['expenses'])->sum('amount');
                }
                
                $total = $subtotal + $tax - $discount + $expensesTotal;
                if ($total < 0) $total = 0;

                // Update invoice with items
                $invoice->update([
                    'customer_id' => $validated['customer_id'] ?? $invoice->customer_id,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'total' => $total,
                    'payment_method' => $validated['payment_method'] ?? $invoice->payment_method,
                    'status' => $validated['status'] ?? $invoice->status,
                    'notes' => $validated['notes'] ?? $invoice->notes,
                ]);

                // Delete old items and create new ones
                $invoice->items()->delete();
                foreach ($itemsData as $itemData) {
                    $itemData['invoice_id'] = $invoice->id;
                    InvoiceItem::create($itemData);
                }

                // Delete old expenses and create new ones
                $invoice->expenses()->delete();
                if (isset($validated['expenses']) && is_array($validated['expenses'])) {
                    foreach ($validated['expenses'] as $expense) {
                        if (!empty($expense['description']) && $expense['amount'] > 0) {
                            Expense::create([
                                'expense_number' => 'EXP-' . str_pad(Expense::count() + 1, 6, '0', STR_PAD_LEFT),
                                'invoice_id' => $invoice->id,
                                'customer_id' => $invoice->customer_id,
                                'description' => $expense['description'],
                                'amount' => $expense['amount'],
                                'category' => $expense['category'] ?? 'other',
                                'expense_date' => now(),
                                'status' => 'pending',
                                'created_by' => auth()->check() ? auth()->id() : null,
                                'currency' => 'SAR',
                                'exchange_rate' => 1.0000,
                            ]);
                        }
                    }
                }
            } else {
                // Update invoice without items (status only update)
                $invoice->update([
                    'customer_id' => $validated['customer_id'] ?? $invoice->customer_id,
                    'payment_method' => $validated['payment_method'] ?? $invoice->payment_method,
                    'status' => $validated['status'] ?? $invoice->status,
                    'notes' => $validated['notes'] ?? $invoice->notes,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الفاتورة بنجاح',
                'data' => new InvoiceResource($invoice->load('items.product')),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث الفاتورة',
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
                'status' => 'required|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
            ], [
                'status.required' => 'الحالة مطلوبة',
                'status.in' => 'الحالة غير صالحة',
            ]);

            $oldStatus = $invoice->status;
            $newStatus = $validated['status'];

            // Update status
            $invoice->update(['status' => $newStatus]);

            // Handle special status logic
            if ($newStatus === Invoice::STATUS_DELIVERED) {
                $invoice->update(['paid_at' => now()]);
            } elseif ($newStatus === Invoice::STATUS_CANCELLED) {
                $invoice->update(['paid_at' => null]);
            } elseif (in_array($oldStatus, [Invoice::STATUS_DELIVERED]) && !in_array($newStatus, [Invoice::STATUS_DELIVERED])) {
                $invoice->update(['paid_at' => null]);
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
            // Revenue calculation based on status
            $revenueToday = Invoice::whereDate('created_at', today())
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('status', '!=', Invoice::STATUS_PENDING)
                ->sum('total');
            
            $revenueWeek = Invoice::whereDate('created_at', '>=', now()->startOfWeek())
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('status', '!=', Invoice::STATUS_PENDING)
                ->sum('total');
            
            $revenueMonth = Invoice::whereDate('created_at', '>=', now()->startOfMonth())
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('status', '!=', Invoice::STATUS_PENDING)
                ->sum('total');
            
            $revenueTotal = Invoice::where('status', '!=', Invoice::STATUS_CANCELLED)
                ->where('status', '!=', Invoice::STATUS_PENDING)
                ->sum('total');

            // Total invoices (all statuses)
            $totalToday = Invoice::whereDate('created_at', today())->sum('total');
            $totalWeek = Invoice::whereDate('created_at', '>=', now()->startOfWeek())->sum('total');
            $totalMonth = Invoice::whereDate('created_at', '>=', now()->startOfMonth())->sum('total');
            $totalAll = Invoice::sum('total');

            // Count statistics
            $countToday = Invoice::whereDate('created_at', today())->count();
            $countWeek = Invoice::whereDate('created_at', '>=', now()->startOfWeek())->count();
            $countMonth = Invoice::whereDate('created_at', '>=', now()->startOfMonth())->count();
            $countTotal = Invoice::count();

            // Status breakdown
            $pendingToday = Invoice::where('status', Invoice::STATUS_PENDING)
                ->whereDate('created_at', today())
                ->sum('total');
            
            $confirmedToday = Invoice::where('status', Invoice::STATUS_CONFIRMED)
                ->whereDate('created_at', today())
                ->sum('total');
            
            $processingToday = Invoice::where('status', Invoice::STATUS_PROCESSING)
                ->whereDate('created_at', today())
                ->sum('total');
            
            $shippedToday = Invoice::where('status', Invoice::STATUS_SHIPPED)
                ->whereDate('created_at', today())
                ->sum('total');
            
            $deliveredToday = Invoice::where('status', Invoice::STATUS_DELIVERED)
                ->whereDate('created_at', today())
                ->sum('total');
            
            $cancelledToday = Invoice::where('status', Invoice::STATUS_CANCELLED)
                ->whereDate('created_at', today())
                ->sum('total');

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإحصائيات بنجاح',
                'data' => [
                    'revenue' => [
                        'today' => (float) $revenueToday,
                        'week' => (float) $revenueWeek,
                        'month' => (float) $revenueMonth,
                        'total' => (float) $revenueTotal,
                    ],
                    'total_sales' => [
                        'today' => (float) $totalToday,
                        'week' => (float) $totalWeek,
                        'month' => (float) $totalMonth,
                        'total' => (float) $totalAll,
                    ],
                    'count' => [
                        'today' => (int) $countToday,
                        'week' => (int) $countWeek,
                        'month' => (int) $countMonth,
                        'total' => (int) $countTotal,
                    ],
                    'today_breakdown' => [
                        'pending' => (float) $pendingToday,
                        'confirmed' => (float) $confirmedToday,
                        'processing' => (float) $processingToday,
                        'shipped' => (float) $shippedToday,
                        'delivered' => (float) $deliveredToday,
                        'cancelled' => (float) $cancelledToday,
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
