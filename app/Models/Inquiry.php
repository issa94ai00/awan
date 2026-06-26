<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inquiries';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'priority',
        'product_id',
        'user_id',
        'assigned_to',
        'closed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'closed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_NEW = 'new';
    const STATUS_READ = 'read';
    const STATUS_REPLIED = 'replied';

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_NEW => 'جديد',
            self::STATUS_READ => 'مقروء',
            self::STATUS_REPLIED => 'تم الرد',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeUnread($query)
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_READ]);
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function markAsRead(): void
    {
        if ($this->status === self::STATUS_NEW) {
            $this->update(['status' => self::STATUS_READ]);
        }
    }

    public function markAsReplied(): void
    {
        $this->update(['status' => self::STATUS_REPLIED]);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusText(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public static function getSubjectOptions(): array
    {
        return [
            'product_inquiry' => 'استفسار عن منتج',
            'price_quote' => 'طلب عرض سعر',
            'delivery' => 'استفسار حول التوصيل',
            'technical_support' => 'دعم فني',
            'partnership' => 'شراكة',
            'other' => 'أخرى',
        ];
    }

    public function getSubjectLabelAttribute(): string
    {
        return self::getSubjectOptions()[$this->subject] ?? $this->subject;
    }

    public static function getPriorityOptions(): array
    {
        return [
            'low' => 'منخفض',
            'medium' => 'متوسط',
            'high' => 'عالي',
            'urgent' => 'عاجل',
        ];
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::getPriorityOptions()[$this->priority] ?? $this->priority;
    }

    public function getClosedAtFormattedAttribute(): ?string
    {
        return $this->closed_at ? $this->closed_at->format('Y-m-d H:i:s') : null;
    }

    public function replies(): HasMany
    {
        return $this->hasMany(InquiryReply::class);
    }

    public function close(): void
    {
        $this->update(['closed_at' => now()]);
    }

    public function reopen(): void
    {
        $this->update(['closed_at' => null]);
    }

    public function assignTo(int $adminId): void
    {
        $this->update(['assigned_to' => $adminId]);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
