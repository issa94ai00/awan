<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'department',
        'hire_date',
        'salary',
        'status',
        'notes',
        'avatar',
        'job_title',
        'employment_type',
        'contract_type',
        'bonus',
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
        'hire_date' => 'date',
        'salary' => 'decimal:2',
        'bonus' => 'decimal:2',
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

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
