<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'products',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'products' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
