<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? 'عميل عام',
            'customer_email' => $this->customer?->email,
            'customer_phone' => $this->customer?->phone,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            // The rates behind those two figures, so a form reopening the
            // invoice can offer back what was typed instead of inferring a rate
            // from an amount. Null on an invoice written in amounts.
            'tax_percent' => $this->tax_percent === null ? null : (float) $this->tax_percent,
            'discount_percent' => $this->discount_percent === null ? null : (float) $this->discount_percent,
            // Charges billed on top of the goods (delivery, packaging, …).
            // Without this the client cannot reconcile subtotal against total.
            'additional_charges' => (float) $this->additional_charges,
            'total' => (float) $this->total,

            // Collection state. The payments endpoint maintains these columns,
            // but they were missing from this resource, so no client could tell
            // a paid invoice from an unpaid one or show an outstanding balance.
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,

            // Owed, from the figures themselves: negative when the customer paid
            // more than the invoice and holds a credit. The stored due_amount
            // above is kept for older clients but was left out of step by
            // earlier edits on some invoices.
            'outstanding' => $this->outstanding(),
            'payment_state' => $this->paymentState(),
            'age_days' => $this->created_at ? (int) $this->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null,

            // The moves this screen may offer. None on an order's invoice —
            // the order moves it — nor on a cancelled one.
            'allowed_statuses' => $this->sales_order_id ? [] : (Invoice::TRANSITIONS[$this->status] ?? []),
            'items_count' => $this->whenCounted('items'),

            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'created_by' => $this->created_by,

            // Link back to the sales order this invoice was converted from.
            'sales_order_id' => $this->sales_order_id,
            'sales_order' => $this->when($this->relationLoaded('salesOrder'), fn () => $this->salesOrder
                ? ['id' => $this->salesOrder->id, 'order_number' => $this->salesOrder->order_number]
                : null),
            'customer_company' => $this->customer?->company,

            // Relationships
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->whenLoaded('payments'),
            'user' => $this->when($this->relationLoaded('user'), function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ] : null;
            }),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_formatted' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    private function paymentState(): ?string
    {
        if ($this->status === Invoice::STATUS_CANCELLED) {
            return null;
        }

        $owed = $this->outstanding();

        return match (true) {
            $owed < -0.009 => 'credit',
            $owed <= 0.009 => 'paid',
            (float) $this->paid_amount > 0.009 => 'partial',
            default => 'unpaid',
        };
    }
}
