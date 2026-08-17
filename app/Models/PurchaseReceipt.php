<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'supplier_id',
        // Without this the controller's validated warehouse_id was silently
        // discarded on create, so the receipt never recorded where the goods
        // it brought in actually went.
        'warehouse_id',
        'receipt_date',
        // Tax the supplier charged, held apart from the goods: it is a claim
        // against the tax authority, not part of what the stock cost.
        'tax_amount',
        'status',
        'currency',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'tax_amount' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function generateReceiptNumber(): string
    {
        return 'PR-' . str_pad($this->id ?? PurchaseReceipt::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
