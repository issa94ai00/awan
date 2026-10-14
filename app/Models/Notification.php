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

    protected $appends = [
        'type_text',
        'icon',
        'badge_type',
        'target_route',
        'category',
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

    /**
     * The preference categories a user can switch on and off. The generic
     * types (info, success, warning, error) are announcements, so they fall
     * under 'system'.
     */
    const CATEGORIES = [
        self::TYPE_ORDER,
        self::TYPE_INVENTORY,
        self::TYPE_WAREHOUSE,
        self::TYPE_FINANCIAL,
        self::TYPE_SYSTEM,
    ];

    public static function categoryFor(?string $type): string
    {
        return in_array($type, self::CATEGORIES, true) ? $type : self::TYPE_SYSTEM;
    }

    /** The notification types that belong to a category. */
    public static function typesInCategory(string $category): array
    {
        return $category === self::TYPE_SYSTEM
            ? [self::TYPE_SYSTEM, self::TYPE_INFO, self::TYPE_SUCCESS, self::TYPE_WARNING, self::TYPE_ERROR]
            : [$category];
    }

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

    public function scopeInCategory($query, string $category)
    {
        return $query->whereIn('type', self::typesInCategory($category));
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

    public function getCategoryAttribute(): string
    {
        return self::categoryFor($this->type);
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
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        $like = '%' . addcslashes($search, '%_\\') . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('title', 'like', $like)
              ->orWhere('message', 'like', $like);
        });
    }

    public function getBadgeTypeAttribute(): string
    {
        return match($this->type) {
            self::TYPE_SUCCESS => 'success',
            self::TYPE_WARNING => 'warning',
            self::TYPE_ERROR => 'danger',
            self::TYPE_ORDER => 'primary',
            self::TYPE_INVENTORY => 'warning',
            self::TYPE_WAREHOUSE => 'info',
            self::TYPE_FINANCIAL => 'success',
            self::TYPE_SYSTEM => 'info',
            default => 'info',
        };
    }

    public function getTargetRouteAttribute(): ?string
    {
        $data = $this->data ?? [];
        if (!empty($data['route'])) {
            return $data['route'];
        }
        if (!empty($data['url'])) {
            return $data['url'];
        }
        if (!empty($data['order_id'])) {
            return "/admin/sales/sales-orders/{$data['order_id']}/edit";
        }
        if (!empty($data['invoice_id'])) {
            return "/admin/sales/invoices/{$data['invoice_id']}/edit";
        }
        if (!empty($data['order_number'])) {
            return "/admin/sales/sales-orders?search=" . urlencode($data['order_number']);
        }
        if (!empty($data['invoice_number'])) {
            return "/admin/sales/invoices?search=" . urlencode($data['invoice_number']);
        }
        if (!empty($data['product_id'])) {
            return "/admin/inventory";
        }
        if (!empty($data['action_url'])) {
            return $data['action_url'];
        }

        return match($this->type) {
            self::TYPE_ORDER => '/admin/sales/sales-orders',
            self::TYPE_INVENTORY => '/admin/stock',
            self::TYPE_WAREHOUSE => '/admin/wms/warehouses',
            self::TYPE_FINANCIAL => '/admin/sales/invoices',
            default => null,
        };
    }
}
