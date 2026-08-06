@extends('admin.layout')

@section('title', 'التقرير المالي')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> التقرير المالي</h1>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.reports.financial') }}" method="GET" class="form-inline">
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
            <h3>${{ number_format($totalRevenue, 2) }}</h3>
            <p>إجمالي الإيرادات</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($pendingRevenue, 2) }}</h3>
            <p>إيرادات معلقة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($totalPayments, 2) }}</h3>
            <p>إجمالي المدفوعات</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($customerBalances->sum('balance'), 2) }}</h3>
            <p>أرصدة العملاء</p>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="dashboard-grid" style="margin-top: 20px;">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> الإيرادات الشهرية</h3>
        </div>
        <div class="card-body">
            <canvas id="monthlyRevenueChart" height="200"></canvas>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> المدفوعات حسب الطريقة</h3>
        </div>
        <div class="card-body">
            <canvas id="paymentsByMethodChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Customer Balances -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> أرصدة العملاء</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>الرصيد</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customerBalances as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td><span class="badge badge-{{ $customer->balance > 0 ? 'danger' : 'success' }}">${{ number_format($customer->balance, 2) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
    new Chart(monthlyRevenueCtx, {
        type: 'line',
        data: {
            labels: @js($monthlyRevenue->pluck('month') ?? []),
            datasets: [{
                label: 'الإيرادات',
                data: @js($monthlyRevenue->pluck('total') ?? []),
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

    const paymentsByMethodCtx = document.getElementById('paymentsByMethodChart').getContext('2d');
    new Chart(paymentsByMethodCtx, {
        type: 'doughnut',
        data: {
            labels: @js($paymentsByMethod->pluck('payment_method') ?? []),
            datasets: [{
                data: @js($paymentsByMethod->pluck('total') ?? []),
                backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b']
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
