<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;

class Product extends Model implements Sitemapable
{
    use HasFactory;

    protected $guarded = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'show_price' => 'boolean',
        'seo' => 'array',
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
}
