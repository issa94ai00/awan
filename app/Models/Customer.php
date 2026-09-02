<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'address',
        'source',
        'status',
        'notes',
        'balance',
        'credit_limit',
        'tax_number',
        'city',
        'state',
        'country',
        'postal_code',
        'currency',
        'total_purchases',
        'last_purchase_at',
        'password',
        'auth_token',
        'employee_id',
    ];

    protected $casts = [
        'balance' => 'decimal:5',
        'credit_limit' => 'decimal:5',
        'total_purchases' => 'decimal:5',
        'last_purchase_at' => 'date',
    ];

    protected $hidden = [
        'password',
        'auth_token',
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function contacts()
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function rmaRequests()
    {
        return $this->hasMany(RmaRequest::class);
    }

    /** Credits owed to this customer, mostly raised by returns. */
    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function updateBalance($amount): void
    {
        $this->increment('balance', $amount);
    }

    public function getRemainingCreditAttribute(): float
    {
        return $this->credit_limit - $this->balance;
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'customer_employee')
            ->withTimestamps()
            ->withPivot(['id', 'is_primary']);
    }

    public function customerEmployees()
    {
        return $this->belongsToMany(Employee::class, 'customer_employee')
            ->withTimestamps()
            ->withPivot(['id', 'is_primary']);
    }

    public function primaryEmployee()
    {
        return $this->belongsToMany(Employee::class, 'customer_employee')
            ->withTimestamps()
            ->withPivot(['id', 'is_primary'])
            ->wherePivot('is_primary', true);
    }

    public function syncPrimaryEmployee(int $employeeId): void
    {
        $this->customerEmployees()->sync(
            $this->customerEmployees()->pluck('employees.id')->mapWithKeys(fn ($id) => [
                (int) $id => ['is_primary' => (int) $id === (int) $employeeId],
            ])->all()
        );

        $this->employee_id = $employeeId;
        $this->saveQuietly();
    }

    /**
     * Records that an employee now serves this customer.
     *
     * Called whenever a rep raises an order for someone: taking the sale is
     * what makes the customer theirs. It is deliberately additive — an
     * existing link is left exactly as it is, so a second rep serving the
     * same customer never demotes the one who already owns the relationship.
     * The first employee to arrive becomes the primary contact.
     */
    public function assignEmployee(int $employeeId): void
    {
        $alreadyLinked = $this->customerEmployees()
            ->where('employees.id', $employeeId)
            ->exists();

        if (! $alreadyLinked) {
            $hasPrimary = $this->customerEmployees()
                ->wherePivot('is_primary', true)
                ->exists();

            $this->customerEmployees()->attach($employeeId, [
                'is_primary' => ! $hasPrimary,
            ]);
        }

        // The legacy single-employee column still drives the rep's customer
        // list, so it is filled in when nobody owns the customer yet.
        if ($this->employee_id === null) {
            $this->employee_id = $employeeId;
            $this->saveQuietly();
        }

        $this->unsetRelation('customerEmployees')
            ->unsetRelation('employees')
            ->unsetRelation('primaryEmployee');
    }

    // Keep the old relationship for backward compatibility
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
