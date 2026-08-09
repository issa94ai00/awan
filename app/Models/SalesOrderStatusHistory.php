<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stage move of a sales order.
 *
 * Append-only by intent: there is no update path anywhere in the application,
 * because a history that can be edited answers nothing.
 */
class SalesOrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'from_status',
        'to_status',
        'note',
        'effects',
        'user_id',
    ];

    protected $casts = [
        'effects' => 'array',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
