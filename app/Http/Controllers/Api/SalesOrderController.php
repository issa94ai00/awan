<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesOrder;
use App\Models\Invoice;
use App\Models\JournalEntryHeader;
use App\Models\JournalEntryLine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'creator', 'items.product']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // per_page was ignored, so callers asking for a larger page (the RMA
        // form requests the customer's delivered orders) silently received only
        // the newest 20 and could not find the order they needed.
        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);

        $salesOrders = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Sales orders retrieved successfully',
            'data' => [
                'sales_orders' => $salesOrders->items(),
                'pagination' => [
                    'current_page' => $salesOrders->currentPage(),
                    'last_page' => $salesOrders->lastPage(),
                    'per_page' => $salesOrders->perPage(),
                    'total' => $salesOrders->total(),
                    'has_more_pages' => $salesOrders->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'fulfillment_warehouse_id' => 'nullable|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_delivery' => 'nullable|date|after:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        $validated['order_number'] = 'SO-' . str_pad(SalesOrder::count() + 1, 6, '0', STR_PAD_LEFT);
        $validated['status'] = SalesOrder::STATUS_PENDING;
        $validated['created_by'] = auth()->id();

        if (!empty($validated['assigned_employee_id']) && empty($validated['fulfillment_warehouse_id'])) {
            $employee = Employee::find($validated['assigned_employee_id']);
            $validated['fulfillment_warehouse_id'] = $employee?->warehouse_id;
        }

        if (empty($validated['fulfillment_warehouse_id'])) {
            $validated['fulfillment_warehouse_id'] = Warehouse::active()->orderBy('id')->value('id');
        }

        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        $salesOrder = SalesOrder::create($validated);

        foreach ($request->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        $salesOrder->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء طلب البيع بنجاح',
            'data' => $salesOrder
        ], 201);
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'creator', 'items.product', 'quote']);

        return response()->json([
            'success' => true,
            'message' => 'Sales order retrieved successfully',
            'data' => $salesOrder
        ]);
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'fulfillment_warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'order_date' => 'nullable|date',
            'expected_delivery' => 'nullable|date|after:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        $subtotal = 0;
        foreach ($request->items as $item) {
            $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0) + ($item['tax'] ?? 0);
            $subtotal += $itemTotal;
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal - ($validated['discount'] ?? 0) + ($validated['tax'] ?? 0);

        if (!empty($validated['assigned_employee_id']) && empty($validated['fulfillment_warehouse_id'])) {
            $employee = Employee::find($validated['assigned_employee_id']);
            $validated['fulfillment_warehouse_id'] = $employee?->warehouse_id;
        }

        if (empty($validated['fulfillment_warehouse_id'])) {
            $validated['fulfillment_warehouse_id'] = Warehouse::active()->orderBy('id')->value('id');
        }

        $salesOrder->update($validated);

        $salesOrder->items()->delete();
        foreach ($request->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'tax' => $item['tax'] ?? 0,
            ]);
        }

        $salesOrder->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث طلب البيع بنجاح',
            'data' => $salesOrder
        ]);
    }

    public function destroy(SalesOrder $salesOrder)
    {
        $salesOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف طلب البيع بنجاح',
            'data' => null
        ]);
    }

    public function convertToInvoice(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_CONFIRMED) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن تحويل طلبات البيع المؤكدة فقط إلى فواتير',
                'data' => null
            ], 400);
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT),
            'customer_id' => $salesOrder->customer_id,
            'sales_order_id' => $salesOrder->id,
            'customer_name' => $salesOrder->customer->name,
            'customer_email' => $salesOrder->customer->email,
            'customer_phone' => $salesOrder->customer->phone,
            'subtotal' => $salesOrder->subtotal,
            'tax' => $salesOrder->tax,
            'discount' => $salesOrder->discount,
            'total' => $salesOrder->total,
            'paid_amount' => 0,
            'due_amount' => $salesOrder->total,
            'status' => Invoice::STATUS_PENDING,
            'notes' => $salesOrder->notes,
            'created_by' => auth()->id(),
        ]);

        foreach ($salesOrder->items as $item) {
            $invoice->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->product->name_ar,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'total' => $item->total,
            ]);
        }

        $salesOrder->customer->updateBalance($salesOrder->total);

        $invoice->load(['customer', 'salesOrder', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحويل طلب البيع إلى فاتورة بنجاح',
            'data' => $invoice
        ], 201);
    }

    public function confirmOrder(SalesOrder $salesOrder)
    {
        // التحقق من أن الطلبية لم يتم تأكيدها مسبقاً
        if ($salesOrder->status === SalesOrder::STATUS_CONFIRMED) {
            // التحقق من وجود الفاتورة
            $invoice = Invoice::where('sales_order_id', $salesOrder->id)->first();
            $hasInvoice = $invoice && $invoice->status !== Invoice::STATUS_CANCELLED;
            
            // التحقق من وجود القيد المحاسبي
            $journalEntry = JournalEntryHeader::where('reference_type', SalesOrder::class)
                ->where('reference_id', $salesOrder->id)
                ->where('status', 'posted')
                ->first();
            $hasJournalEntry = $journalEntry !== null;
            
            // التحقق من وجود حركات المخزون
            $stockMovements = StockMovement::where('reference', 'sales_order')
                ->where('source', $salesOrder->id)
                ->get();
            $hasStockMovements = $stockMovements->count() > 0;
            
            // إذا كانت كل العمليات صحيحة، عرض رسالة
            if ($hasInvoice && $hasJournalEntry && $hasStockMovements) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تأكيد هذه الطلبية مسبقا وسجلات الفواتير صحيحة والقيود المحاسبية صحيحة وحركة المخزون صحيحة',
                    'data' => [
                        'invoice' => $invoice,
                        'journal_entry' => $journalEntry,
                        'stock_movements' => $stockMovements,
                    ]
                ]);
            }
            
            // إذا كانت الطلبية مؤكدة لكن بعض العمليات غير مكتملة، أكمل العمليات الناقصة
            return $this->completeConfirmationProcesses($salesOrder, $invoice, $journalEntry, $stockMovements);
        }
        
        // تأكيد الطلبية وإجراء العمليات
        return DB::transaction(function () use ($salesOrder) {
            // تحديث حالة الطلبية
            $salesOrder->update([
                'status' => SalesOrder::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
            
            // إنشاء الفاتورة
            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT),
                'customer_id' => $salesOrder->customer_id,
                'sales_order_id' => $salesOrder->id,
                'subtotal' => $salesOrder->subtotal,
                'tax' => $salesOrder->tax,
                'discount' => $salesOrder->discount,
                'total' => $salesOrder->total,
                'paid_amount' => 0,
                'due_amount' => $salesOrder->total,
                'status' => Invoice::STATUS_CONFIRMED,
                'notes' => $salesOrder->notes,
                'created_by' => auth()->id(),
                'currency' => $salesOrder->currency ?? 'SAR',
            ]);
            
            // إنشاء بنود الفاتورة
            foreach ($salesOrder->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->product->name_ar ?? $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                    'total' => ($item->unit_price * $item->quantity) - $item->discount + $item->tax,
                ]);
            }
            
            // إنشاء القيد المحاسبي
            $journalEntry = JournalEntryHeader::create([
                'entry_number' => 'JE-' . now()->format('Ymd') . '-' . str_pad(JournalEntryHeader::count() + 1, 4, '0', STR_PAD_LEFT),
                'entry_date' => now(),
                'reference_type' => SalesOrder::class,
                'reference_id' => $salesOrder->id,
                'posting_key' => 'SO-' . $salesOrder->id,
                'source_module' => 'sales',
                'description' => 'تأكيد طلب بيع رقم ' . $salesOrder->order_number,
                'total_debit' => $salesOrder->total,
                'total_credit' => $salesOrder->total,
                'currency' => $salesOrder->currency ?? 'SAR',
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);
            
            // حساب حسابات محاسبية (مثال - يجب تعديلها حسب نظام الحسابات)
            $accountsReceivableId = $this->getAccountId('accounts_receivable');
            $salesRevenueId = $this->getAccountId('sales_revenue');
            $taxPayableId = $this->getAccountId('tax_payable');
            
            // حساب الذمم المدينة (مدين)
            JournalEntryLine::create([
                'journal_entry_header_id' => $journalEntry->id,
                'account_id' => $accountsReceivableId,
                'description' => 'ذمم العملاء - طلب بيع ' . $salesOrder->order_number,
                'debit' => $salesOrder->total,
                'credit' => 0,
            ]);
            
            // حساب الإيرادات (دائن)
            JournalEntryLine::create([
                'journal_entry_header_id' => $journalEntry->id,
                'account_id' => $salesRevenueId,
                'description' => 'إيرادات المبيعات - طلب بيع ' . $salesOrder->order_number,
                'debit' => 0,
                'credit' => $salesOrder->subtotal,
            ]);
            
            // حساب الضريبة (دائن)
            if ($salesOrder->tax > 0) {
                JournalEntryLine::create([
                    'journal_entry_header_id' => $journalEntry->id,
                    'account_id' => $taxPayableId,
                    'description' => 'ضريبة القيمة المضافة - طلب بيع ' . $salesOrder->order_number,
                    'debit' => 0,
                    'credit' => $salesOrder->tax,
                ]);
            }
            
            // إنشاء حركات المخزون (إخراج من المخزون)
            //
            // Ships through InventoryService so the movement, the warehouse row
            // and products.stock_quantity all move together. Keyed per item,
            // so re-confirming the same order can never take stock twice.
            $warehouseId = $salesOrder->fulfillment_warehouse_id;
            $inventory = app(\App\Services\Inventory\InventoryService::class);

            foreach ($salesOrder->items as $item) {
                $inventory->issue(
                    $item->product_id,
                    $item->quantity,
                    $warehouseId,
                    [
                        'key' => 'SO-' . $salesOrder->id . '-' . $item->product_id,
                        'reference' => 'sales_order',
                        'source' => $salesOrder->id,
                        'reason' => 'إخراج مخزون لطلب بيع رقم ' . $salesOrder->order_number,
                        'unit_cost' => $item->product->cost_price ?? 0,
                        'created_by' => auth()->id(),
                    ]
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد الطلبية بنجاح وإنشاء الفاتورة والقيد المحاسبي وحركة المخزون',
                'data' => [
                    'sales_order' => $salesOrder->load(['customer', 'items.product']),
                    'invoice' => $invoice->load(['items.product']),
                    'journal_entry' => $journalEntry->load('lines'),
                    'stock_movements' => StockMovement::where('reference', 'sales_order')
                        ->where('source', $salesOrder->id)
                        ->get(),
                ]
            ]);
        });
    }
    
    private function completeConfirmationProcesses(SalesOrder $salesOrder, $invoice, $journalEntry, $stockMovements)
    {
        return DB::transaction(function () use ($salesOrder, $invoice, $journalEntry, $stockMovements) {
            $processesCompleted = [];
            
            // إنشاء الفاتورة إذا لم تكن موجودة
            if (!$invoice || $invoice->status === Invoice::STATUS_CANCELLED) {
                $invoice = Invoice::create([
                    'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT),
                    'customer_id' => $salesOrder->customer_id,
                    'sales_order_id' => $salesOrder->id,
                    'subtotal' => $salesOrder->subtotal,
                    'tax' => $salesOrder->tax,
                    'discount' => $salesOrder->discount,
                    'total' => $salesOrder->total,
                    'paid_amount' => 0,
                    'due_amount' => $salesOrder->total,
                    'status' => Invoice::STATUS_CONFIRMED,
                    'notes' => $salesOrder->notes,
                    'created_by' => auth()->id(),
                    'currency' => $salesOrder->currency ?? 'SAR',
                ]);
                
                foreach ($salesOrder->items as $item) {
                    $invoice->items()->create([
                        'product_id' => $item->product_id,
                        'description' => $item->product->name_ar ?? $item->product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $item->discount,
                        'tax' => $item->tax,
                        'total' => ($item->unit_price * $item->quantity) - $item->discount + $item->tax,
                    ]);
                }
                
                $processesCompleted[] = 'invoice';
            }
            
            // إنشاء القيد المحاسبي إذا لم يكن موجوداً
            if (!$journalEntry) {
                $journalEntry = JournalEntryHeader::create([
                    'entry_number' => 'JE-' . now()->format('Ymd') . '-' . str_pad(JournalEntryHeader::count() + 1, 4, '0', STR_PAD_LEFT),
                    'entry_date' => now(),
                    'reference_type' => SalesOrder::class,
                    'reference_id' => $salesOrder->id,
                    'posting_key' => 'SO-' . $salesOrder->id,
                    'source_module' => 'sales',
                    'description' => 'تأكيد طلب بيع رقم ' . $salesOrder->order_number,
                    'total_debit' => $salesOrder->total,
                    'total_credit' => $salesOrder->total,
                    'currency' => $salesOrder->currency ?? 'SAR',
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);
                
                $accountsReceivableId = $this->getAccountId('accounts_receivable');
                $salesRevenueId = $this->getAccountId('sales_revenue');
                $taxPayableId = $this->getAccountId('tax_payable');
                
                JournalEntryLine::create([
                    'journal_entry_header_id' => $journalEntry->id,
                    'account_id' => $accountsReceivableId,
                    'description' => 'ذمم العملاء - طلب بيع ' . $salesOrder->order_number,
                    'debit' => $salesOrder->total,
                    'credit' => 0,
                ]);
                
                JournalEntryLine::create([
                    'journal_entry_header_id' => $journalEntry->id,
                    'account_id' => $salesRevenueId,
                    'description' => 'إيرادات المبيعات - طلب بيع ' . $salesOrder->order_number,
                    'debit' => 0,
                    'credit' => $salesOrder->subtotal,
                ]);
                
                if ($salesOrder->tax > 0) {
                    JournalEntryLine::create([
                        'journal_entry_header_id' => $journalEntry->id,
                        'account_id' => $taxPayableId,
                        'description' => 'ضريبة القيمة المضافة - طلب بيع ' . $salesOrder->order_number,
                        'debit' => 0,
                        'credit' => $salesOrder->tax,
                    ]);
                }
                
                $processesCompleted[] = 'journal_entry';
            }
            
            // إنشاء حركات المخزون إذا لم تكن موجودة
            //
            // The idempotency is now handled inside InventoryService via the
            // movement_key — re-running this method can only ever book the stock
            // once. Retrying an issue is a no-op, so no count is needed here.
            if ($stockMovements->count() === 0) {
                $warehouseId = $salesOrder->fulfillment_warehouse_id;
                $inventory = app(\App\Services\Inventory\InventoryService::class);

                foreach ($salesOrder->items as $item) {
                    $inventory->issue(
                        $item->product_id,
                        $item->quantity,
                        $warehouseId,
                        [
                            'key' => 'SO-' . $salesOrder->id . '-' . $item->product_id,
                            'reference' => 'sales_order',
                            'source' => $salesOrder->id,
                            'reason' => 'إخراج مخزون لطلب بيع رقم ' . $salesOrder->order_number,
                            'unit_cost' => $item->product->cost_price ?? 0,
                            'created_by' => auth()->id(),
                        ]
                    );
                }

                $processesCompleted[] = 'stock_movements';
            }
            
            return response()->json([
                'success' => true,
                'message' => 'تم إكمال العمليات الناقصة: ' . implode(', ', $processesCompleted),
                'data' => [
                    'sales_order' => $salesOrder->load(['customer', 'items.product']),
                    'invoice' => $invoice ? $invoice->load(['items.product']) : null,
                    'journal_entry' => $journalEntry ? $journalEntry->load('lines') : null,
                    'stock_movements' => StockMovement::where('reference', 'sales_order')
                        ->where('source', $salesOrder->id)
                        ->get(),
                ]
            ]);
        });
    }
    
    private function getAccountId($accountType)
    {
        // هذه دالة مساعدة للحصول على معرف الحساب المحاسبي
        // يجب تعديلها حسب هيكل الحسابات الفعلي في النظام
        $accounts = [
            'accounts_receivable' => 1, // معرف افتراضي
            'sales_revenue' => 2,
            'tax_payable' => 3,
            'cost_of_goods_sold' => 4,
            'inventory' => 5,
        ];
        
        return $accounts[$accountType] ?? 1;
    }
}
