<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;


class Category extends Model implements Sitemapable
{
    use HasFactory;

    /**
     * `name` is a read-only accessor over `name_ar` / `name_en`, and `status`
     * was replaced by `is_active`. Neither is a column, so both were silently
     * dropped on every mass assignment — see the same removal on Product.
     */
    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'image',
        'icon',
        'parent_id',
        'description',
        'description_ar',
        'description_en',
        'meta_title',
        'meta_description',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSitemapTag(): Url|string
    {
        return Url::create(route('category.show', $this))
            ->setLastModificationDate($this->updated_at)
            ->setChangeFrequency('weekly')
            ->setPriority(0.7);
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
}
