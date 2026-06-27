<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_number',
        'invoice_id',
        'customer_id',
        'description',
        'notes',
        'amount',
        'category',
        'expense_date',
        'status',
        'created_by',
        'currency',
        'exchange_rate',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'exchange_rate' => 'decimal:4',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const CATEGORY_SHIPPING = 'shipping';
    const CATEGORY_PACKAGING = 'packaging';
    const CATEGORY_HANDLING = 'handling';
    const CATEGORY_OTHER = 'other';

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'معلق',
            self::STATUS_APPROVED => 'موافق عليه',
            self::STATUS_REJECTED => 'مرفوض',
        ];
    }

    public static function getCategoryOptions(): array
    {
        return [
            self::CATEGORY_SHIPPING => 'شحن',
            self::CATEGORY_PACKAGING => 'تغليف',
            self::CATEGORY_HANDLING => 'معالجة',
            self::CATEGORY_OTHER => 'أخرى',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::getCategoryOptions()[$this->category] ?? $this->category;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generateExpenseNumber(): string
    {
        return 'EXP-' . str_pad($this->id ?? Expense::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
