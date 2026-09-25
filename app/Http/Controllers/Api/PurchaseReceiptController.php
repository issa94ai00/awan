<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrder;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Purchasing\PurchaseOrderCostSync;
use App\Services\Purchasing\SupplierPaymentRecorder;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptController extends Controller
{
    public function __construct(
        private LedgerPostingService $ledger,
        private SupplierPaymentRecorder $payments,
    ) {
    }

    public function index(Request $request)
    {
        $query = PurchaseReceipt::with(['purchaseOrder', 'supplier', 'creator', 'items.product', 'items.variant', 'warehouse', 'payments']);

        if ($request->has('supplier_id') && $request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $receipts = $query->latest()->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return response()->json([
            'success' => true,
            'message' => 'Purchase receipts retrieved successfully',
            'data' => [
                // Each with what it cost, what was paid and how.
                'receipts' => collect($receipts->items())->map->toArrayWithPayments()->all(),
                'pagination' => [
                    'current_page' => $receipts->currentPage(),
                    'last_page' => $receipts->lastPage(),
                    'per_page' => $receipts->perPage(),
                    'total' => $receipts->total(),
                    'has_more_pages' => $receipts->hasMorePages(),
                ]
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'receipt_date' => 'nullable|date',
            // Tax charged by the supplier, kept apart from what the goods cost:
            // it is recoverable from the tax authority, not part of the stock's
            // value.
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.sale_price' => 'nullable|numeric|min:0',
            // Paid as the goods are booked in, in part or in full. Recorded as
            // a supplier payment linked to this receipt, in the same
            // transaction — so the receipt and the payment stand or fall
            // together.
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:'.implode(',', SupplierPayment::METHODS),
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $paid = round((float) ($validated['paid_amount'] ?? 0), 2);
        if ($paid > 0 && empty($validated['payment_method'])) {
            throw ValidationException::withMessages(['payment_method' => 'اختر طريقة الدفع للمبلغ المدفوع']);
        }
        $payment = [
            'payment_method' => $validated['payment_method'] ?? null,
            'reference' => $validated['payment_reference'] ?? null,
        ];
        unset($validated['paid_amount'], $validated['payment_method'], $validated['payment_reference']);

        // Paying suppliers is an admin task — the payments screen is behind
        // role:admin — and receiving goods is not. A receipt must not become
        // the way round that.
        if ($paid > 0 && ! $request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'تسجيل الدفع للمورّد من صلاحيات المدير — احفظ الإيصال دون مبلغ مدفوع.',
                'errors' => ['paid_amount' => ['تسجيل الدفع للمورّد من صلاحيات المدير']],
            ], 403);
        }

        $receiptTotal = round(
            collect($validated['items'])->sum(fn ($item) => (int) $item['quantity'] * (float) $item['unit_price'])
            + (float) ($validated['tax_amount'] ?? 0),
            2
        );
        if ($paid > $receiptTotal + 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'المبلغ المدفوع أكبر من قيمة الإيصال',
                'errors' => ['paid_amount' => ['المبلغ المدفوع أكبر من قيمة الإيصال ('.number_format($receiptTotal, 2).')']],
            ], 422);
        }

        $variants = ProductVariant::forLines($validated['items']);

        // Stock is taken in once per product (or variant) per receipt — the
        // intake key is receipt + product + variant — so a second line for the
        // same thing was silently dropped from the warehouse while still being
        // billed. Two sizes of one product are two different things.
        $seen = [];
        foreach ($validated['items'] as $index => $item) {
            $key = PurchaseOrderCostSync::lineKey($item['product_id'], $item['product_variant_id'] ?? null);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'المنتج مكرر في أكثر من سطر — اجمعه في سطر واحد',
                ]);
            }
            $seen[$key] = true;
        }

        // The receipt credits the supplier it names and completes the order it
        // links, so the two have to agree — and a cancelled order was promised
        // never to be received against.
        if (!empty($validated['purchase_order_id'])) {
            $order = PurchaseOrder::find($validated['purchase_order_id']);

            if ((int) $order->supplier_id !== (int) $validated['supplier_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الشراء المرتبط يخص مورداً آخر',
                    'errors' => ['purchase_order_id' => ['طلب الشراء المرتبط يخص مورداً آخر']],
                ], 422);
            }

            if (PurchaseOrder::normalizeStatus($order->status) === PurchaseOrder::STATUS_CANCELLED) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن استلام بضاعة لطلب شراء ملغى',
                    'errors' => ['purchase_order_id' => ['لا يمكن استلام بضاعة لطلب شراء ملغى']],
                ], 422);
            }
        }

        // Derived from the last id: counting reuses a number as soon as any
        // receipt is deleted, and two concurrent requests always collide.
        $validated['receipt_number'] = 'PR-' . str_pad(
            (string) (((int) PurchaseReceipt::max('id')) + 1),
            6,
            '0',
            STR_PAD_LEFT
        );
        $validated['created_by'] = auth()->id();

        $inventory = app(\App\Services\Inventory\InventoryService::class);

        try {
            $receipt = DB::transaction(function () use ($validated, $inventory, $variants, $paid, $payment) {
                $receipt = PurchaseReceipt::create($validated);

                $products = Product::whereIn('id', collect($validated['items'])->pluck('product_id'))->get()->keyBy('id');

                foreach ($validated['items'] as $item) {
                    $variant = $variants->get((int) ($item['product_variant_id'] ?? 0));
                    $product = $products->get($item['product_id']);

                    $receipt->items()->create([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $variant?->id,
                        // Says which size came in, on the receipt and its print.
                        'description' => $variant ? $variant->displayName($product?->name_ar ?? $product?->name_en) : null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'sale_price' => $item['sale_price'] ?? null,
                        'total' => $item['quantity'] * $item['unit_price'],
                    ]);
                }

                // Take the goods into stock. Previously a model hook on the receipt
                // item did this on every save — including updates, so editing a
                // receipt re-added the whole quantity. Receiving now runs once per
                // receipt, keyed per item so a resubmit is a no-op, and flows
                // through InventoryService so the warehouse row and the product
                // total agree.
                $warehouseId = $receipt->warehouse_id ?: $inventory->defaultWarehouseId();

                foreach ($validated['items'] as $item) {
                    $variant = $variants->get((int) ($item['product_variant_id'] ?? 0));

                    // Warehouse stock and the product's average cost stay per
                    // product. What belongs to one size — its own count, what it
                    // cost and what it sells for — goes on the variant below.
                    if ($variant) {
                        $this->receiveIntoVariant($variant, $item);
                    }

                    $inventory->receive(
                        $item['product_id'],
                        $item['quantity'],
                        $warehouseId,
                        [
                            'key' => 'purchase_receipt:' . $receipt->id . ':item:' . $item['product_id']
                                . ($variant ? ':variant:' . $variant->id : ''),
                            'reference' => $receipt->receipt_number,
                            'source' => 'purchase_receipt',
                            'reason' => 'استلام من أمر شراء',
                            'unit_cost' => $item['unit_price'],
                            'created_by' => auth()->id(),
                            // A purchase is real money paid, so it should move the
                            // product's reference cost — a weighted average with
                            // what was already on hand — not just open a FIFO
                            // layer for this warehouse.
                            'update_average_cost' => true,
                            // If the operator set a shelf price on this line, receiving
                            // the goods is what puts it into effect. A variant's
                            // price is its own, set above — not the product's.
                            'sale_price' => $variant ? null : ($item['sale_price'] ?? null),
                        ]
                    );
                }

                // Persist the warehouse actually used, so this receipt can be
                // reversed later without guessing — and so the posting below knows
                // which warehouse's inventory account to debit. This used to happen
                // after the posting, which left a receipt that arrived without an
                // explicit warehouse booked to the pooled account while its stock
                // went into the default one: the very mismatch the per-warehouse
                // debit exists to prevent.
                if (!$receipt->warehouse_id && $warehouseId) {
                    $receipt->update(['warehouse_id' => $warehouseId]);
                }

                // The receipt is what puts the goods on the balance sheet. Without
                // this the inventory account was only ever credited — by sales —
                // and drifted negative no matter how full the warehouse was.
                $receipt->load('items');
                $this->ledger->postGoodsReceipt($receipt);

                // Bought on account, so the supplier is now owed for it — including
                // the tax, which is part of what the invoice has to be paid at even
                // though the books carry it separately from the goods.
                $receipt->supplier?->updateBalance(
                    $receipt->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price)
                    + (float) ($receipt->tax_amount ?? 0)
                );

                // Receiving goods against a linked order is what completes it: the
                // order was a promise to buy, and this receipt is that promise
                // kept. Skips a cancelled order rather than resurrecting it —
                // goods should never have been received against one anyway.
                if ($receipt->purchase_order_id) {
                    PurchaseOrder::whereKey($receipt->purchase_order_id)
                        ->where('status', '!=', 'cancelled')
                        ->update([
                            'status' => 'completed',
                            'received_date' => $receipt->receipt_date ?? now(),
                        ]);

                    // And it settles what the order cost. Until now the only
                    // record of that was the receipt, so purchase reporting went
                    // on costing the order at the price it was placed at, however
                    // much of it actually turned up or at whatever price.
                    app(PurchaseOrderCostSync::class)->syncFromReceipt($receipt);
                }

                // What was paid on the spot comes straight off what was just owed.
                if ($paid > 0 && $receipt->supplier) {
                    $this->payments->record($receipt->supplier, $payment + [
                        'amount' => $paid,
                        'purchase_receipt_id' => $receipt->id,
                        'purchase_order_id' => $receipt->purchase_order_id,
                        'payment_date' => $receipt->receipt_date?->toDateString(),
                        'notes' => 'دفعة عند استلام '.$receipt->receipt_number,
                    ]);
                }

                return $receipt;
            });
        } catch (\RuntimeException $e) {
            // The ledger could not post the receipt or its payment: nothing
            // was kept, stock included.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }

        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product', 'items.variant', 'warehouse', 'payments']);

        return response()->json([
            'success' => true,
            'message' => $paid > 0
                ? 'تم إنشاء إيصال الاستلام وتسجيل الدفعة، وأُدخلت البضاعة للمخزون ورُحّل قيدها المحاسبي'
                : 'تم إنشاء إيصال الاستلام بنجاح، وأُدخلت البضاعة للمخزون ورُحّل قيدها المحاسبي',
            'data' => $receipt->toArrayWithPayments(),
        ], 201);
    }

    public function show(PurchaseReceipt $receipt)
    {
        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product', 'items.variant', 'payments']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase receipt retrieved successfully',
            'data' => $receipt->toArrayWithPayments(),
        ]);
    }

    /**
     * What a receipt line changes on the variant it names: its own stock
     * count goes up, its cost becomes the weighted average of what was on
     * hand and what just arrived, and a shelf price set on the line becomes
     * its price.
     *
     * @param  array<string, mixed>  $item
     */
    private function receiveIntoVariant(ProductVariant $variant, array $item): void
    {
        $variant->refresh();

        $onHand = max(0, (int) $variant->stock_quantity);
        $quantity = (int) $item['quantity'];
        $unitCost = (float) $item['unit_price'];
        $oldCost = $variant->cost_price !== null ? (float) $variant->cost_price : null;

        $variant->cost_price = ($oldCost === null || $onHand === 0)
            ? $unitCost
            : round(($onHand * $oldCost + $quantity * $unitCost) / ($onHand + $quantity), 5);

        if (isset($item['sale_price']) && $item['sale_price'] !== null && $item['sale_price'] !== '') {
            $variant->price = $item['sale_price'];
        }

        $variant->save();

        ProductVariant::adjustStockCount($variant->id, $quantity);
    }

    /**
     * Get purchase order details for auto-filling receipt items
     */
    public function getPurchaseOrderDetails($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.product', 'items.variant', 'supplier'])
            ->withCount('receipts')
            ->find($purchaseOrderId);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'طلب الشراء المحدد غير موجود',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات طلب الشراء بنجاح',
            'data' => [
                'purchase_order' => $purchaseOrder,
                'supplier_id' => $purchaseOrder->supplier_id,
                // Whether goods may still come in against it, so the form can
                // say so before the operator fills anything in.
                'receivable' => PurchaseOrder::normalizeStatus($purchaseOrder->status) !== PurchaseOrder::STATUS_CANCELLED,
                // What an earlier receipt already brought in — the cost sync
                // settles it onto each line — so a second delivery is
                // prefilled with what is still owed, not the whole order again.
                'items' => $purchaseOrder->items->map(function ($item) {
                    $received = (int) ($item->received_quantity ?? 0);

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'variant_label' => $item->variant?->label,
                        'variant_sku' => $item->variant?->sku,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'received_quantity' => $received,
                        'remaining_quantity' => max(0, (int) $item->quantity - $received),
                        'unit_price' => $item->unit_price,
                        'sale_price' => $item->sale_price,
                        'total_price' => $item->total_price,
                        'product' => $item->product,
                    ];
                }),
            ]
        ]);
    }

    /**
     * A receipt records goods that physically arrived, so its lines are not
     * freely editable: the stock was already taken in against them and the cost
     * posted to the ledger. Rewriting the lines here changed neither, leaving
     * the document describing quantities the warehouse and the books had never
     * seen. Only the descriptive fields can be corrected.
     */
    public function update(Request $request, PurchaseReceipt $receipt)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
        ]);

        if ($request->filled('items')) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل بنود إيصال استلام بعد إدخال البضاعة للمخزون. سجّل تسوية مخزنية أو إيصال إرجاع بدلاً من ذلك.',
                'data' => null,
            ], 422);
        }

        $receipt->update(collect($validated)->except('items')->all());
        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product', 'items.variant', 'warehouse', 'payments']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات إيصال الاستلام',
            'data' => $receipt->toArrayWithPayments(),
        ]);
    }

    /**
     * Deleting a receipt would strand its journal entry against a document that
     * no longer exists and leave the goods on the shelf with nothing explaining
     * where they came from.
     */
    public function destroy(PurchaseReceipt $receipt)
    {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن حذف إيصال استلام أُدخلت بضاعته للمخزون ورُحّل قيده. سجّل إرجاعاً للمورّد لعكس أثره.',
            'data' => null,
        ], 422);
    }
}
