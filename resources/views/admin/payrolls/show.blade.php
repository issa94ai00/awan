@extends('admin.layout')

@section('title', 'عرض تفاصيل مسيرة الراتب')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-check-alt"></i> مسيرة راتب #{{ $payroll->payroll_number }}</h1>
    <a href="{{ route('admin.payrolls.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>الموظف:</strong> {{ $payroll->employee?->name ?? '-' }}</p>
                <p><strong>فترة الدفع:</strong> {{ $payroll->pay_period_start?->format('Y-m-d') }} إلى {{ $payroll->pay_period_end?->format('Y-m-d') }}</p>
                <p><strong>تاريخ الدفع:</strong> {{ $payroll->payment_date?->format('Y-m-d') }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>الحالة:</strong> {{ $payroll->status }}</p>
            </div>
        </div>
        
        <h3>تفاصيل الراتب</h3>
        <table class="table">
            <tr>
                <td><strong>الراتب الأساسي:</strong></td>
                <td>${{ number_format($payroll->basic_salary, 2) }}</td>
            </tr>
            <tr>
                <td><strong>ساعات العمل الإضافي:</strong></td>
                <td>{{ $payroll->overtime_hours }} ساعة × ${{ number_format($payroll->overtime_rate, 2) }} = ${{ number_format($payroll->overtime_hours * $payroll->overtime_rate, 2) }}</td>
            </tr>
            <tr>
                <td><strong>المكافآت:</strong></td>
                <td>${{ number_format($payroll->bonuses, 2) }}</td>
            </tr>
            <tr>
                <td><strong>الخصومات:</strong></td>
                <td>${{ number_format($payroll->deductions, 2) }}</td>
            </tr>
            <tr class="success">
                <td><strong>الراتب الصافي:</strong></td>
                <td><strong>${{ number_format($payroll->net_salary, 2) }}</strong></td>
            </tr>
        </table>
        
        @if($payroll->notes)
        <p><strong>ملاحظات:</strong> {{ $payroll->notes }}</p>
        @endif
    </div>
</div>
@endsection
