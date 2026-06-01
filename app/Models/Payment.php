<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'invoice_id',
        'customer_id',
        'payment_method',
        'status',
        'amount',
        'payment_date',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CHECK = 'check';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_FAILED => 'فشل',
            self::STATUS_REFUNDED => 'مسترد',
            default => $this->status,
        };
    }

    public function getPaymentMethodTextAttribute()
    {
        return match($this->payment_method) {
            self::METHOD_CASH => 'نقدي',
            self::METHOD_CARD => 'بطاقة',
            self::METHOD_BANK_TRANSFER => 'تحويل بنكي',
            self::METHOD_CHECK => 'شيك',
            default => $this->payment_method,
        };
    }

    public function generatePaymentNumber(): string
    {
        return 'PAY-' . str_pad($this->id ?? Payment::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
