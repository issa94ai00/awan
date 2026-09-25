<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            // Exposed so clients can render the two-level classification tree
            // (a parent section and the subcategories filed under it) instead of
            // one flat list where a child is indistinguishable from a section.
            'parent_id' => $this->parent_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description' => $this->description,
            // The admin form edits both languages; without these it opened
            // blank and saving wiped the stored descriptions.
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'icon' => $this->icon,
            'image' => image_url($this->image),
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'product_count' => $this->when(isset($this->product_count), $this->product_count),
            // Admin list only (see CategoryController::adminIndex): products filed
            // directly here, in any state and live-only, and the subcategories.
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
            'active_products_count' => $this->when(isset($this->active_products_count), $this->active_products_count),
            'children_count' => $this->when(isset($this->children_count), $this->children_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'url' => route('category.show', $this),
        ];
    }
}
