@extends('admin.layout')

@section('title', 'تقرير الرواتب')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-check-alt"></i> تقرير الرواتب</h1>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.reports.payroll') }}" method="GET" class="form-inline">
            <div class="form-group">
                <label>من تاريخ:</label>
                <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
            </div>
            <div class="form-group">
                <label>إلى تاريخ:</label>
                <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-primary">تصفية</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-top: 20px;">
    <div class="stat-card">
        <div class="stat-info">
            <h3>{{ $payrolls->count() }}</h3>
            <p>مسيرات الرواتب</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($totalPayroll, 2) }}</h3>
            <p>إجمالي الرواتب</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($totalBasicSalary, 2) }}</h3>
            <p>الرواتب الأساسية</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($totalOvertime, 2) }}</h3>
            <p>العمل الإضافي</p>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="dashboard-grid" style="margin-top: 20px;">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> الرواتب الشهرية</h3>
        </div>
        <div class="card-body">
            <canvas id="monthlyPayrollChart" height="200"></canvas>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> الرواتب حسب الحالة</h3>
        </div>
        <div class="card-body">
            <canvas id="payrollByStatusChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Payroll Details -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> تفاصيل الرواتب</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>الراتب الأساسي</th>
                    <th>العمل الإضافي</th>
                    <th>المكافآت</th>
                    <th>الخصومات</th>
                    <th>الراتب الصافي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->employee?->name ?? '-' }}</td>
                    <td>${{ number_format($payroll->basic_salary, 2) }}</td>
                    <td>${{ number_format($payroll->overtime_hours * $payroll->overtime_rate, 2) }}</td>
                    <td>${{ number_format($payroll->bonuses, 2) }}</td>
                    <td>${{ number_format($payroll->deductions, 2) }}</td>
                    <td><strong>${{ number_format($payroll->net_salary, 2) }}</strong></td>
                    <td>
                        <span class="badge badge-{{ $payroll->status === 'paid' ? 'success' : ($payroll->status === 'processed' ? 'warning' : 'secondary') }}">
                            {{ $payroll->status }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const monthlyPayrollCtx = document.getElementById('monthlyPayrollChart').getContext('2d');
    new Chart(monthlyPayrollCtx, {
        type: 'line',
        data: {
            labels: @js($monthlyPayroll->pluck('month') ?? []),
            datasets: [{
                label: 'الرواتب',
                data: @js($monthlyPayroll->pluck('total') ?? []),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    const payrollByStatusCtx = document.getElementById('payrollByStatusChart').getContext('2d');
    new Chart(payrollByStatusCtx, {
        type: 'doughnut',
        data: {
            labels: @js($payrollByStatus->pluck('status') ?? []),
            datasets: [{
                data: @js($payrollByStatus->pluck('total') ?? []),
                backgroundColor: ['#667eea', '#f093fb', '#4facfe']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>
@endsection
@endsection
