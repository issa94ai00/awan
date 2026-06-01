@extends('admin.layout')

@section('title', 'تقرير المبيعات')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> تقرير المبيعات</h1>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.reports.sales') }}" method="GET" class="form-inline">
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
            <h3>{{ $quotes->count() }}</h3>
            <p>عروض الأسعار</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>{{ $salesOrders->count() }}</h3>
            <p>طلبات البيع</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>{{ $invoices->count() }}</h3>
            <p>الفواتير</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($payments->sum('amount'), 2) }}</h3>
            <p>إجمالي التحصيل</p>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="dashboard-grid" style="margin-top: 20px;">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> المبيعات اليومية</h3>
        </div>
        <div class="card-body">
            <canvas id="dailySalesChart" height="200"></canvas>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> الطلبات حسب الحالة</h3>
        </div>
        <div class="card-body">
            <canvas id="salesByStatusChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Top Customers -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> أفضل العملاء</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>إجمالي المبيعات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topCustomers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>${{ number_format($customer->total_sales, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Top Products -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-boxes"></i> أكثر الأصناف مبيعاً</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الكمية المباعة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topProducts as $product)
                <tr>
                    <td>{{ $product->name_ar }}</td>
                    <td>{{ $product->total_sold }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
    new Chart(dailySalesCtx, {
        type: 'line',
        data: {
            labels: @js($dailySales->pluck('date') ?? []),
            datasets: [{
                label: 'المبيعات',
                data: @js($dailySales->pluck('total') ?? []),
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

    const salesByStatusCtx = document.getElementById('salesByStatusChart').getContext('2d');
    new Chart(salesByStatusCtx, {
        type: 'doughnut',
        data: {
            labels: @js($salesByStatus->pluck('status') ?? []),
            datasets: [{
                data: @js($salesByStatus->pluck('count') ?? []),
                backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a']
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
