<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'symbol',
        'decimal_places',
        'rounding_step',
        'is_base',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'rounding_step' => 'integer',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Codes are identity here, so 'usd' and 'USD' must not become two rows. */
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function rates()
    {
        return $this->hasMany(CurrencyRate::class)->orderByDesc('effective_at');
    }

    /** The rate in force at a moment — the newest one that had already started. */
    public function rateAt(?\DateTimeInterface $moment = null): ?CurrencyRate
    {
        return $this->rates()
            ->where('effective_at', '<=', $moment ?? now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return trim((string) $this->name_en) ?: $this->name_ar;
        }

        return trim((string) $this->name_ar) ?: (string) $this->name_en;
    }
}
