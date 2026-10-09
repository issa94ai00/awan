<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'module',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    protected $appends = [
        'action_text',
        'module_text',
        'severity',
        'changes',
    ];

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_VIEW = 'view';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_FAILED_LOGIN = 'failed_login';
    const ACTION_PASSWORD_CHANGE = 'password_change';
    const ACTION_REVOKE_SESSION = 'revoke_session';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT = 'reject';
    const ACTION_CANCEL = 'cancel';
    const ACTION_CREATE_RMA = 'create_rma';
    const ACTION_APPROVE_RMA = 'approve_rma';
    const ACTION_RECEIVE_ITEMS = 'receive_items';
    const ACTION_COMPLETE_RMA = 'complete_rma';

    const MODULE_PRODUCTS = 'products';
    const MODULE_INVENTORY = 'inventory';
    const MODULE_ORDERS = 'orders';
    const MODULE_CUSTOMERS = 'customers';
    const MODULE_SUPPLIERS = 'suppliers';
    const MODULE_WAREHOUSE = 'warehouse';
    const MODULE_FINANCE = 'finance';
    const MODULE_USERS = 'users';
    const MODULE_SECURITY = 'security';
    const MODULE_SETTINGS = 'settings';
    const MODULE_WORKFLOWS = 'workflows';
    const MODULE_REPORTS = 'reports';
    const MODULE_RMA = 'rma';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByEntity($query, $entityType, $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function getActionTextAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATE => 'إنشاء',
            self::ACTION_UPDATE => 'تحديث',
            self::ACTION_DELETE => 'حذف',
            self::ACTION_VIEW => 'عرض',
            self::ACTION_LOGIN => 'تسجيل دخول',
            self::ACTION_LOGOUT => 'تسجيل خروج',
            self::ACTION_FAILED_LOGIN => 'فشل تسجيل الدخول',
            self::ACTION_PASSWORD_CHANGE => 'تغيير كلمة المرور',
            self::ACTION_REVOKE_SESSION => 'إنهاء جلسة / جهاز',
            self::ACTION_EXPORT => 'تصدير',
            self::ACTION_IMPORT => 'استيراد',
            self::ACTION_APPROVE => 'موافقة',
            self::ACTION_REJECT => 'رفض',
            self::ACTION_CANCEL => 'إلغاء',
            self::ACTION_CREATE_RMA => 'إنشاء طلب إرجاع',
            self::ACTION_APPROVE_RMA => 'موافقة على إرجاع',
            self::ACTION_RECEIVE_ITEMS => 'استلام مرتجعات',
            self::ACTION_COMPLETE_RMA => 'إكمال إرجاع',
            default => $this->action,
        };
    }

    public function getModuleTextAttribute(): string
    {
        return match($this->module) {
            self::MODULE_PRODUCTS => 'المنتجات',
            self::MODULE_INVENTORY => 'المخزون',
            self::MODULE_ORDERS => 'الطلبات',
            self::MODULE_CUSTOMERS => 'العملاء',
            self::MODULE_SUPPLIERS => 'الموردين',
            self::MODULE_WAREHOUSE => 'المستودع',
            self::MODULE_FINANCE => 'المالي',
            self::MODULE_USERS => 'المستخدمين',
            self::MODULE_SECURITY => 'الأمان والجلسات',
            self::MODULE_SETTINGS => 'الإعدادات',
            self::MODULE_WORKFLOWS => 'سير العمل',
            self::MODULE_REPORTS => 'التقارير',
            self::MODULE_RMA => 'المرتجعات (RMA)',
            default => $this->module ?: 'عام',
        };
    }

    public function getSeverityAttribute(): string
    {
        return match($this->action) {
            self::ACTION_FAILED_LOGIN, 'force_delete' => 'critical',
            self::ACTION_DELETE, self::ACTION_CANCEL, self::ACTION_REJECT, self::ACTION_PASSWORD_CHANGE, self::ACTION_REVOKE_SESSION => 'warning',
            default => 'info',
        };
    }

    public function getChangesAttribute(): array
    {
        $changes = [];

        if (is_array($this->old_values) && is_array($this->new_values)) {
            $allKeys = array_unique(array_merge(array_keys($this->old_values), array_keys($this->new_values)));
            foreach ($allKeys as $key) {
                $oldValue = $this->old_values[$key] ?? null;
                $newValue = $this->new_values[$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        } elseif (is_array($this->new_values) && !empty($this->new_values)) {
            foreach ($this->new_values as $key => $newValue) {
                $changes[$key] = [
                    'old' => null,
                    'new' => $newValue,
                ];
            }
        } elseif (is_array($this->old_values) && !empty($this->old_values)) {
            foreach ($this->old_values as $key => $oldValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $changes;
    }

    public function getEntityTypeAttribute(): string
    {
        return class_basename($this->attributes['entity_type'] ?? '');
    }
}
