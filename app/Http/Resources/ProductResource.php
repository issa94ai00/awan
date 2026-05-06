<?php

namespace App\Http\Resources;

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
        return [
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
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'show_price' => (bool) $this->show_price,
            'in_stock' => (bool) $this->in_stock,
            'stock_quantity' => $this->stock_quantity,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            
            // Images
            'image_main' => $this->image_main ? asset('storage/' . $this->image_main) : null,
            'image_gallery' => $this->image_gallery ? array_map(function ($image) {
                return asset('storage/' . $image);
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
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Additional computed fields
            'has_sale' => $this->sale_price && $this->sale_price < $this->price,
            'discount_percentage' => $this->sale_price && $this->sale_price < $this->price 
                ? round((($this->price - $this->sale_price) / $this->price) * 100, 2) 
                : 0,
        ];
    }
}
