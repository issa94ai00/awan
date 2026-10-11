<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

class Product extends Model implements Sitemapable
{
    use HasFactory;

    /**
     * `name`, `description` and `status` are deliberately absent.
     *
     * The first two are read-only accessors over `name_ar` / `name_en` — there
     * are no such columns, and no mutator to route them anywhere, so listing
     * them here promised a mass assignment that Eloquent silently dropped.
     * `status` is the name of a column that was replaced by `is_active`.
     *
     * Nothing sends them: neither the products API nor the import validates any
     * of the three. Removing them makes this list describe the table again.
     */
    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'price',
        'stock_quantity',
        'image_main',
        'image_gallery',
        'category_id',
        'show_price',
        'seo',
        'sku',
        'barcode',
        'cost_price',
        'tax_rate',
        'taxable',
        'unit',
        'pack_quantity',
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
        'cost_price' => 'decimal:5',
        'tax_rate' => 'decimal:2',
        'taxable' => 'boolean',
        'pack_quantity' => 'integer',
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

    public function stockMovements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toSitemapTag(): Url|string
    {
        $tag = Url::create(route('product.show', $this))
            ->setLastModificationDate($this->updated_at)
            ->setChangeFrequency('weekly')
            // Product pages are the long tail — template-thin next to the
            // category hubs, so they rank below a parent category (0.7) and
            // level with a subcategory (0.6). The old flat 0.8 put ~4,000
            // near-identical pages above almost everything worth promoting.
            ->setPriority(0.6);

        // Surface the product photos to Google Images via <image:image>.
        // image_url() resolves storage-relative paths to absolute URLs; gallery
        // entries cap at 8 per product so a heavily-photographed item cannot
        // bloat the sitemap (Google allows up to 1000, but more is rarely worth it).
        //
        // The generic placeholder drawings (images_items/generic/*.svg) are
        // skipped: they are not the product, and advertising hundreds of
        // identical icons to Google Images only dilutes the real photos.
        $gallery = json_decode($this->image_gallery ?? '[]', true) ?: [];
        $images = collect([$this->image_main])
            ->merge($gallery)
            ->filter()
            ->reject(fn (string $path) => str_contains($path, 'images_items/generic/'))
            ->map(fn (string $path) => image_url($path))
            ->filter()
            ->take(9);

        foreach ($images as $imageUrl) {
            $tag->addImage($imageUrl);
        }

        return $tag;
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

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function units(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * The storefront lists products that share a name inside one category
     * once — a catalogue imported as one product per size ("مفتاح اموليت"
     * at 8", 10" and 12", each with its own code and photo) reads as one
     * item with sizes. The listed one is the group's first by id; its card
     * and page offer the others (see attachStorefrontSiblings()).
     *
     * Only for browsing: a search keeps every product, so a shopper looking
     * up one size's code finds that size.
     */
    public function scopeStorefrontGrouped(\Illuminate\Database\Eloquent\Builder $query, bool $inStockOnly = false): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotExists(fn ($twin) => $twin->selectRaw('1')
            ->from('products as twin')
            ->whereColumn('twin.name_ar', 'products.name_ar')
            ->whereColumn('twin.category_id', 'products.category_id')
            ->whereColumn('twin.id', '<', 'products.id')
            ->where('twin.is_active', 1)
            ->when($inStockOnly, fn ($q) => $q->where('twin.in_stock', 1)));
    }

    /**
     * Give each product its storefront siblings — the active products sharing
     * its name and category, itself included — as `storefront_siblings`, in
     * one query for the whole page. A product alone in its group gets none.
     *
     * @param  iterable<self>  $products
     */
    public static function attachStorefrontSiblings(iterable $products): void
    {
        $products = collect($products)->filter(fn ($p) => $p instanceof self && $p->category_id !== null);
        if ($products->isEmpty()) {
            return;
        }

        $groups = self::query()
            ->where('is_active', 1)
            ->whereIn('category_id', $products->pluck('category_id')->unique()->values())
            ->whereIn('name_ar', $products->pluck('name_ar')->unique()->values())
            ->orderBy('id')
            ->get(['id', 'slug', 'name_ar', 'category_id', 'sku', 'model', 'size', 'description_ar',
                'price', 'show_price', 'in_stock', 'stock_quantity', 'image_main'])
            ->groupBy(fn (self $p) => $p->category_id.'|'.$p->name_ar);

        foreach ($products as $product) {
            $group = $groups->get($product->category_id.'|'.$product->name_ar);
            if ($group && $group->count() > 1) {
                $product->setAttribute('storefront_siblings', self::labelSiblings($group));
            }
        }
    }

    /**
     * What tells siblings apart on a button, read from their descriptions,
     * which an import writes freely ("100 غرام", "العرض: 12 مم", "القياس:
     * 10\" (250 مم)"): the lines no other sibling has, else the lines not
     * every sibling has, the name itself left out. A key all labels start
     * with ("العرض:") is dropped. Siblings whose descriptions say nothing
     * apart fall back to size, model or code.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $group
     * @return array<int, array<string, mixed>>
     */
    private static function labelSiblings(\Illuminate\Support\Collection $group): array
    {
        $linesOf = $group->mapWithKeys(function (self $p) {
            $lines = preg_split('/\R/u', trim(strip_tags((string) $p->description_ar))) ?: [];
            $lines = collect($lines)
                ->map(fn ($line) => trim(str_starts_with(trim($line), $p->name_ar) ? mb_substr(trim($line), mb_strlen($p->name_ar)) : $line))
                ->filter(fn ($line) => $line !== '')
                ->unique()
                ->values();

            return [$p->id => $lines];
        });
        $seenIn = $linesOf->flatten()->countBy();
        $size = $group->count();

        // One line where that is enough to tell them apart, two where not.
        $labelsOf = fn (int $take) => $group->mapWithKeys(function (self $p) use ($linesOf, $seenIn, $size, $take) {
            $lines = $linesOf[$p->id];
            $own = $lines->filter(fn ($line) => $seenIn[$line] === 1);
            $parts = ($own->isNotEmpty() ? $own : $lines->filter(fn ($line) => $seenIn[$line] < $size))->take($take);
            $label = $parts->implode(' · ');
            $label = $label !== '' ? $label : (trim((string) $p->size) ?: (trim((string) $p->model) ?: (string) $p->sku));

            return [$p->id => mb_strimwidth($label, 0, 48, '…')];
        });
        $short = $labelsOf(1);
        $long = $labelsOf(2);
        $shortCounts = $short->countBy();
        $labels = $short->map(fn ($label, $id) => $shortCounts[$label] > 1 ? $long[$id] : $label);

        if (preg_match('/^([^:：·]{1,20}[:：])/u', (string) $labels->first(), $m)
            && $labels->every(fn ($label) => str_starts_with($label, $m[1]))) {
            $labels = $labels->map(fn ($label) => trim(mb_substr($label, mb_strlen($m[1]))));
        }

        $counts = $labels->countBy();

        return $group->map(fn (self $p) => [
            'id' => $p->id,
            'slug' => $p->slug,
            'label' => $counts[$labels[$p->id]] > 1 ? $labels[$p->id].' · '.($p->model ?: $p->sku) : $labels[$p->id],
            'sku' => $p->sku,
            'price' => $p->price,
            'show_price' => (bool) $p->show_price,
            'in_stock' => (bool) $p->in_stock,
            'stock_quantity' => $p->stock_quantity,
            'image_main' => image_url($p->image_main),
        ])->values()->all();
    }

    /**
     * One row per variant instead of one row per product, so the storefront
     * can list each size/colour as its own item. A product with no variants
     * still comes back once, with a null `variant_id`.
     *
     * The variant's columns are aliased (`variant_*`) because `product_variants`
     * shares sku, barcode, price, size and color with `products`; any condition
     * added to a query using this scope must qualify its columns.
     */
    public function scopeWithVariantRows(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->leftJoin('product_variants as pv', 'pv.product_id', '=', 'products.id')
            ->select('products.*')
            ->addSelect([
                'pv.id as variant_id',
                'pv.sku as variant_sku',
                'pv.barcode as variant_barcode',
                'pv.price as variant_price',
                'pv.cost_price as variant_cost_price',
                'pv.stock_quantity as variant_stock_quantity',
                'pv.color as variant_color',
                'pv.size as variant_size',
                'pv.material as variant_material',
            ]);
    }

    /**
     * The storefront's listing orders — newest, price either way, name — for a
     * query with or without withVariantRows(). Unpriced rows ("ask for a
     * price") go last under a price sort rather than first as the cheapest,
     * and a tie-break on id keeps rows from swapping between pages.
     */
    public function scopeStorefrontSort(\Illuminate\Database\Eloquent\Builder $query, ?string $sort, bool $variantRows, ?string $lang = null): \Illuminate\Database\Eloquent\Builder
    {
        $priceSql = $variantRows ? self::variantRowPriceSql() : self::lowestPriceSql();
        $unpricedLast = "CASE WHEN COALESCE({$priceSql}, 0) <= 0 THEN 1 ELSE 0 END";

        match ($sort) {
            'price_asc' => $query->orderByRaw($unpricedLast)->orderByRaw("{$priceSql} asc"),
            'price_desc' => $query->orderByRaw($unpricedLast)->orderByRaw("{$priceSql} desc"),
            'name' => $query->orderBy($lang === 'en' ? 'products.name_en' : 'products.name_ar'),
            default => $query->orderByDesc('products.created_at'),
        };

        return $query->orderBy('products.id')->when($variantRows, fn ($q) => $q->orderBy('pv.id'));
    }

    /**
     * In stock the way the storefront card's badge says it: the product is,
     * and the size listed has stock (or keeps none of its own). A product
     * listed once for all its sizes is in stock while any one of them is.
     */
    public function scopeStorefrontInStock(\Illuminate\Database\Eloquent\Builder $query, bool $variantRows): \Illuminate\Database\Eloquent\Builder
    {
        $available = fn ($w) => $w->whereNull('stock_quantity')->orWhere('stock_quantity', '>', 0);

        return $query->where('products.in_stock', 1)
            ->when($variantRows, fn ($q) => $q->where(fn ($w) => $w->whereNull('pv.stock_quantity')->orWhere('pv.stock_quantity', '>', 0)))
            ->when(! $variantRows, fn ($q) => $q->where(fn ($w) => $w->whereDoesntHave('variants')->orWhereHas('variants', $available)));
    }

    /**
     * The lowest price a shopper can pay for a product listed once for all its
     * sizes — what its card's "from" price shows. A size without a price of
     * its own sells at the product's price; a product without sizes, at its
     * own.
     */
    public static function lowestPriceSql(): string
    {
        return 'CAST(COALESCE((SELECT MIN(CASE WHEN v.price > 0 THEN v.price ELSE products.price END)'
            .' FROM product_variants v WHERE v.product_id = products.id), products.price) AS DECIMAL(20, 5))';
    }

    /**
     * The price a shopper pays for a row from withVariantRows(): the variant's
     * own price when it has one, otherwise the product's. Cast so the two
     * tables' decimal columns compare as numbers on every driver.
     */
    public static function variantRowPriceSql(): string
    {
        return 'CAST(CASE WHEN pv.id IS NOT NULL AND pv.price > 0 THEN pv.price'
            .' ELSE products.price END AS DECIMAL(20, 5))';
    }

    public function warehouseAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductWarehouseAssignment::class);
    }

    public function activeWarehouseAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->warehouseAssignments()->active();
    }

    public function parentComponents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id');
    }

    public function childComponents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductComponent::class, 'component_product_id');
    }

    public function isComposite(): bool
    {
        return $this->parentComponents()->required()->exists();
    }

    public function getRequiredComponents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->parentComponents()->required();
    }

    public function getOptionalComponents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->parentComponents()->optional();
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
