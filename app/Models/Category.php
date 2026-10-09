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

    /**
     * A picture for each category that has none of its own: the newest photo
     * among its products, or else its subcategories' (in their order). No
     * category had an image, so the storefront showed a wall of icons.
     *
     * Two queries for the whole tree rather than one per category. Pass the
     * whole tree: a section looks among the subcategories in the collection.
     */
    public static function attachThumbnails(\Illuminate\Support\Collection $categories): void
    {
        $latestIds = Product::query()
            ->where('is_active', 1)
            ->whereNotNull('image_main')
            ->where('image_main', '!=', '')
            // A real photo beats the generic placeholder drawings, which
            // only stand in when a category has nothing else.
            ->selectRaw("category_id, COALESCE(MAX(CASE WHEN image_main NOT LIKE '%images_items/generic/%' THEN id END), MAX(id)) as id")
            ->groupBy('category_id')
            ->pluck('id', 'category_id');

        $images = Product::whereIn('id', $latestIds->values())->pluck('image_main', 'id');
        $own = $latestIds->map(fn ($id) => $images[$id] ?? null)->filter();
        $children = $categories->groupBy('parent_id');

        foreach ($categories as $category) {
            if ($category->image) {
                continue;
            }
            $candidates = [$category->id, ...($children[$category->id] ?? collect())->pluck('id')];
            $image = collect($candidates)->map(fn ($id) => $own[$id] ?? null)->first(fn ($value) => $value);
            if ($image) {
                $category->thumbnail = image_url($image);
            }
        }
    }

    /**
     * The category itself plus its children. The taxonomy is two levels deep —
     * top-level sections with one row of subcategories under them — so a single
     * child lookup covers every product filed anywhere under this category.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        return array_merge(
            [$this->id],
            static::query()->where('parent_id', $this->id)->pluck('id')->all()
        );
    }

    /**
     * Selects `product_count` as the number of active products in the category
     * *and* its children, so a parent that only holds subcategories (Bahsas)
     * still reports the products the visitor will find under it.
     */
    public function scopeWithProductCount($query)
    {
        $table = $this->getTable();

        if (is_null($query->getQuery()->columns)) {
            $query->select($table . '.*');
        }

        // Its own products plus its subcategories', as two counts added up: one
        // `category_id = id OR category_id IN (...)` kept MySQL off the index
        // and took 0.8s over the whole tree, against 0.008s for this.
        return $query->selectRaw(
            '((SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND p.category_id = ' . $table . '.id)'
            . ' + (SELECT COUNT(*) FROM products p JOIN ' . $table . ' sub ON sub.id = p.category_id'
            . ' WHERE p.is_active = 1 AND sub.parent_id = ' . $table . '.id)'
            . ') as product_count'
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSitemapTag(): Url|string
    {
        $tag = Url::create(route('category.show', $this))
            ->setLastModificationDate($this->updated_at)
            ->setChangeFrequency('weekly')
            // A top-level section is the hub a crawler should reach before the
            // shelves under it; outranking the products themselves only for the
            // sections keeps the scale honest.
            ->setPriority($this->parent_id === null ? 0.7 : 0.6);

        if ($this->image) {
            $tag->addImage(image_url($this->image));
        }

        return $tag;
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
