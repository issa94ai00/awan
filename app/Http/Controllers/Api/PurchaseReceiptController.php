<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptController extends Controller
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    public function index(Request $request)
    {
        $query = PurchaseReceipt::with(['purchaseOrder', 'supplier', 'creator', 'items.product', 'warehouse']);

        if ($request->has('supplier_id') && $request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $receipts = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Purchase receipts retrieved successfully',
            'data' => [
                'receipts' => $receipts->items(),
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
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.sale_price' => 'nullable|numeric|min:0',
        ]);

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

        $receipt = DB::transaction(function () use ($validated, $request, $inventory) {
            $receipt = PurchaseReceipt::create($validated);

            foreach ($request->items as $item) {
                $receipt->items()->create([
                    'product_id' => $item['product_id'],
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

            foreach ($request->items as $item) {
                $inventory->receive(
                    $item['product_id'],
                    $item['quantity'],
                    $warehouseId,
                    [
                        'key' => 'purchase_receipt:' . $receipt->id . ':item:' . $item['product_id'],
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
                        // the goods is what puts it into effect.
                        'sale_price' => $item['sale_price'] ?? null,
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
            }

            return $receipt;
        });

        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product', 'warehouse']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء إيصال الاستلام بنجاح، وأُدخلت البضاعة للمخزون ورُحّل قيدها المحاسبي',
            'data' => $receipt
        ], 201);
    }

    public function show(PurchaseReceipt $receipt)
    {
        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase receipt retrieved successfully',
            'data' => $receipt
        ]);
    }

    /**
     * Get purchase order details for auto-filling receipt items
     */
    public function getPurchaseOrderDetails($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.product', 'supplier'])->find($purchaseOrderId);

        if (!$purchaseOrder) {
            return response()->json([
                'success' => false,
                'message' => 'أمر الشراء المحدد غير موجود',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات أمر الشراء بنجاح',
            'data' => [
                'purchase_order' => $purchaseOrder,
                'supplier_id' => $purchaseOrder->supplier_id,
                'items' => $purchaseOrder->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
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
        $receipt->load(['purchaseOrder', 'supplier', 'creator', 'items.product', 'warehouse']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات إيصال الاستلام',
            'data' => $receipt
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
