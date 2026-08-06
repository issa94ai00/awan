<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

class Product extends Model implements Sitemapable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'name_en',
        'slug',
        'description',
        'description_ar',
        'description_en',
        'price',
        'stock_quantity',
        'image_main',
        'image_gallery',
        'status',
        'category_id',
        'show_price',
        'seo',
        'sku',
        'barcode',
        'cost_price',
        'tax_rate',
        'taxable',
        'unit',
        'min_stock',
        'max_stock',
        'reorder_point',
        'weight',
        'length',
        'width',
        'height',
        'color',
        'size',
        'variant_group',
        'short_description_ar',
        'short_description_en',
        'sort_order',
        'brand',
        'model',
        'in_stock',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'show_price' => 'boolean',
        'seo' => 'array',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'taxable' => 'boolean',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inquiries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSitemapTag(): Url|string
    {
        return Url::create(route('product.show', $this))
            ->setLastModificationDate($this->updated_at)
            ->setChangeFrequency('weekly')
            ->setPriority(0.8);
    }

    public function getImageMainUrlAttribute(): ?string
    {
        if (!$this->image_main) {
            return asset('assets/images/products/default-product.jpg');
        }
        
        return image_url($this->image_main);
    }

    public function getImageGalleryUrlsAttribute(): array
    {
        $gallery = json_decode($this->image_gallery ?? '[]', true);
        
        if (empty($gallery)) {
            return [];
        }
        
        return array_map(function ($image) {
            return image_url($image);
        }, $gallery);
    }

    public function warehouses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'stock_movements');
    }

    public function units(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && $this->name_ar) {
            return $this->name_ar;
        }
        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }
        return $this->name ?? ($this->name_ar ?? $this->name_en ?? '');
    }

    public function getDescriptionAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && $this->description_ar) {
            return $this->description_ar;
        }
        if ($locale === 'en' && $this->description_en) {
            return $this->description_en;
        }
        return $this->description ?? ($this->description_ar ?? $this->description_en ?? '');
    }

    public function getShortDescriptionAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && $this->short_description_ar) {
            return $this->short_description_ar;
        }
        if ($locale === 'en' && $this->short_description_en) {
            return $this->short_description_en;
        }
        return $this->short_description_ar ?? $this->short_description_en ?? '';
    }
}
