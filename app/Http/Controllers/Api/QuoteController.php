<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\SalesOrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    /** What the list and the drawer need of a quote, and nothing heavier. */
    private const RELATIONS = [
        'customer:id,name,phone,company',
        'creator:id,name',
        'salesOrder:id,quote_id,order_number,status',
    ];

    public function index(Request $request)
    {
        $query = Quote::query()
            ->with(self::RELATIONS)
            ->withCount('items');

        // The search and the customer and date filters shape the cards too, so
        // they say what the table below them is made of. The status and
        // validity filters do not: the cards are how you pick one.
        $this->applyScope($query, $request);
        $summary = $this->summary(clone $query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif ($request->boolean('open')) {
            // Draft and sent together: everything still in play.
            $query->whereIn('status', Quote::EDITABLE_STATUSES);
        }

        match ($request->input('validity')) {
            // Still open, but the date it was good until has gone.
            'lapsed' => $query->whereIn('status', Quote::EDITABLE_STATUSES)
                ->whereDate('valid_until', '<', today()),
            // Open, and running out within the week.
            'expiring' => $query->whereIn('status', Quote::EDITABLE_STATUSES)
                ->whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(7)),
            default => null,
        };

        // Accepted quotes that never became an order are work left undone.
        if ($request->filled('converted')) {
            $request->boolean('converted') ? $query->has('salesOrder') : $query->doesntHave('salesOrder');
        }

        $sort = in_array($request->input('sort'), ['total', 'valid_until', 'created_at', 'quote_number'], true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction)->orderByDesc('id');

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $quotes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Quotes retrieved successfully',
            'data' => [
                'quotes' => collect($quotes->items())->map(fn (Quote $quote) => $this->present($quote))->all(),
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $quotes->currentPage(),
                    'last_page' => $quotes->lastPage(),
                    'per_page' => $quotes->perPage(),
                    'total' => $quotes->total(),
                    'has_more_pages' => $quotes->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateQuote($request);
        $lines = $this->buildLines($validated['items']);
        $totals = $this->totals($lines, $validated);

        $quote = DB::transaction(function () use ($validated, $lines, $totals) {
            $quote = Quote::create([
                'quote_number' => Quote::nextNumber(),
                'customer_id' => $validated['customer_id'],
                'status' => Quote::STATUS_DRAFT,
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'created_by' => auth()->id(),
            ] + $totals);

            foreach ($lines as $line) {
                $quote->items()->create($line);
            }

            return $quote;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء عرض السعر بنجاح',
            'data' => $this->loadFull($quote),
        ], 201);
    }

    public function show(Quote $quote)
    {
        return response()->json([
            'success' => true,
            'message' => 'Quote retrieved successfully',
            'data' => $this->loadFull($quote),
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        // Once accepted, rejected or lapsed, the quote is what the customer
        // answered to; rewriting it would change the record of that answer.
        if (! in_array($quote->status, Quote::EDITABLE_STATUSES, true) || $quote->salesOrder()->exists()) {
            return $this->refuse('لا يمكن تعديل عرض السعر إلا وهو مسودة أو مرسل. أعده إلى مسودة أولاً.', 422);
        }

        $validated = $this->validateQuote($request);
        $lines = $this->buildLines($validated['items']);
        $totals = $this->totals($lines, $validated);

        // Header totals are derived from the lines, and the lines are cleared
        // before being rewritten — so both have to land together or the quote
        // is left describing items it no longer has.
        DB::transaction(function () use ($quote, $validated, $lines, $totals) {
            // Status moves only through updateStatus(), where the transition
            // rules live; a full save must not slip one past them.
            $quote->update([
                'customer_id' => $validated['customer_id'],
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
            ] + $totals);

            $quote->items()->delete();
            foreach ($lines as $line) {
                $quote->items()->create($line);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث عرض السعر بنجاح',
            'data' => $this->loadFull($quote),
        ]);
    }

    /**
     * Change only the workflow status of a quote.
     *
     * update() rewrites the whole quote, so it cannot be used to move a quote
     * from draft to sent. Moves are limited to Quote::TRANSITIONS.
     */
    public function updateStatus(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);
        $status = $validated['status'];

        if ($quote->status === $status) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة عرض السعر بنجاح',
                'data' => $this->loadFull($quote),
            ]);
        }

        if ($quote->salesOrder()->exists()) {
            return $this->refuse('تحوّل عرض السعر إلى طلب بيع، فلا تتغير حالته.', 422);
        }

        if (! $quote->canMoveTo($status)) {
            return $this->refuse('لا يمكن نقل عرض السعر من هذه الحالة إلى الحالة المطلوبة.', 422);
        }

        // A customer cannot accept an offer that no longer stands. Extend the
        // validity first, so the order is made at prices that were still on offer.
        if ($status === Quote::STATUS_ACCEPTED && $quote->valid_until && $quote->valid_until->lt(today())) {
            return $this->refuse('انتهت صلاحية عرض السعر. مدّد تاريخ الصلاحية قبل قبوله.', 422);
        }

        $quote->update(['status' => $status]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة عرض السعر بنجاح',
            'data' => $this->loadFull($quote),
        ]);
    }

    public function destroy(Quote $quote)
    {
        // The order keeps a link back to the quote it came from; deleting the
        // quote would leave the order with no record of what was offered.
        if ($quote->salesOrder()->exists()) {
            return $this->refuse('لا يمكن حذف عرض سعر تحوّل إلى طلب بيع.', 422);
        }

        $quote->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف عرض السعر بنجاح',
            'data' => null,
        ]);
    }

    /**
     * A new draft with the same customer and lines — the usual start for a
     * revised offer or a repeat customer.
     */
    public function duplicate(Quote $quote)
    {
        $quote->load('items');

        // The copy is good for as long as the original was, counted from today.
        $validFor = $quote->valid_until
            ? max(1, (int) $quote->created_at->copy()->startOfDay()->diffInDays($quote->valid_until, false))
            : null;

        $copy = DB::transaction(function () use ($quote, $validFor) {
            $copy = Quote::create([
                'quote_number' => Quote::nextNumber(),
                'customer_id' => $quote->customer_id,
                'status' => Quote::STATUS_DRAFT,
                'valid_until' => $validFor ? today()->addDays($validFor) : null,
                'subtotal' => $quote->subtotal,
                'tax' => $quote->tax,
                'discount' => $quote->discount,
                'total' => $quote->total,
                'notes' => $quote->notes,
                'terms' => $quote->terms,
                'created_by' => auth()->id(),
            ]);

            foreach ($quote->items as $item) {
                $copy->items()->create($item->only(['product_id', 'description', 'quantity', 'unit_price', 'discount', 'tax']));
            }

            return $copy;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم نسخ عرض السعر',
            'data' => $this->loadFull($copy),
        ], 201);
    }

    public function convertToSalesOrder(Quote $quote)
    {
        // A conversion that half-succeeded would leave an order carrying the
        // quote's totals with only some of its lines. The quote row is locked
        // so a double click cannot make two orders out of one acceptance.
        $result = DB::transaction(function () use ($quote) {
            $quote = Quote::whereKey($quote->id)->lockForUpdate()->first();

            if ($quote->status !== Quote::STATUS_ACCEPTED) {
                return ['error' => 'يمكن تحويل عروض الأسعار المقبولة فقط إلى طلبات بيع', 'code' => 400];
            }

            $existing = $quote->salesOrder()->first();
            if ($existing) {
                return [
                    'error' => "تحوّل عرض السعر مسبقاً إلى طلب البيع {$existing->order_number}",
                    'code' => 409,
                    'order' => $existing,
                ];
            }

            $salesOrder = SalesOrder::create([
                'order_number' => 'SO-'.str_pad((string) (((int) SalesOrder::max('id')) + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => $quote->customer_id,
                'quote_id' => $quote->id,
                'status' => SalesOrder::STATUS_PENDING,
                'order_date' => now(),
                'subtotal' => $quote->subtotal,
                'tax' => $quote->tax,
                'discount' => $quote->discount,
                'total' => $quote->total,
                'notes' => $quote->notes,
                'created_by' => auth()->id(),
                // As with an order entered by hand: owned by whoever made it,
                // so it shows up in someone's orders and has someone to chase it.
                'assigned_employee_id' => Employee::where('user_id', auth()->id())->value('id'),
            ]);

            foreach ($quote->items()->with('product:id,name_ar')->get() as $item) {
                $salesOrder->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description ?: $item->product?->name_ar,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'tax' => $item->tax,
                ]);
            }

            SalesOrderStatusHistory::create([
                'sales_order_id' => $salesOrder->id,
                'from_status' => null,
                'to_status' => SalesOrder::STATUS_PENDING,
                'note' => "من عرض السعر {$quote->quote_number}",
                'user_id' => auth()->id(),
            ]);

            return ['order' => $salesOrder];
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => isset($result['order'])
                    ? $result['order']->only(['id', 'order_number', 'status'])
                    : null,
            ], $result['code']);
        }

        $salesOrder = $result['order']->load(['customer', 'creator', 'items.product']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحويل عرض السعر إلى طلب بيع بنجاح',
            'data' => $salesOrder,
        ], 201);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function validateQuote(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|exists:customers,id',
            // Today counts: a quote good for the day it is written is a real offer.
            'valid_until' => 'nullable|date|after_or_equal:today',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'terms' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);
    }

    /**
     * The lines as they will be stored. A discount larger than the line would
     * make the customer owe less than nothing for it, so that is refused here
     * rather than surfacing later as a negative total.
     */
    private function buildLines(array $items): array
    {
        $names = Product::whereIn('id', collect($items)->pluck('product_id'))->pluck('name_ar', 'id');
        $errors = [];

        $lines = collect($items)->values()->map(function (array $item, int $i) use ($names, &$errors) {
            $gross = (float) $item['unit_price'] * (int) $item['quantity'];
            $discount = (float) ($item['discount'] ?? 0);

            if ($discount > $gross + 0.00001) {
                $errors["items.{$i}.discount"] = 'خصم السطر أكبر من قيمته.';
            }

            return [
                'product_id' => $item['product_id'],
                'description' => ($item['description'] ?? null) ?: ($names[$item['product_id']] ?? null),
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'discount' => $discount,
                'tax' => (float) ($item['tax'] ?? 0),
            ];
        })->all();

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    private function totals(array $lines, array $validated): array
    {
        $subtotal = collect($lines)->sum(
            fn (array $line) => ($line['unit_price'] * $line['quantity']) - $line['discount'] + $line['tax']
        );
        $discount = (float) ($validated['discount'] ?? 0);
        $tax = (float) ($validated['tax'] ?? 0);

        if ($discount > $subtotal + 0.00001) {
            throw ValidationException::withMessages(['discount' => 'الخصم أكبر من قيمة البنود.']);
        }

        return [
            'subtotal' => round($subtotal, 5),
            'discount' => $discount,
            'tax' => $tax,
            'total' => round($subtotal - $discount + $tax, 5),
        ];
    }

    private function applyScope($query, Request $request): void
    {
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
    }

    /**
     * Counts and values per status, and the two figures that call for action:
     * open quotes that have lapsed, and those running out this week.
     */
    private function summary($query): array
    {
        $rows = (clone $query)->reorder()->setEagerLoads([])
            ->getQuery()
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(total), 0) as value'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $byStatus = [];
        foreach (array_keys(Quote::TRANSITIONS) as $status) {
            $byStatus[$status] = [
                'count' => (int) ($rows[$status]->count ?? 0),
                'value' => round((float) ($rows[$status]->value ?? 0), 2),
            ];
        }

        $open = fn () => (clone $query)->reorder()->setEagerLoads([])->whereIn('status', Quote::EDITABLE_STATUSES);

        $converted = (clone $query)->reorder()->setEagerLoads([])->has('salesOrder')->count();
        $decided = $byStatus['accepted']['count'] + $byStatus['rejected']['count'] + $byStatus['expired']['count'];

        return [
            'total' => array_sum(array_column($byStatus, 'count')),
            'by_status' => $byStatus,
            'open_value' => round($byStatus['draft']['value'] + $byStatus['sent']['value'], 2),
            'lapsed' => $open()->whereDate('valid_until', '<', today())->count(),
            'expiring' => $open()->whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(7))->count(),
            'converted' => $converted,
            'awaiting_conversion' => max(0, $byStatus['accepted']['count'] - $converted),
            // Of the quotes the customer has answered, how many they took.
            'win_rate' => $decided ? round($byStatus['accepted']['count'] / $decided * 100, 1) : null,
        ];
    }

    private function present(Quote $quote): array
    {
        return $quote->toArray() + [
            'is_past_validity' => $quote->isPastValidity(),
            'allowed_statuses' => $quote->salesOrder ? [] : (Quote::TRANSITIONS[$quote->status] ?? []),
            'is_editable' => ! $quote->salesOrder && in_array($quote->status, Quote::EDITABLE_STATUSES, true),
        ];
    }

    private function loadFull(Quote $quote): array
    {
        $quote->refresh()->load([...self::RELATIONS, 'items.product:id,name_ar,name_en,sku,unit,price']);
        $quote->loadCount('items');

        return $this->present($quote);
    }

    private function refuse(string $message, int $code)
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $code);
    }
}
