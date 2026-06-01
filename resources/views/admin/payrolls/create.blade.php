@extends('admin.layout')

@section('title', 'إنشاء مسيرة راتب')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-check-alt"></i> إنشاء مسيرة راتب جديدة</h1>
    <a href="{{ route('admin.payrolls.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.payrolls.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>الموظف</label>
                <select name="employee_id" class="form-control" required>
                    <option value="">اختر الموظف</option>
                    @foreach($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }} - الراتب الأساسي: ${{ number_format($employee->salary, 2) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>بداية فترة الدفع</label>
                <input type="date" name="pay_period_start" class="form-control" required>
            </div>
            <div class="form-group">
                <label>نهاية فترة الدفع</label>
                <input type="date" name="pay_period_end" class="form-control" required>
            </div>
            <div class="form-group">
                <label>تاريخ الدفع</label>
                <input type="date" name="payment_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label>الراتب الأساسي</label>
                <input type="number" name="basic_salary" class="form-control" required step="0.01">
            </div>
            <div class="form-group">
                <label>ساعات العمل الإضافي</label>
                <input type="number" name="overtime_hours" class="form-control" value="0" step="0.5">
            </div>
            <div class="form-group">
                <label>معدل العمل الإضافي</label>
                <input type="number" name="overtime_rate" class="form-control" value="0" step="0.01">
            </div>
            <div class="form-group">
                <label>المكافآت</label>
                <input type="number" name="bonuses" class="form-control" value="0" step="0.01">
            </div>
            <div class="form-group">
                <label>الخصومات</label>
                <input type="number" name="deductions" class="form-control" value="0" step="0.01">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-calculator"></i>
                <strong>الراتب الصافي:</strong> سيتم حسابه تلقائياً = الراتب الأساسي + (ساعات العمل الإضافي × المعدل) + المكافآت - الخصومات
            </div>
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
    </div>
</div>
@endsection
