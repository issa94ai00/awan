<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'name',
        'name_ar',
        'subject',
        'subject_ar',
        'body',
        'body_ar',
        'type',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    const TYPE_EMAIL = 'email';
    const TYPE_SMS = 'sms';
    const TYPE_PUSH = 'push';
    const TYPE_IN_APP = 'in_app';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('template_key', $key);
    }

    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_EMAIL => 'بريد إلكتروني',
            self::TYPE_SMS => 'رسالة نصية',
            self::TYPE_PUSH => 'إشعار دفع',
            self::TYPE_IN_APP => 'إشعار داخل التطبيق',
            default => $this->type,
        };
    }

    public function render(array $data = [], $locale = 'en'): string
    {
        $body = $locale === 'ar' && $this->body_ar ? $this->body_ar : $this->body;
        
        foreach ($data as $key => $value) {
            $body = str_replace("{{$key}}", $value, $body);
        }

        return $body;
    }

    public function renderSubject(array $data = [], $locale = 'en'): ?string
    {
        $subject = $locale === 'ar' && $this->subject_ar ? $this->subject_ar : $this->subject;
        
        if (!$subject) {
            return null;
        }

        foreach ($data as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
        }

        return $subject;
    }
}
