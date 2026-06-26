<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type',
        'email_enabled',
        'sms_enabled',
        'push_enabled',
        'in_app_enabled',
        'channels',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'channels' => 'array',
    ];

    const TYPE_ALL = 'all';
    const TYPE_ORDER = 'order';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_SYSTEM = 'system';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->notification_type) {
            self::TYPE_ALL => 'الكل',
            self::TYPE_ORDER => 'الطلبات',
            self::TYPE_INVENTORY => 'المخزون',
            self::TYPE_WAREHOUSE => 'المستودع',
            self::TYPE_FINANCIAL => 'المالي',
            self::TYPE_SYSTEM => 'النظام',
            default => $this->notification_type,
        };
    }

    public function isChannelEnabled($channel): bool
    {
        if ($this->notification_type === self::TYPE_ALL) {
            return match($channel) {
                'email' => $this->email_enabled,
                'sms' => $this->sms_enabled,
                'push' => $this->push_enabled,
                'in_app' => $this->in_app_enabled,
                default => false,
            };
        }

        return in_array($channel, $this->channels ?? []);
    }
}
