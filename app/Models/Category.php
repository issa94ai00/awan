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

    protected $guarded = [
        'created_at',
        'updated_at',

    ];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
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
}
