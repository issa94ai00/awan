<?php

namespace App\Http\Resources;

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
            'total' => (float) $this->total,

            // Collection state. The payments endpoint maintains these columns,
            // but they were missing from this resource, so no client could tell
            // a paid invoice from an unpaid one or show an outstanding balance.
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,

            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'created_by' => $this->created_by,

            // Link back to the sales order this invoice was converted from.
            'sales_order_id' => $this->sales_order_id,

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
}
