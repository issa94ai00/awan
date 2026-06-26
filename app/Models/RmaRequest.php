<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RmaRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'sales_order_id',
        'rma_number',
        'reason',
        'type',
        'status',
        'reason_description',
        'return_address',
        'requested_at',
        'approved_at',
        'completed_at',
        'approved_by',
        'completed_by',
        'refund_amount',
        'refund_method',
        'admin_notes',
        'resolution_type',
    ];

    protected $casts = [
        'return_address' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    const REASON_DEFECTIVE = 'defective';
    const REASON_WRONG_ITEM = 'wrong_item';
    const REASON_DAMAGED = 'damaged';
    const REASON_NOT_AS_DESCRIBED = 'not_as_described';
    const REASON_CHANGED_MIND = 'changed_mind';
    const REASON_OTHER = 'other';

    const TYPE_REFUND = 'refund';
    const TYPE_EXCHANGE = 'exchange';
    const TYPE_STORE_CREDIT = 'store_credit';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items()
    {
        return $this->hasMany(RmaItem::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_APPROVED => 'موافق عليه',
            self::STATUS_REJECTED => 'مرفوض',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    public function getReasonTextAttribute(): string
    {
        return match($this->reason) {
            self::REASON_DEFECTIVE => 'معيب',
            self::REASON_WRONG_ITEM => 'منتج خاطئ',
            self::REASON_DAMAGED => 'تالف',
            self::REASON_NOT_AS_DESCRIBED => 'كما هو موصوف',
            self::REASON_CHANGED_MIND => 'تغيير الرأي',
            self::REASON_OTHER => 'أخرى',
            default => $this->reason,
        };
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_REFUND => 'استرداد',
            self::TYPE_EXCHANGE => 'تبديل',
            self::TYPE_STORE_CREDIT => 'رصيد المتجر',
            default => $this->type,
        };
    }

    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canReject(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canComplete(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function approve($userId): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    public function reject($userId, $reason = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->approved_by = $userId;
        $this->approved_at = now();
        if ($reason) {
            $this->admin_notes = $reason;
        }
        $this->save();
    }

    public function complete($userId, $refundAmount = null): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_by = $userId;
        $this->completed_at = now();
        if ($refundAmount !== null) {
            $this->refund_amount = $refundAmount;
        }
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function generateRmaNumber(): string
    {
        return 'RMA-' . str_pad($this->id ?? RmaRequest::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    public function getTotalRefundAmountAttribute(): float
    {
        return $this->items()->sum('refund_amount');
    }
}
