<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A replaced version of a sales order, kept as it stood when an edit past
 * confirmation superseded it. Append-only.
 */
class SalesOrderRevision extends Model
{
    protected $fillable = [
        'sales_order_id',
        'revision',
        'status',
        'subtotal',
        'discount',
        'tax',
        'shipping_cost',
        'total',
        'snapshot',
        'note',
        'replaced_by',
    ];

    protected $casts = [
        'revision' => 'integer',
        'subtotal' => 'decimal:5',
        'discount' => 'decimal:5',
        'tax' => 'decimal:5',
        'shipping_cost' => 'decimal:5',
        'total' => 'decimal:5',
        'snapshot' => 'array',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }
}
