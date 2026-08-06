<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'code',
        'type',
        'integration_type',
        'config',
        'is_active',
        'auto_sync',
        'last_synced_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    const TYPE_ONLINE = 'online';
    const TYPE_RETAIL = 'retail';
    const TYPE_B2B = 'b2b';
    const TYPE_MARKETPLACE = 'marketplace';
    const TYPE_API = 'api';

    const INTEGRATION_SHOPIFY = 'shopify';
    const INTEGRATION_BIGCOMMERCE = 'bigcommerce';
    const INTEGRATION_CUSTOM = 'custom';

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'channel_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAutoSync($query)
    {
        return $query->where('auto_sync', true);
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_ONLINE => 'متجر إلكتروني',
            self::TYPE_RETAIL => 'متجر بيع بالتجزئة',
            self::TYPE_B2B => 'B2B',
            self::TYPE_MARKETPLACE => 'سوق إلكتروني',
            self::TYPE_API => 'API',
            default => $this->type,
        };
    }

    public function markAsSynced(): void
    {
        $this->last_synced_at = now();
        $this->save();
    }

    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    public function setConfigValue(string $key, $value): void
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
        $this->save();
    }
}
