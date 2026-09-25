<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'product_id' => $this->product_id,
            // The size or colour sold, when the line is for one.
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product_name,
            // Where the line came off the shelf and the unit it was priced in.
            // The edit form restores both; without them a reopened invoice
            // lost its warehouses and units and saved them back empty.
            'warehouse_id' => $this->warehouse_id,
            'product_unit_id' => $this->product_unit_id,
            'unit_name' => $this->unit_name,
            'unit_multiplier' => $this->unit_multiplier !== null ? (float) $this->unit_multiplier : null,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'notes' => $this->notes,

            // Product details (if loaded)
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name_ar' => $this->product->name_ar,
                    'name_en' => $this->product->name_en,
                    'slug' => $this->product->slug,
                    'image_main' => image_url($this->product->image_main),
                ] : null;
            }),

            'variant' => $this->when($this->relationLoaded('variant'), function () {
                return $this->variant ? [
                    'id' => $this->variant->id,
                    'sku' => $this->variant->sku,
                    'size' => $this->variant->size,
                    'color' => $this->variant->color,
                    'material' => $this->variant->material,
                    'label' => $this->variant->label,
                ] : null;
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
