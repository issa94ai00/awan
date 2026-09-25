<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // receipts_count tells the list whether an order's goods already
        // arrived, so a completed row can point at its receipt instead of
        // offering a receive action that would double-count the stock.
        $query = PurchaseOrder::with(['supplier', 'items.product', 'items.variant'])->withCount('receipts');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Matches every spelling of the requested stage, so an order saved as
        // 'ordered' by an older version of this screen still appears under
        // 'confirmed' rather than falling out of every tab.
        if ($request->filled('status')) {
            $query->whereIn('status', $this->statusAliases($request->input('status')));
        }

        // Searching hits the table rather than the twenty rows the browser
        // happened to hold, so an order on page three can still be found.
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $perPage = min((int) $request->input('per_page', 20) ?: 20, 500);

        $purchaseOrders = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Purchase orders retrieved successfully',
            'data' => [
                'orders' => $purchaseOrders->items(),
                // Counted over the whole table: a badge that only counted the
                // current page would say nothing about how much is waiting.
                'status_counts' => $this->statusCounts(),
                'pagination' => [
                    'current_page' => $purchaseOrders->currentPage(),
                    'last_page' => $purchaseOrders->lastPage(),
                    'per_page' => $purchaseOrders->perPage(),
                    'total' => $purchaseOrders->total(),
                    'has_more_pages' => $purchaseOrders->hasMorePages(),
                ],
            ],
        ]);
    }

    /** Every stored spelling that means the given stage. */
    private function statusAliases(string $status): array
    {
        $stage = PurchaseOrder::normalizeStatus($status);

        $aliases = array_keys(
            array_filter(PurchaseOrder::LEGACY_STATUSES, fn ($mapped) => $mapped === $stage)
        );

        return array_values(array_unique([$stage, ...$aliases]));
    }

    private function statusCounts(): array
    {
        $counts = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStage = array_fill_keys(PurchaseOrder::STATUSES, 0);

        foreach ($counts as $status => $total) {
            $stage = PurchaseOrder::normalizeStatus((string) $status);
            // An unrecognised status still counts towards the total, it just
            // has no tab of its own.
            if (array_key_exists($stage, $byStage)) {
                $byStage[$stage] += (int) $total;
            }
        }

        return ['all' => (int) $counts->sum()] + $byStage;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                // A new order starts pending or approved; completion is a
                // goods receipt's to write, and a cancelled order is not new.
                'status' => 'nullable|string|in:' . PurchaseOrder::STATUS_PENDING . ',' . PurchaseOrder::STATUS_CONFIRMED,
                // Sent by the form all along, but never validated — so
                // validated() dropped it and every order was saved undated.
                'order_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:order_date',
                'discount' => 'nullable|numeric|min:0',
                'tax' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.sale_price' => 'nullable|numeric|min:0',
                'items.*.notes' => 'nullable|string|max:500',
            ], [
                'supplier_id.required' => 'يجب اختيار المورد',
                'supplier_id.exists' => 'المورد المحدد غير موجود',
                'items.required' => 'يجب إضافة منتج واحد على الأقل',
                'items.*.product_id.required' => 'يجب تحديد المنتج',
                'items.*.product_id.exists' => 'المنتج المحدد غير موجود',
                'items.*.quantity.required' => 'يجب تحديد الكمية',
                'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
                'items.*.unit_price.required' => 'يجب تحديد سعر الوحدة',
                'items.*.unit_price.min' => 'سعر الوحدة يجب أن يكون 0 على الأقل',
            ]);

            $validated['order_number'] = $this->nextOrderNumber();
            $validated['status'] = $validated['status'] ?? 'pending';
            $validated['order_date'] = $validated['order_date'] ?? now()->toDateString();
            $validated['created_by'] = auth()->id();

            $this->applyTotals($validated);
            ProductVariant::forLines($validated['items']);

            // The header carries totals computed from the lines, so the two must
            // land together. Written separately, a failure inside the loop left
            // an order whose subtotal described items that do not exist.
            $order = DB::transaction(function () use ($validated) {
                $order = PurchaseOrder::create($validated);

                $this->createLines($order, $validated['items']);

                return $order;
            });

            $order->load(['supplier', 'items.product', 'items.variant']);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب الشراء بنجاح',
                'data' => $order,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء طلب الشراء',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(PurchaseOrder $order): JsonResponse
    {
        $order->load(['supplier', 'items.product', 'items.variant', 'receipts']);
        $order->loadCount('receipts');

        return response()->json([
            'success' => true,
            'message' => 'Purchase order retrieved successfully',
            'data' => $order,
        ]);
    }

    public function update(Request $request, PurchaseOrder $order): JsonResponse
    {
        $current = PurchaseOrder::normalizeStatus($order->status);

        /*
         * A received order's lines are what its receipt booked into stock, and
         * carry the landed cost the receipt settled onto them. Rewriting them —
         * update() deletes every line and inserts it again — threw that cost
         * away and let the order describe goods other than the ones that came.
         * A cancelled order has nothing left to change either. Both may still
         * have their notes and dates corrected, and nothing else.
         */
        if (in_array($current, [PurchaseOrder::STATUS_COMPLETED, PurchaseOrder::STATUS_CANCELLED], true)) {
            $validated = $request->validate([
                'order_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
            ]);

            $order->update($validated);
            $order->load(['supplier', 'items.product', 'items.variant']);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $order,
            ]);
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            // Optional: the form no longer carries a status, since approving
            // and cancelling have their own endpoint. When one is sent it has
            // to be a move the workflow allows, checked below.
            'status' => 'sometimes|nullable|string|in:' . implode(',', $this->writableStatuses()),
            'order_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:order_date',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.sale_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        // A full save used to accept any status at all, so the rules the
        // status endpoint enforces — no completing by hand, no reviving a
        // cancelled order — could be walked around by saving the form.
        if (!empty($validated['status'])) {
            $target = PurchaseOrder::normalizeStatus($validated['status']);

            if ($target !== $current && !in_array($target, PurchaseOrder::STATUS_TRANSITIONS[$current] ?? [], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن نقل طلب الشراء من حالته الحالية إلى الحالة المطلوبة',
                    'data' => null,
                ], 422);
            }

            $validated['status'] = $target;
        } else {
            unset($validated['status']);
        }

        $this->applyTotals($validated);
        ProductVariant::forLines($validated['items']);

        /*
         * Editing an order clears its lines and writes them again. Without a
         * transaction that is destructive rather than merely inconsistent: a
         * failure between the delete and the last insert leaves the order with
         * fewer lines than it had — or none — and no way to recover them.
         */
        DB::transaction(function () use ($order, $validated) {
            $order->update($validated);
            $order->items()->delete();

            $this->createLines($order, $validated['items']);
        });

        $order->load(['supplier', 'items.product', 'items.variant']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order updated successfully',
            'data' => $order,
        ]);
    }

    /**
     * Write an order's lines. A line for one variant is named for it
     * ("floor drain - 4\"") so the supplier's copy and the goods receipt
     * both say which size was asked for.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createLines(PurchaseOrder $order, array $items): void
    {
        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');
        $variants = ProductVariant::forLines($items);

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $variant = $variants->get((int) ($item['product_variant_id'] ?? 0));
            $name = $product ? $product->name : 'Unknown Product';

            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $variant?->id,
                'product_name' => $item['product_name'] ?? ($variant ? $variant->displayName($name) : $name),
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sale_price' => $item['sale_price'] ?? null,
                'total_price' => $item['unit_price'] * $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * Subtotal and total from the lines, in place.
     *
     * A discount larger than the goods plus tax left an order owing the
     * supplier a negative amount, which then flowed into purchase reporting.
     */
    private function applyTotals(array &$validated): void
    {
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        }

        $tax = (float) ($validated['tax'] ?? 0);
        $discount = (float) ($validated['discount'] ?? 0);

        if ($discount > $subtotal + $tax) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount' => 'الخصم أكبر من قيمة الطلب',
            ]);
        }

        $validated['subtotal'] = $subtotal;
        $validated['total'] = $subtotal + $tax - $discount;
    }

    /**
     * The next free PO number.
     *
     * Counting rows reused a number as soon as any order was deleted — the
     * count drops, the next order is given a number that is still taken, and
     * the unique index turns the save into a 500. The highest id only grows,
     * and every existing number was issued at or below its own id.
     */
    private function nextOrderNumber(): string
    {
        $next = ((int) PurchaseOrder::max('id')) + 1;

        do {
            $number = 'PO-' . str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
        } while (PurchaseOrder::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Statuses a full save may carry.
     *
     * The list used to stop at 'received', so the edit form's own options
     * ('processing', 'completed') were rejected — and an order that a goods
     * receipt had already marked completed could not be saved again at all,
     * because its current status was not in the list it had to pass.
     */
    private function writableStatuses(): array
    {
        return array_values(array_unique([
            ...PurchaseOrder::STATUSES,
            ...array_keys(PurchaseOrder::LEGACY_STATUSES),
        ]));
    }

    /**
     * Move an order along the workflow without touching anything else.
     *
     * Approving used to mean reopening the edit form and re-saving the whole
     * order, which rewrites every line — update() deletes them and inserts them
     * again — just to change one word. This changes the status alone, so
     * approval is a single click that cannot disturb the lines.
     */
    public function updateStatus(Request $request, PurchaseOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', PurchaseOrder::STATUSES),
        ]);

        $current = PurchaseOrder::normalizeStatus($order->status);
        $target = PurchaseOrder::normalizeStatus($validated['status']);

        if ($current === $target) {
            $order->load(['supplier', 'items.product', 'items.variant']);

            return response()->json([
                'success' => true,
                'message' => 'طلب الشراء في هذه الحالة بالفعل',
                'data' => $order,
            ]);
        }

        // Completion is the goods receipt's to write: it is what moves the stock
        // and posts the journal entry. Marking the order completed from here
        // would claim goods arrived that nothing ever booked in.
        if ($target === PurchaseOrder::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'يكتمل طلب الشراء بتسجيل إيصال استلام للبضاعة، وليس بتغيير حالته يدوياً',
                'data' => null,
            ], 422);
        }

        if (!in_array($target, PurchaseOrder::STATUS_TRANSITIONS[$current] ?? [], true)) {
            return response()->json([
                'success' => false,
                'message' => $current === PurchaseOrder::STATUS_COMPLETED
                    ? 'تم استلام بضاعة هذا الطلب، ولا يمكن تغيير حالته'
                    : 'لا يمكن نقل طلب الشراء من حالته الحالية إلى الحالة المطلوبة',
                'data' => null,
            ], 422);
        }

        $order->update(['status' => $target]);
        $order->load(['supplier', 'items.product', 'items.variant']);
        $order->loadCount('receipts');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة طلب الشراء بنجاح',
            'data' => $order,
        ]);
    }

    public function destroy(PurchaseOrder $order): JsonResponse
    {
        // A receipt booked stock and posted a journal entry against this
        // order; deleting it leaves both pointing at nothing. The screen only
        // greyed the option out, so the API itself did not refuse.
        if (PurchaseOrder::normalizeStatus($order->status) === PurchaseOrder::STATUS_COMPLETED
            || $order->receipts()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف طلب شراء تم استلام بضاعته. يمكنك إلغاؤه قبل الاستلام فقط',
                'data' => null,
            ], 422);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase order deleted successfully',
            'data' => null,
        ]);
    }
}
