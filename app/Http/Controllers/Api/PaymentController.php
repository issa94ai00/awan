<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private CurrencyService $currencies)
    {
    }

    /**
     * Turns what a payer typed — an amount in `$code` — into what actually
     * settles the books: the base-currency equivalent, plus what to keep on
     * the payment row for the receipt.
     *
     * Converting here rather than trusting a client-supplied rate is the
     * whole point: the rate used is always the one on file at this moment,
     * never a number a request could quietly override.
     *
     * @return array{amount: float, currency: string, tendered_amount: ?float}
     *
     * @throws \RuntimeException when `$code` has no rate recorded
     */
    private function resolveAmount(float $amount, ?string $code): array
    {
        $code = strtoupper(trim((string) ($code ?: $this->currencies->baseCode())));

        if ($code === $this->currencies->baseCode()) {
            return ['amount' => round($amount, 5), 'currency' => $code, 'tendered_amount' => null];
        }

        $converted = $this->currencies->convertToBase($amount, $code);

        if ($converted === null) {
            throw new \RuntimeException("لا يوجد سعر صرف مسجّل للعملة {$code}. سجّل السعر من إدارة العملات قبل القبض بها.");
        }

        return ['amount' => $converted, 'currency' => $code, 'tendered_amount' => round($amount, 5)];
    }

    /**
     * What has actually been collected, per currency — read as separate
     * cash drawers rather than one figure translated through a rate.
     *
     * Deliberately not a conversion to the base: that is exactly the number
     * the accounting summary already gives. This answers a different
     * question — "how many actual dollars, and how many actual pounds, have
     * we been handed" — which is why each row sums `tendered_amount` (what
     * was physically received) and falls back to `amount` only for the rows
     * that were paid in the base currency to begin with, where the two are
     * the same figure.
     */
    public function currencySummary(): \Illuminate\Http\JsonResponse
    {
        $rows = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->selectRaw('currency, SUM(COALESCE(tendered_amount, amount)) as total, COUNT(*) as payments_count')
            ->groupBy('currency')
            ->get();

        $base = $this->currencies->baseCode();

        $wallets = $rows->map(function ($row) use ($base) {
            $currency = $this->currencies->find((string) $row->currency);

            return [
                'currency' => $row->currency,
                'name' => $currency?->displayName(),
                'symbol' => $currency?->symbol,
                'decimal_places' => $currency?->decimal_places ?? 2,
                'is_base' => $row->currency === $base,
                'total' => (float) $row->total,
                'payments_count' => (int) $row->payments_count,
            ];
        })
            // The base currency's drawer leads, since it is the one the books
            // are kept in; the rest follow by however much sits in them.
            ->sortByDesc(fn ($wallet) => $wallet['is_base'] ? PHP_INT_MAX : $wallet['total'])
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['base' => $base, 'wallets' => $wallets],
        ]);
    }

    public function index(Request $request)
    {
        $query = Payment::query()->with([
            'invoice:id,invoice_number,status,total,paid_amount,sales_order_id',
            'customer:id,name,phone,company',
            'creator:id,name',
        ]);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // payment_date is optional on older rows; they fall back to the day
        // they were recorded, so a date filter does not silently drop them.
        // DATE() on both: the date column comes back with a time on some drivers.
        $day = 'COALESCE(DATE(payments.payment_date), DATE(payments.created_at))';
        if ($request->filled('date_from')) {
            $query->whereRaw("{$day} >= ?", [$request->date_from]);
        }
        if ($request->filled('date_to')) {
            $query->whereRaw("{$day} <= ?", [$request->date_to]);
        }

        // Searching used to happen in the browser over the twenty rows loaded,
        // so a payment on page two could not be found at all.
        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', $search)
                    ->orWhere('reference', 'like', $search)
                    ->orWhere('notes', 'like', $search)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('company', 'like', $search))
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', $search));
            });
        }

        // The cards describe the search and dates, before the status and kind
        // filters narrow it — they are how those are picked.
        $summary = $request->boolean('with_summary') ? $this->listSummary(clone $query) : null;

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Against an invoice, on account (no invoice), or a refund paid out.
        match ($request->input('kind')) {
            'invoice' => $query->whereNotNull('invoice_id')->where('status', '!=', Payment::STATUS_REFUNDED),
            'on_account' => $query->whereNull('invoice_id')->where('status', '!=', Payment::STATUS_REFUNDED),
            'refund' => $query->where('status', Payment::STATUS_REFUNDED),
            default => null,
        };

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if ($request->input('sort') === 'amount') {
            $query->orderBy('amount', $direction);
        } else {
            $query->orderByRaw("{$day} {$direction}");
        }
        $query->orderBy('id', $direction);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $payments = $query->paginate($perPage);

        $rows = collect($payments->items())->map(fn (Payment $payment) => $payment->toArray() + [
            // Refunds belong to the return that paid them out; reversing one
            // here would undo the cash without undoing the credit note.
            'is_refund' => $payment->status === Payment::STATUS_REFUNDED,
            'can_change' => $payment->status !== Payment::STATUS_REFUNDED,
            // A payment taken in another currency is corrected by reversing it
            // and recording it again: its base amount and what was handed over
            // would otherwise disagree.
            'amount_locked' => $payment->status === Payment::STATUS_REFUNDED
                || ($payment->tendered_amount !== null && $payment->currency !== $this->currencies->baseCode()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => [
                'payments' => $rows,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'has_more_pages' => $payments->hasMorePages(),
                ]
            ]
        ]);
    }

    /**
     * Money in, split by method; refunds paid out; how much was taken on
     * account; and today's takings — over the search and dates.
     */
    private function listSummary($query): array
    {
        $base = fn () => (clone $query)->reorder()->setEagerLoads([])->getQuery()->select([]);

        $in = $base()->where('status', Payment::STATUS_COMPLETED)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoice_id IS NULL THEN amount ELSE 0 END), 0) as on_account')
            ->selectRaw('SUM(CASE WHEN invoice_id IS NULL THEN 1 ELSE 0 END) as on_account_n')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(DATE(payment_date), DATE(created_at)) = ? THEN amount ELSE 0 END), 0) as today', [now()->toDateString()])
            ->selectRaw('SUM(CASE WHEN COALESCE(DATE(payment_date), DATE(created_at)) = ? THEN 1 ELSE 0 END) as today_n', [now()->toDateString()])
            ->first();

        $byMethod = $base()->where('status', Payment::STATUS_COMPLETED)
            ->select('payment_method')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->payment_method => ['count' => (int) $row->n, 'total' => round((float) $row->total, 2)]]);

        $out = $base()->where('status', Payment::STATUS_REFUNDED)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(ABS(amount)), 0) as total')
            ->first();

        return [
            'collected' => round((float) $in->total, 2),
            'collected_count' => (int) $in->n,
            'by_method' => $byMethod,
            'on_account' => round((float) $in->on_account, 2),
            'on_account_count' => (int) $in->on_account_n,
            'today' => round((float) $in->today, 2),
            'today_count' => (int) $in->today_n,
            'refunded' => round((float) $out->total, 2),
            'refunded_count' => (int) $out->n,
            'net' => round((float) $in->total - (float) $out->total, 2),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'customer_id' => 'required|exists:customers,id',
            'payment_method' => 'required|in:cash,card,bank_transfer,check',
            'amount' => 'required|numeric|min:0.01',
            // The currency the payer actually handed over. Optional — omitting
            // it means the base currency, same as before this field existed.
            'currency' => ['nullable', 'string', Rule::in($this->currencies->selectableCodes())],
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $resolved = $this->resolveAmount((float) $validated['amount'], $validated['currency'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        // A collection against an invoice goes through PaymentRecorder, the same
        // path delivery-time settlement uses, so the invoice, the customer
        // balance and the ledger always move together. This method used to do
        // all four by hand and marked a fully paid invoice as *delivered* —
        // describing the goods as having arrived because the money had.
        if (!empty($validated['invoice_id'])) {
            $invoice = Invoice::findOrFail($validated['invoice_id']);

            try {
                $payment = app(\App\Services\Sales\PaymentRecorder::class)->record(
                    $invoice,
                    $resolved['amount'],
                    [
                        'method' => $validated['payment_method'],
                        'date' => $validated['payment_date'] ?? null,
                        'reference' => $validated['reference'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'currency' => $resolved['currency'],
                        'tendered_amount' => $resolved['tendered_amount'],
                    ]
                );
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
            }

            $payment->load(['invoice', 'customer', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدفعة وترحيل قيدها المحاسبي',
                'data' => $payment,
            ], 201);
        }

        // An on-account payment with no invoice behind it: still recorded and
        // still posted, but there is no invoice to settle.
        //
        // The three records move together. This used to catch the posting
        // failure, log it, and answer 201 with the reason in an
        // `accounting_warning` field no screen displays — so money that never
        // reached the books looked, to whoever took it, exactly like money that
        // did, and the customer's balance had already moved for it.
        $payment_data = [
            'payment_number' => 'PAY-' . str_pad((string) (((int) Payment::max('id')) + 1), 6, '0', STR_PAD_LEFT),
            'customer_id' => $validated['customer_id'],
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => Payment::STATUS_COMPLETED,
            'created_by' => auth()->id(),
            'amount' => $resolved['amount'],
            'currency' => $resolved['currency'],
            'tendered_amount' => $resolved['tendered_amount'],
            'exchange_rate' => $resolved['tendered_amount'] !== null && $resolved['amount'] > 0
                ? round($resolved['tendered_amount'] / $resolved['amount'], 4)
                : 1,
        ];

        try {
            $payment = \Illuminate\Support\Facades\DB::transaction(function () use ($payment_data) {
                $payment = Payment::create($payment_data);
                $payment->customer->updateBalance(-$payment->amount);

                app(\App\Services\Accounting\LedgerPostingService::class)->postPayment($payment);

                return $payment;
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد الدفعة: ' . $e->getMessage(),
                'data' => null,
            ], 422);
        }

        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الدفعة بنجاح وترحيل قيدها',
            'data' => $payment,
        ], 201);
    }

    public function show(Payment $payment)
    {
        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved successfully',
            'data' => $payment
        ]);
    }

    /**
     * Corrects a payment already on the books.
     *
     * Reassigning a payment to a different invoice or customer is refused
     * rather than half-handled — the old code accepted both fields but never
     * moved the balance or the ledger off the original invoice, so the
     * receivable stayed charged to whoever it started on while the payment
     * record pointed elsewhere. Only what a correction actually means —
     * the amount, method, date, reference, notes — is editable here.
     *
     * An amount change reverses the original ledger entry and posts the
     * corrected one under its own key, the same way an invoice is restated
     * after it changes (`SalesOrderWorkflowService::restateInvoice`) — the
     * original key now belongs to a reversed entry, so re-posting under it
     * would just hand back that reversed header instead of writing a new one.
     *
     * This also drops the old `markAsDelivered()` call: an invoice reaching
     * zero due says the money arrived, not that the goods did, and conflating
     * the two is exactly the bug `store()` already had to be fixed for.
     */
    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,bank_transfer,check',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($payment->status === Payment::STATUS_REFUNDED) {
            return $this->refuse('هذا استرداد صُرف من مرتجع؛ يُدار من المرتجع نفسه.');
        }

        $oldAmount = round((float) $payment->amount, 5);
        $newAmount = round((float) $validated['amount'], 5);
        $invoice = $payment->invoice;
        $amountChanged = abs($newAmount - $oldAmount) > 0.009;

        if ($amountChanged && $payment->tendered_amount !== null && $payment->currency !== $this->currencies->baseCode()) {
            return $this->refuse('دُفعت هذه الدفعة بعملة أخرى؛ لتصحيح مبلغها اعكسها وسجّلها من جديد.');
        }

        if ($invoice && abs($newAmount - $oldAmount) > 0.009) {
            // What the invoice's other payments already cover, so the new
            // amount is checked against what is actually left rather than
            // against the total this payment used to claim.
            $otherPaid = round((float) $invoice->paid_amount - $oldAmount, 5);

            if ($newAmount - ((float) $invoice->total - $otherPaid) > 0.009) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'المبلغ (%s) يتجاوز المتبقي على الفاتورة %s.',
                        number_format($newAmount, 2),
                        $invoice->invoice_number
                    ),
                    'data' => null,
                ], 422);
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $invoice, $validated, $oldAmount, $newAmount, $otherPaid) {
                $payment->update($validated);

                $paid = round($otherPaid + $newAmount, 5);
                $invoice->update([
                    'paid_amount' => $paid,
                    'due_amount' => max(0, round((float) $invoice->total - $paid, 5)),
                    'paid_at' => $paid + 0.009 >= (float) $invoice->total ? now() : null,
                ]);

                // The customer owes the difference more, or less, than before.
                $invoice->customer?->updateBalance($oldAmount - $newAmount);

                app(\App\Services\Accounting\LedgerPostingService::class)->reverseFor('payment:' . $payment->id);
                app(\App\Services\Accounting\LedgerPostingService::class)->postPayment(
                    $payment,
                    'payment:' . $payment->id . ':corrected:' . now()->getTimestamp()
                );
            });
        } elseif (! $invoice && $amountChanged) {
            // On account: nothing to settle, but the customer's balance and the
            // entry still carry the old amount. Only the fields changed here
            // used to move, so the books kept the figure as first typed.
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $validated, $oldAmount, $newAmount) {
                $payment->update($validated);
                $payment->customer?->updateBalance($oldAmount - $newAmount);

                app(\App\Services\Accounting\LedgerPostingService::class)->reverseFor('payment:' . $payment->id);
                app(\App\Services\Accounting\LedgerPostingService::class)->postPayment(
                    $payment,
                    'payment:' . $payment->id . ':corrected:' . now()->getTimestamp()
                );
            });
        } else {
            $payment->update($validated);
        }

        $payment->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الدفعة بنجاح',
            'data' => $payment
        ]);
    }

    /**
     * Cancels a payment.
     *
     * All four records move together: the invoice, the customer's balance, the
     * reversing entry and the payment itself. The reversal used to be wrapped
     * in a catch that logged and carried on, so a payment whose entry could not
     * be reversed — most often because its period has since been closed — was
     * deleted anyway, leaving the collection standing in the books with no
     * document behind it.
     */
    public function destroy(Payment $payment)
    {
        if ($payment->status === Payment::STATUS_REFUNDED) {
            return $this->refuse('هذا استرداد صُرف من مرتجع؛ عكسه هنا يعيد المال دون أن يلغي الإشعار الدائن.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
                app(\App\Services\Accounting\LedgerPostingService::class)
                    ->reverseFor('payment:' . $payment->id);

                if ($invoice = $payment->invoice) {
                    // Recomputed rather than nudged: the due column is not
                    // always in step, and a fully paid invoice has to lose
                    // its paid stamp once money comes off it.
                    $paid = round((float) $invoice->paid_amount - (float) $payment->amount, 5);
                    $due = round((float) $invoice->due_amount + (float) $payment->amount, 5);
                    $invoice->update([
                        'paid_amount' => $paid,
                        'due_amount' => max(0, $due),
                        'paid_at' => $due > 0.009 ? null : $invoice->paid_at,
                    ]);
                }

                $payment->customer?->updateBalance($payment->amount);
                $payment->delete();
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر عكس قيد الدفعة: ' . $e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الدفعة وترحيل قيد عكسي لها',
            'data' => null
        ]);
    }

    private function refuse(string $message)
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], 422);
    }
}
