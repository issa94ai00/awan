<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_number',
        'employee_id',
        'pay_period_start',
        'pay_period_end',
        'payment_date',
        'basic_salary',
        'overtime_hours',
        'overtime_rate',
        'overtime_pay',
        'bonuses',
        'deductions',
        'net_salary',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'payment_date' => 'date',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSED = 'processed';
    const STATUS_PAID = 'paid';

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_PROCESSED => 'تمت المعالجة',
            self::STATUS_PAID => 'مدفوع',
            default => $this->status,
        };
    }

    public function generatePayrollNumber(): string
    {
        return 'PAY-' . str_pad($this->id ?? Payroll::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    public function calculateNetSalary(): void
    {
        $this->overtime_pay = $this->overtime_hours * $this->overtime_rate;
        $this->net_salary = $this->basic_salary + $this->overtime_pay + $this->bonuses - $this->deductions;
    }
}
