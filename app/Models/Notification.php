<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
        'read_at',
        'data',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    const TYPE_ORDER = 'order';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_SYSTEM = 'system';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
    }

    public function markAsUnread()
    {
        $this->is_read = false;
        $this->read_at = null;
        $this->save();
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_INFO => 'معلومة',
            self::TYPE_SUCCESS => 'نجاح',
            self::TYPE_WARNING => 'تحذير',
            self::TYPE_ERROR => 'خطأ',
            self::TYPE_ORDER => 'طلب',
            self::TYPE_INVENTORY => 'مخزون',
            self::TYPE_WAREHOUSE => 'مستودع',
            self::TYPE_FINANCIAL => 'مالي',
            self::TYPE_SYSTEM => 'نظام',
            default => $this->type,
        };
    }

    public function getIconAttribute(): string
    {
        return match($this->type) {
            self::TYPE_SUCCESS => 'check-circle',
            self::TYPE_WARNING => 'alert-triangle',
            self::TYPE_ERROR => 'alert-circle',
            self::TYPE_ORDER => 'shopping-cart',
            self::TYPE_INVENTORY => 'package',
            self::TYPE_WAREHOUSE => 'warehouse',
            self::TYPE_FINANCIAL => 'dollar-sign',
            default => 'info',
        };
    }
}
