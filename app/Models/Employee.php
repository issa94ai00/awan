<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'warehouse_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'department',
        'hire_date',
        'salary',
        // What has been accrued towards the end-of-service benefit, and up to
        // when. Kept on the record so the register reads without replaying the
        // journal, and so a month cannot be accrued twice.
        'end_of_service_accrued',
        'end_of_service_through',
        'status',
        'notes',
        'avatar',
        'job_title',
        'employment_type',
        'contract_type',
        'bonus',
        'commission_rate',
        'monthly_sales_target',
        'national_id',
        'nationality',
        'contract_start',
        'contract_end',
        'emergency_contact_name',
        'emergency_contact_phone',
        'bank_name',
        'bank_account_number',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'hire_date' => 'date',
        'salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'monthly_sales_target' => 'decimal:2',
        'contract_start' => 'date',
        'contract_end' => 'date',
    ];

    protected $appends = ['name'];

    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function setNameAttribute($value)
    {
        $parts = array_filter(explode(' ', trim($value)), fn ($part) => $part !== '');
        $this->attributes['first_name'] = array_shift($parts) ?: '';
        $this->attributes['last_name'] = implode(' ', $parts);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_employee')
            ->withTimestamps()
            ->withPivot('id');
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'assigned_employee_id');
    }

    /**
     * Invoices this employee is the sales rep of record for.
     *
     * Reads `invoices.assigned_employee_id` directly. It used to go through
     * `sales_orders.assigned_employee_id` instead, which returned nothing for
     * every invoice raised outside the sales-order flow — the header column
     * this table already carries for exactly this purpose was never read.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'assigned_employee_id');
    }

    /** Invoices reached via a sales order this employee is assigned to. */
    public function invoicesViaSalesOrders()
    {
        return $this->hasManyThrough(Invoice::class, SalesOrder::class, 'assigned_employee_id', 'sales_order_id');
    }

    public function commissionRecords()
    {
        return $this->hasMany(EmployeeCommission::class);
    }
}
