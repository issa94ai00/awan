@extends('admin.layout')

@section('title', 'الرواتب')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-check-alt"></i> الرواتب</h1>
    <div>
        <a href="{{ route('admin.payrolls.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> مسيرة راتب جديدة
        </a>
        <form action="{{ route('admin.payrolls.auto-generate') }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="fas fa-magic"></i> توليد تلقائي
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم المسيرة</th>
                    <th>الموظف</th>
                    <th>الراتب الصافي</th>
                    <th>فترة الدفع</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->payroll_number }}</td>
                    <td>{{ $payroll->employee?->name ?? '-' }}</td>
                    <td>${{ number_format($payroll->net_salary, 2) }}</td>
                    <td>{{ $payroll->pay_period_start?->format('Y-m-d') }} إلى {{ $payroll->pay_period_end?->format('Y-m-d') }}</td>
                    <td>
                        <span class="badge badge-{{ $payroll->status === 'paid' ? 'success' : ($payroll->status === 'processed' ? 'warning' : 'secondary') }}">
                            {{ $payroll->status }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('admin.payrolls.show', $payroll) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.payrolls.edit', $payroll) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.payrolls.destroy', $payroll) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">لا توجد مسيرات رواتب</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
