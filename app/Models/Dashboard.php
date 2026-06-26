<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'type',
        'layout_config',
        'created_by',
        'is_public',
        'is_default',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'is_public' => 'boolean',
        'is_default' => 'boolean',
    ];

    const TYPE_EXECUTIVE = 'executive';
    const TYPE_SALES = 'sales';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_WAREHOUSE = 'warehouse';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_CUSTOM = 'custom';

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function widgets()
    {
        return $this->hasMany(DashboardWidget::class, 'dashboard_id')->orderBy('sort_order');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_EXECUTIVE => 'تنفيذي',
            self::TYPE_SALES => 'المبيعات',
            self::TYPE_INVENTORY => 'المخزون',
            self::TYPE_WAREHOUSE => 'المستودع',
            self::TYPE_FINANCIAL => 'المالي',
            self::TYPE_CUSTOM => 'مخصص',
            default => $this->type,
        };
    }

    public function getActiveWidgetsAttribute()
    {
        return $this->widgets()->where('is_active', true)->get();
    }
}
