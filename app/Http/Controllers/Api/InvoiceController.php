<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\JournalEntryHeader;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Sales\GoodsIssueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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
                // A caller narrowing to "any of these statuses" (e.g. the RMA
                // picker, which accepts confirmed or delivered invoices) sends
                // an array; every other caller still sends one status string.
                if (is_array($request->status)) {
                    $query->whereIn('status', $request->status);
                } else {
                    $query->where('status', $request->status);
                }
            }

            // Filter by customer_id
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
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
    /**
     * Moves the customer's account after an invoice is raised.
     *
     * The invoice itself creates the receivable; a payment taken at the same
     * time immediately clears part (or all) of it. Both are recorded, because
     * they are two different facts: what was sold, and what was collected.
     *
     * Invoices converted from a sales order are skipped for the receivable
     * side — SalesOrderController already added the order total to the balance
     * when the order was placed, and charging it again would double the debt.
     *
     * @return array{payment: ?Payment, customer_balance: ?float}
     */
    private function settleCustomerAccount(Invoice $invoice, float $paidAmount, ?string $method): array
    {
        $customer = $invoice->customer_id ? Customer::find($invoice->customer_id) : null;

        if (!$customer) {
            return ['payment' => null, 'customer_balance' => null];
        }

        if (!$invoice->sales_order_id) {
            $customer->updateBalance((float) $invoice->total);
        }

        $payment = null;

        if ($paidAmount > 0) {
            $payment = Payment::create([
                'payment_number' => 'PAY-' . str_pad((string) (Payment::max('id') + 1), 6, '0', STR_PAD_LEFT),
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                // The invoice's payment method describes how this money arrived;
                // `transfer` is what the invoice calls a bank transfer.
                'payment_method' => match ($method) {
                    'card' => Payment::METHOD_CARD,
                    'transfer' => Payment::METHOD_BANK_TRANSFER,
                    'check' => Payment::METHOD_CHECK,
                    default => Payment::METHOD_CASH,
                },
                'status' => Payment::STATUS_COMPLETED,
                'amount' => $paidAmount,
                'payment_date' => now()->toDateString(),
                'reference' => $invoice->invoice_number,
                'notes' => 'دفعة عند إنشاء الفاتورة ' . $invoice->invoice_number,
                'created_by' => auth()->id(),
            ]);

            $customer->updateBalance(-$paidAmount);
        }

        return [
            'payment' => $payment,
            'customer_balance' => round((float) $customer->fresh()->balance, 5),
        ];
    }

    /**
     * Turns a shortage report into something a screen can point at.
     *
     * @param  list<array{product_id:int, warehouse_id:int, required:int, available:int, shortfall:int}>  $shortages
     * @return list<array<string, mixed>>
     */
    private function describeShortages(array $shortages): array
    {
        $products = Product::whereIn('id', array_column($shortages, 'product_id'))->get()->keyBy('id');
        $warehouses = Warehouse::whereIn('id', array_column($shortages, 'warehouse_id'))->get()->keyBy('id');

        return array_map(function (array $row) use ($products, $warehouses) {
            $product = $products->get($row['product_id']);

            return $row + [
                'product_name' => $product?->name_ar ?: ($product?->name_en ?: ('#'.$row['product_id'])),
                'sku' => $product?->sku,
                'warehouse_name' => $warehouses->get($row['warehouse_id'])?->name ?? ('#'.$row['warehouse_id']),
            ];
        }, $shortages);
    }

    public function store(Request $request, GoodsIssueService $goods): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|integer|exists:customers,id',
                'items' => 'required|array|min:1',
                // Who made the sale. Optional — a counter sale has no rep.
                'assigned_employee_id' => 'nullable|integer|exists:employees,id',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:500',
                'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
                // Where each line is taken from. Optional only so an install
                // with a single warehouse need not repeat itself; with more
                // than one, GoodsIssueService refuses to guess.
                'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'tax' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                // What the customer handed over. Anything short of the total
                // stays on their account as a receivable; anything over leaves
                // them in credit.
                'paid_amount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,card,transfer,check',
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

                // Where this line comes off the shelf. Named per line so a sale
                // can draw a fast-moving item from the branch and the rest from
                // the main store — and so the cost entry can credit the holding
                // the goods actually left.
                $lineWarehouseId = $goods->requireWarehouse(
                    $item['warehouse_id'] ?? ($validated['warehouse_id'] ?? null)
                );

                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $lineWarehouseId,
                    'product_name' => $product->name_ar ?? $product->name_en ?? 'منتج غير معروف',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'notes' => $item['notes'] ?? null,
                    'product_unit_id' => $item['product_unit_id'] ?? null,
                    'unit_name' => $unitName,
                    'unit_multiplier' => $unitMultiplier,
                    // Kept beside the line for the issue below; not a column.
                    '_unit_cost' => (float) ($product->cost_price ?? 0),
                ];

                $subtotal += $totalPrice;
            }

            /*
             * Refuse a sale the shelves cannot cover, before anything is written.
             *
             * The previous behaviour was to save the invoice and issue the stock
             * with `allow_negative`, which drove the warehouse below zero and
             * left a document claiming goods that were not there. A negative
             * shelf is not a smaller number — it is a record that has stopped
             * describing anything, and it corrupts every valuation and reorder
             * decision that reads it afterwards.
             */
            $shortages = $goods->shortagesFor(array_map(
                fn (array $line) => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                    'warehouse_id' => (int) $line['warehouse_id'],
                ],
                $itemsData
            ));

            if ($shortages !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'المخزون لا يغطي هذا البيع في المستودعات المحددة.',
                    // Named per product and warehouse so the screen can point at
                    // the exact line rather than making the seller hunt for it.
                    'data' => ['shortages' => $this->describeShortages($shortages)],
                ], 422);
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

            // Persist the charges that were folded into the total. Leaving this
            // unrecorded is what made subtotal + tax - discount fall short of
            // total, and it left the ledger crediting less revenue than it
            // debited receivables.
            $additionalCharges = round($expensesTotal, 5);

            $paidAmount = round((float) ($validated['paid_amount'] ?? 0), 5);

            // The header's warehouse, when every line agrees on one. A sale
            // split across warehouses (see items.*.warehouse_id) has no single
            // warehouse to report at the header level, so it stays null rather
            // than picking one line arbitrarily — Warehouse::invoices() and
            // reports that filter by warehouse_id rely on this being either
            // right or absent, never a guess.
            $lineWarehouseIds = collect($itemsData)->pluck('warehouse_id')->filter()->unique();
            $headerWarehouseId = $lineWarehouseIds->count() === 1 ? $lineWarehouseIds->first() : null;

            // Generate invoice number
            $invoice = new Invoice();
            $invoiceNumber = $invoice->generateInvoiceNumber();

            /*
             * Everything that has to exist together, or not at all.
             *
             * The header, its lines, the delivery charges raised with it and the
             * customer's settlement were written as separate statements with no
             * transaction around them. A failure part-way through the item loop
             * left a saved invoice whose stored subtotal and total had been
             * computed from lines that were never written — a document that
             * disagreed with itself, and a receivable posted for an amount
             * nothing on the invoice added up to.
             *
             * Stock issuing and ledger posting stay outside on purpose. Both
             * already swallow their own failures into warnings, because the
             * deliberate design here is that a bookkeeping problem must not
             * discard an invoice the user has successfully created.
             */
            [$invoice, $createdExpenses, $settlement, $stockWarnings] = DB::transaction(function () use (
                $validated,
                $itemsData,
                $invoiceNumber,
                $subtotal,
                $tax,
                $discount,
                $additionalCharges,
                $total,
                $paidAmount,
                $headerWarehouseId,
                $goods
            ) {
            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $validated['customer_id'] ?? null,
                'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
                'warehouse_id' => $headerWarehouseId,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'additional_charges' => $additionalCharges,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'due_amount' => max(0, round($total - $paidAmount, 5)),
                'payment_method' => $validated['payment_method'] ?? Invoice::PAYMENT_CASH,
                'status' => $validated['status'] ?? Invoice::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->check() ? auth()->id() : null,
                'paid_at' => ($validated['status'] ?? null) === Invoice::STATUS_DELIVERED ? now() : null,
            ]);

            // Create invoice items
            foreach ($itemsData as $itemData) {
                $itemData['invoice_id'] = $invoice->id;
                // Carried alongside the line for the stock issue, not a column.
                unset($itemData['_unit_cost']);
                InvoiceItem::create($itemData);
            }

            // Create expenses if provided.
            //
            // These are two separate economic events and both belong in the
            // books: the customer is billed for delivery (revenue, already in
            // the invoice entry) and the carrier has to be paid (a cost). Only
            // ExpenseController used to post these, so charges added through
            // the invoice form never reached the ledger at all.
            $createdExpenses = [];
            if (isset($validated['expenses']) && is_array($validated['expenses'])) {
                foreach ($validated['expenses'] as $expense) {
                    if (!empty($expense['description']) && $expense['amount'] > 0) {
                        $createdExpenses[] = Expense::create([
                            'expense_number' => 'EXP-' . str_pad(Expense::count() + 1, 6, '0', STR_PAD_LEFT),
                            'invoice_id' => $invoice->id,
                            'customer_id' => $invoice->customer_id,
                            'description' => $expense['description'],
                            'amount' => $expense['amount'],
                            'category' => $expense['category'] ?? 'other',
                            'expense_date' => now(),
                            'status' => 'pending',
                            'created_by' => auth()->check() ? auth()->id() : null,
                            // The books' own currency: a literal here made the
                            // expense claim a currency nobody had configured.
                            'currency' => base_currency_code(),
                            'exchange_rate' => 1.0000,
                        ]);
                    }
                }
            }

            /*
             * Take the goods off the named shelf, and tell the ledger what they
             * cost, in one step.
             *
             * Both halves were wrong before. The issue passed no warehouse, so
             * it fell through to "whichever warehouse has the lowest id" and
             * drew every sale from there regardless of where the goods were —
             * driving that one negative while the real one stayed full. And no
             * cost entry was posted at all, so every invoice raised here
             * overstated gross profit by the entire cost of the goods.
             *
             * `GoodsIssueService` is the same code the sales-order shipment
             * uses. Sharing it is the point: two copies of "move stock and post
             * its cost" is how the two paths end up disagreeing about margin.
             */
            $stockWarnings = [];

            try {
                $issued = $goods->issueAndPostCost(
                    lines: $invoice->items()->get()->map(fn ($line) => [
                        'product_id' => (int) $line->product_id,
                        'quantity' => (int) $line->quantity,
                        'warehouse_id' => (int) $line->warehouse_id,
                        'unit_cost' => (float) ($line->product?->cost_price ?? 0),
                        // Keyed per line, so a retry is a no-op rather than a
                        // second withdrawal.
                        'movement_key' => 'invoice:'.$invoice->id.':item:'.$line->id,
                    ])->all(),
                    postingKey: 'invoice_cogs:'.$invoice->id,
                    label: 'فاتورة '.$invoice->invoice_number,
                    reference: $invoice,
                    currency: $invoice->currency,
                    reason: 'بيع - فاتورة '.$invoice->invoice_number,
                    movementReference: 'invoice',
                    movementSource: $invoice->id,
                );

                $this->recordLineCosts($invoice, $issued['cost_by_key'] ?? []);
            } catch (\Throwable $e) {
                // Coverage was checked before the invoice was written, so this
                // is an unexpected failure rather than a routine shortfall. It
                // is surfaced instead of discarding a saved sale.
                $stockWarnings[] = $e->getMessage();
                report($e);
            }

            // Settle the customer's account.
            //
            // The sale puts the whole total on their receivable, then whatever
            // they actually paid comes straight back off it. Netting the two
            // instead of recording both would lose the payment entirely — there
            // would be no cash movement anywhere in the books.
            $settlement = $this->settleCustomerAccount($invoice, $paidAmount, $validated['payment_method'] ?? null);

                return [$invoice, $createdExpenses, $settlement, $stockWarnings];
            });

            // Feed the general ledger. Posting is idempotent and balanced or it
            // throws, so a ledger problem must not silently swallow a saved
            // invoice — it is reported alongside the successful creation.
            $postingError = null;
            $ledger = app(\App\Services\Accounting\LedgerPostingService::class);
            try {
                $ledger->postInvoice($invoice);
                if ($settlement['payment']) {
                    $ledger->postPayment($settlement['payment']);
                }
                foreach ($createdExpenses as $createdExpense) {
                    $ledger->postExpense($createdExpense);
                }
            } catch (\Throwable $e) {
                $postingError = $e->getMessage();
                report($e);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفاتورة بنجاح',
                'data' => new InvoiceResource($invoice->fresh()->load('items.product')),
                'settlement' => [
                    'total' => round($total, 5),
                    'paid' => $paidAmount,
                    // Positive: still owed by the customer. Negative: overpaid,
                    // so they now hold credit with us.
                    'remaining' => round($total - $paidAmount, 5),
                    'payment_number' => $settlement['payment']?->payment_number,
                    'customer_balance' => $settlement['customer_balance'],
                ],
                'accounting_warning' => $postingError,
                'inventory_warnings' => $stockWarnings ?: null,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (RuntimeException $e) {
            // A sale that cannot name its source — no warehouse chosen and more
            // than one to choose from, or none set up at all. That is something
            // the seller can fix, so it is stated plainly rather than dressed up
            // as a server fault.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
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
    public function update(Request $request, Invoice $invoice, GoodsIssueService $goods): JsonResponse
    {
        // Filled by the re-issue below when a shelf cannot cover the edited
        // lines. The refusal has to travel out through the rollback, so it is
        // read again in the catch rather than returned from inside it.
        $shortages = [];

        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|integer|exists:customers,id',
                'items' => 'nullable|array|min:1',
                'items.*.product_id' => 'required_with:items|integer|exists:products,id',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.unit_price' => 'required_with:items|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:500',
                'items.*.product_unit_id' => 'nullable|integer|exists:product_units,id',
                // Accepted here as it is on creation. The client has always
                // sent it; this end never read it, so every edit silently
                // stripped the warehouse off every line — losing the record of
                // which shelf the goods left, which is the only thing tying a
                // line to the stock movement that filled it.
                'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'tax' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,card,transfer,check',
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
                        'warehouse_id' => $item['warehouse_id'] ?? $invoice->warehouse_id,
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

                // Folded into the total on creation and never on edit, which
                // left subtotal + tax - discount short of total and the ledger
                // crediting less revenue than it debited receivables.
                $additionalCharges = round($expensesTotal, 5);

                // The sale as the books currently have it. The correction
                // posted at the end is the difference between this and what
                // the edit leaves behind.
                $before = [
                    'total' => (float) $invoice->total,
                    'tax' => (float) $invoice->tax,
                    'charges' => (float) ($invoice->additional_charges ?? 0),
                ];

                // See store(): a sale split across warehouses has no single
                // warehouse to report at the header, so it stays null.
                $lineWarehouseIds = collect($itemsData)->pluck('warehouse_id')->filter()->unique();
                $headerWarehouseId = $lineWarehouseIds->count() === 1 ? $lineWarehouseIds->first() : null;

                // A cancelled invoice has already given its goods back and had
                // its entries reversed. Re-issuing against it would take the
                // stock out again for a sale that is not happening.
                $settles = $invoice->status !== Invoice::STATUS_CANCELLED
                    && ($validated['status'] ?? $invoice->status) !== Invoice::STATUS_CANCELLED;

                /*
                 * Everything the edit touches, or none of it.
                 *
                 * Editing used to rewrite the rows and stop there: the goods
                 * stayed off the shelves in the quantities first rung up, and
                 * the ledger went on carrying the original revenue, receivable
                 * and cost. Changing a quantity therefore left the invoice, the
                 * warehouse and the books each describing a different sale, and
                 * nothing anywhere said so.
                 *
                 * So the goods that actually left come back, the edited lines
                 * are issued in their place, and the books are told the
                 * difference. Unlike creation, none of this is allowed to fail
                 * into a warning: an edit that cannot be settled must leave the
                 * invoice exactly as it was rather than half-applied.
                 */
                DB::transaction(function () use (
                    $invoice, $itemsData, $validated, $goods, $settles, $before,
                    $subtotal, $tax, $discount, $additionalCharges, $total, $headerWarehouseId, &$shortages
                ) {
                // Put back exactly what left, at what it cost when it left, and
                // dated so it returns to its own place in the queue.
                $returned = $settles ? $this->returnIssuedGoods($invoice, $goods) : ['cost_by_warehouse' => []];

                // The rewritten lines start uncosted. Where the edit
                // settles, the reissue below measures them again; where it does
                // not — a cancelled invoice, whose goods are already back — no
                // issue stands behind them, and the report saying so is more
                // use than a figure carried over from a sale that was undone.
                $invoice->items()->delete();
                foreach ($itemsData as $itemData) {
                    $itemData['invoice_id'] = $invoice->id;
                    InvoiceItem::create($itemData);
                }

                $invoice->update([
                    'customer_id' => $validated['customer_id'] ?? $invoice->customer_id,
                    'warehouse_id' => $headerWarehouseId,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'additional_charges' => $additionalCharges,
                    'total' => $total,
                    'due_amount' => max(0, round($total - (float) $invoice->paid_amount, 5)),
                    'payment_method' => $validated['payment_method'] ?? $invoice->payment_method,
                    'status' => $validated['status'] ?? $invoice->status,
                    'notes' => $validated['notes'] ?? $invoice->notes,
                ]);

                if ($settles) {
                    // Checked after the return, so the units this invoice is
                    // giving back count towards covering what it now asks for.
                    $shortages = $goods->shortagesFor(array_map(
                        fn (array $line) => [
                            'product_id' => (int) $line['product_id'],
                            'quantity' => (int) $line['quantity'],
                            'warehouse_id' => (int) $line['warehouse_id'],
                        ],
                        $itemsData
                    ));

                    if ($shortages !== []) {
                        // Unwinds the return and the rewritten lines with it.
                        throw new RuntimeException('المخزون لا يغطي هذا البيع في المستودعات المحددة.');
                    }

                    $this->resettleGoods($invoice, $goods, $returned['cost_by_warehouse']);
                    $this->correctInvoicePosting($invoice, $before);
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
                                'currency' => base_currency_code(),
                                'exchange_rate' => 1.0000,
                            ]);
                        }
                    }
                }
                });
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
            // The edit was refused because a shelf could not cover it, and the
            // rollback has already put the invoice back as it was. Named per
            // product and warehouse, as creation does, so the screen can point
            // at the line rather than making the seller hunt for it.
            if ($shortages !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'المخزون لا يغطي هذا البيع في المستودعات المحددة.',
                    'data' => ['shortages' => $this->describeShortages($shortages)],
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث الفاتورة',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Writes what the goods actually cost onto the lines that sold them.
     *
     * The cost comes out of the FIFO layers the issue consumed, so it is what
     * this sale really took off the shelf rather than what the catalogue says
     * the product costs today. Recording it here is what lets profit be
     * reported from the invoice itself, and keeps that figure fixed once the
     * document is closed — re-pricing a product afterwards no longer rewrites
     * the margin of every sale it ever appeared in.
     *
     * @param  array<string,array{quantity:int, cost:float}>  $costByKey
     */
    private function recordLineCosts(Invoice $invoice, array $costByKey): void
    {
        if ($costByKey === []) {
            return;
        }

        foreach ($invoice->items()->get() as $line) {
            $issued = $costByKey['invoice:'.$invoice->id.':item:'.$line->id] ?? null;

            if ($issued === null) {
                continue;
            }

            $quantity = (int) ($issued['quantity'] ?: $line->quantity);
            $cost = round((float) $issued['cost'], 5);

            $line->forceFill([
                'total_cost' => $cost,
                'unit_cost' => $quantity > 0 ? round($cost / $quantity, 5) : 0,
            ])->save();
        }
    }

    /**
     * Puts back what this invoice actually took, ready for it to be reissued.
     *
     * Driven by the stock movements rather than the invoice lines, because
     * those are what really happened: a line whose issue failed at the time
     * moved nothing, and returning goods against it would credit the warehouse
     * with units it never gave up. Each return is dated as the goods first
     * arrived, so they go back to their own place in the FIFO queue and a
     * reissue of the same units takes the same batch at the same cost.
     *
     * @return array{cost: float, cost_by_warehouse: array<int,float>}
     */
    private function returnIssuedGoods(Invoice $invoice, GoodsIssueService $goods): array
    {
        $lines = $invoice->items()->get();

        if ($lines->isEmpty()) {
            return ['cost' => 0.0, 'cost_by_warehouse' => []];
        }

        $issued = StockMovement::whereIn(
            'movement_key',
            $lines->map(fn ($line) => 'invoice:'.$invoice->id.':item:'.$line->id)->all()
        )->where('movement_type', StockMovement::TYPE_OUT)->get()->keyBy('movement_key');

        $returns = [];

        foreach ($lines as $line) {
            $movement = $issued->get('invoice:'.$invoice->id.':item:'.$line->id);

            if (! $movement) {
                continue;
            }

            $returns[] = [
                'product_id' => (int) $movement->product_id,
                'quantity' => (int) $movement->quantity,
                'warehouse_id' => (int) $movement->warehouse_id,
                'unit_cost' => (float) $movement->unit_cost,
                'received_at' => $movement->created_at,
                // Line ids are never reused, so this is unique however many
                // times the invoice is edited.
                'movement_key' => 'invoice_edit:'.$invoice->id.':item:'.$line->id,
            ];
        }

        return $returns === []
            ? ['cost' => 0.0, 'cost_by_warehouse' => []]
            : $goods->returnGoods($returns, 'تعديل فاتورة '.$invoice->invoice_number, 'تعديل بيع - فاتورة '.$invoice->invoice_number);
    }

    /**
     * Issues the edited lines and tells the books what the cost did.
     *
     * The issue posts nothing itself: the original cost entry stands, and what
     * is recorded here is the difference between what the goods cost then and
     * what they cost now, warehouse by warehouse.
     *
     * @param  array<int,float>  $returnedByWarehouse  what came back, per warehouse
     */
    private function resettleGoods(Invoice $invoice, GoodsIssueService $goods, array $returnedByWarehouse): void
    {
        $issued = $goods->issueAndPostCost(
            lines: $invoice->items()->get()->map(fn ($line) => [
                'product_id' => (int) $line->product_id,
                'quantity' => (int) $line->quantity,
                'warehouse_id' => (int) $line->warehouse_id,
                'unit_cost' => (float) ($line->product?->cost_price ?? 0),
                'movement_key' => 'invoice:'.$invoice->id.':item:'.$line->id,
            ])->all(),
            postingKey: 'invoice_cogs:'.$invoice->id,
            label: 'فاتورة '.$invoice->invoice_number,
            reference: $invoice,
            currency: $invoice->currency,
            reason: 'بيع - فاتورة '.$invoice->invoice_number,
            movementReference: 'invoice',
            movementSource: $invoice->id,
            // The correction below says what changed; posting the whole cost
            // again would state it twice.
            postCost: false,
        );

        $this->recordLineCosts($invoice, $issued['cost_by_key'] ?? []);

        $delta = $issued['cost_by_warehouse'];

        foreach ($returnedByWarehouse as $warehouseId => $cost) {
            $delta[$warehouseId] = ($delta[$warehouseId] ?? 0) - $cost;
        }

        app(LedgerPostingService::class)->postCostOfGoodsSoldCorrection(
            key: $this->correctionKey($invoice, 'invoice_cogs_adjust'),
            deltaByWarehouse: $delta,
            label: 'فاتورة '.$invoice->invoice_number,
            reference: $invoice,
            currency: $invoice->currency,
        );
    }

    /**
     * Records what the edit did to the sale itself — the receivable, the
     * revenue, the charges and the tax — and to the customer's account.
     *
     * @param  array{total: float, tax: float, charges: float}  $before
     */
    private function correctInvoicePosting(Invoice $invoice, array $before): void
    {
        $invoice->refresh();

        $after = [
            'total' => (float) $invoice->total,
            'tax' => (float) $invoice->tax,
            'charges' => (float) ($invoice->additional_charges ?? 0),
        ];

        // The customer owes the difference, or is owed it. Skipped for an
        // invoice raised from a sales order, which is where its balance was
        // accounted for — the same condition creation settles under.
        $difference = round($after['total'] - $before['total'], 5);

        if (abs($difference) >= 0.000005 && ! $invoice->sales_order_id && $invoice->customer_id) {
            Customer::find($invoice->customer_id)?->updateBalance($difference);
        }

        app(LedgerPostingService::class)->postInvoiceCorrection(
            invoice: $invoice,
            key: $this->correctionKey($invoice, 'invoice_adjust'),
            before: $before,
            after: $after,
        );
    }

    /**
     * A key for the next correction on this invoice.
     *
     * Numbered from what is already posted rather than from a counter on the
     * invoice, so it stays right however the document got here — and so an
     * edit that is rolled back leaves no gap behind it.
     */
    private function correctionKey(Invoice $invoice, string $prefix): string
    {
        $posted = JournalEntryHeader::where('posting_key', 'like', $prefix.':'.$invoice->id.':%')->count();

        return $prefix.':'.$invoice->id.':'.($posted + 1);
    }

    /**
     * Update invoice status (pay or cancel)
     */
    public function updateStatus(Request $request, Invoice $invoice, GoodsIssueService $goods): JsonResponse
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

            /*
             * Cancelling has to undo what the sale did, not merely relabel it.
             *
             * This used to flip the status and clear `paid_at`, and nothing
             * else: the goods stayed off the shelf and the ledger went on
             * carrying the revenue, the receivable and the cost. A cancelled
             * invoice was therefore indistinguishable, in the books and in the
             * warehouse, from one that had been fulfilled.
             *
             * Guarded on the previous status so cancelling twice does not return
             * the goods twice — and the movement keys differ from the issue's,
             * or the return would be read as a repeat of it and skipped.
             */
            $isCancelling = $newStatus === Invoice::STATUS_CANCELLED
                && $oldStatus !== Invoice::STATUS_CANCELLED;

            if ($isCancelling) {
                DB::transaction(function () use ($invoice, $goods) {
                    $goods->returnAndReverseCost(
                        lines: $invoice->items()->with('product')->get()->map(fn ($line) => [
                            'product_id' => (int) $line->product_id,
                            'quantity' => (int) $line->quantity,
                            'warehouse_id' => (int) $line->warehouse_id,
                            'unit_cost' => (float) ($line->product?->cost_price ?? 0),
                            'movement_key' => 'invoice_cancel:'.$invoice->id.':item:'.$line->id,
                        ])->all(),
                        postingKey: 'invoice_cogs:'.$invoice->id,
                        label: 'إلغاء فاتورة '.$invoice->invoice_number,
                        reason: 'إلغاء بيع - فاتورة '.$invoice->invoice_number,
                    );

                    /*
                     * The sale itself: the receivable and the revenue.
                     *
                     * The customer's payment is deliberately *not* reversed. The
                     * cash genuinely moved, and saying otherwise would make the
                     * books claim money that is sitting in the till never
                     * arrived. Reversing only the sale leaves the payment
                     * standing against a receivable that no longer exists, so
                     * the customer's account shows a credit — which is exactly
                     * what they hold: a refund owed to them. Paying it back is a
                     * separate, deliberate act.
                     */
                    $ledger = app(\App\Services\Accounting\LedgerPostingService::class);
                    $ledger->reverseFor('invoice:'.$invoice->id);
                });
            }

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
                'message' => $isCancelling
                    ? 'أُلغيت الفاتورة: أُعيدت الكميات إلى مستودعاتها وعُكست القيود المحاسبية.'
                    : 'تم تحديث حالة الفاتورة بنجاح',
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
