<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'address' => $this->address,
            'source' => $this->source,
            'status' => $this->status,
            'notes' => $this->notes,
            'balance' => (float) $this->balance,
            // The rep sales for this customer default to (the primary one).
            'employee_id' => $this->employee_id ? (int) $this->employee_id : null,
            'sales_rep' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => $this->employee ? ['id' => $this->employee->id, 'name' => $this->employee->name] : null
            ),
            'credit_limit' => (float) $this->credit_limit,
            'tax_number' => $this->tax_number,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'currency' => $this->currency,
            'total_purchases' => (float) $this->total_purchases,
            'last_purchase_at' => $this->last_purchase_at?->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_at_formatted' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
