<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->baseArray();

        return $this->variant_id ? $this->asVariantRow($data) : $data;
    }

    /**
     * A row from Product::withVariantRows() that stands for one variant: it
     * reads as a product of its own — name, code, price and stock are the
     * variant's — while `id` and `slug` stay the parent's, so links and the
     * cart keep working. `listing_key` is unique per row for list keys.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function asVariantRow(array $data): array
    {
        $label = ProductVariant::labelFor($this->variant_size, $this->variant_color, $this->variant_material);
        $suffix = $label !== '' ? ' - '.$label : '';

        $hasOwnPrice = $this->variant_price !== null && (float) $this->variant_price > 0;
        $stock = $this->variant_stock_quantity;

        return array_merge($data, [
            'listing_key' => $this->id.'-'.$this->variant_id,
            'variant_id' => $this->variant_id,
            'variant_label' => $label,
            'product_name_ar' => $this->name_ar,
            'product_name_en' => $this->name_en,
            'name_ar' => $this->name_ar.$suffix,
            'name_en' => $this->name_en ? $this->name_en.$suffix : $this->name_en,
            'sku' => $this->variant_sku ?: $this->sku,
            'barcode' => $this->variant_barcode ?: $this->barcode,
            'size' => $this->variant_size ?: $this->size,
            'color' => $this->variant_color ?: $this->color,
            'material' => $this->variant_material,
            // The product's sale price is a discount on the product's price,
            // not on a variant's own price, so it only carries over to a
            // variant that has no price of its own.
            'price' => $hasOwnPrice ? $this->variant_price : $data['price'],
            'sale_price' => $hasOwnPrice ? null : $data['sale_price'],
            'has_sale' => $hasOwnPrice ? false : $data['has_sale'],
            'discount_percentage' => $hasOwnPrice ? 0 : $data['discount_percentage'],
            'stock_quantity' => $stock,
            'in_stock' => (bool) $this->in_stock && ($stock === null || (int) $stock > 0),
            'url' => $data['url'].'?variant='.$this->variant_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseArray(): array
    {
        return [
            'listing_key' => (string) $this->id,
            'variant_id' => null,
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'short_description_ar' => $this->short_description_ar,
            'short_description_en' => $this->short_description_en,
            'brand' => $this->brand,
            'model' => $this->model,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'cost_price' => $this->cost_price,
            'tax_rate' => $this->tax_rate,
            'taxable' => (bool) $this->taxable,
            'unit' => $this->unit,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'show_price' => (bool) $this->show_price,
            'in_stock' => (bool) $this->in_stock,
            'stock_quantity' => $this->stock_quantity,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'reorder_point' => $this->reorder_point,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'color' => $this->color,
            'size' => $this->size,
            
            // Images
            'image_main' => image_url($this->image_main),
            'image_gallery' => $this->image_gallery ? array_map(function ($image) {
                return image_url($image);
            }, json_decode($this->image_gallery, true) ?? []) : [],
            
            // SEO data
            'seo' => $this->seo,
            
            // Relationships
            'category' => $this->when($this->relationLoaded('category'), function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'name_ar' => $this->category->name_ar,
                    'name_en' => $this->category->name_en,
                    'slug' => $this->category->slug,
                ] : null;
            }),
            'variants' => $this->when($this->relationLoaded('variants'), function () {
                return $this->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'material' => $variant->material,
                    'price' => $variant->price,
                    'cost_price' => $variant->cost_price,
                    'stock_quantity' => $variant->stock_quantity,
                ]);
            }),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Additional computed fields
            'has_sale' => $this->sale_price && $this->sale_price < $this->price,
            'discount_percentage' => $this->sale_price && $this->sale_price < $this->price 
                ? round((($this->price - $this->sale_price) / $this->price) * 100, 2) 
                : 0,
            'url' => route('product.show', $this),
        ];
    }
}
