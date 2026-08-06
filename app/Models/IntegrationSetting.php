<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_name',
        'api_domain',
        'access_token',
        'sync_stock',
        'sync_orders',
        'last_synced_at',
    ];

    protected $casts = [
        'sync_stock' => 'boolean',
        'sync_orders' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
