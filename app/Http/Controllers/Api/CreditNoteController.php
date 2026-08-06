<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use Illuminate\Http\Request;

/**
 * Credit notes are raised by the settlement flow (returns), not entered by
 * hand, so this exposes reading and cancelling only. Amounts are adjusted
 * through the documents that consume the credit, never edited directly.
 */
class CreditNoteController extends Controller
{
    public function index(Request $request)
    {
        $query = CreditNote::with(['customer', 'invoice', 'rmaRequest', 'creator']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->filled('rma_request_id')) {
            $query->where('rma_request_id', $request->rma_request_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('open_only')) {
            $query->open();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $creditNotes = $query->latest('issue_date')->latest('id')
            ->paginate(min((int) $request->input('per_page', 20) ?: 20, 200));

        return response()->json([
            'success' => true,
            'data' => [
                'credit_notes' => $creditNotes->items(),
                'pagination' => [
                    'current_page' => $creditNotes->currentPage(),
                    'last_page' => $creditNotes->lastPage(),
                    'per_page' => $creditNotes->perPage(),
                    'total' => $creditNotes->total(),
                ],
            ],
        ]);
    }

    public function show($id)
    {
        $creditNote = CreditNote::with([
            'customer', 'invoice', 'rmaRequest', 'creator', 'items.product',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $creditNote,
        ]);
    }

    /**
     * Cancels a credit note that has not been consumed. Once any part of it has
     * been applied, refunded or turned into store credit, cancelling would
     * silently reverse a settlement that other documents already reflect.
     */
    public function cancel($id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->settled_amount > 0.009) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء إشعار دائن تم استخدامه جزئياً أو كلياً',
                'data' => null,
            ], 422);
        }

        $creditNote->status = CreditNote::STATUS_CANCELLED;
        $creditNote->save();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الإشعار الدائن',
            'data' => $creditNote,
        ]);
    }

    public function summary(Request $request)
    {
        $query = CreditNote::query()->where('status', '!=', CreditNote::STATUS_CANCELLED);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $notes = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $notes->count(),
                'total_issued' => round($notes->sum('total'), 2),
                'total_applied_to_invoices' => round($notes->sum('applied_to_invoice'), 2),
                'total_refunded' => round($notes->sum('refunded_amount'), 2),
                'total_store_credit' => round($notes->sum('store_credit_amount'), 2),
                'total_open' => round($notes->sum(fn ($note) => $note->open_amount), 2),
            ],
        ]);
    }
}
