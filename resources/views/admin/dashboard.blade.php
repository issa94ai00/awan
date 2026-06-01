@extends('admin.layout')

@section('title', 'لوحة التحكم')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> لوحة التحكم</h1>
    <p>نظرة عامة على أداء النظام والإحصائيات</p>
</div>

<!-- ERP KPIs -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $quotesCount ?? 0 }}</h3>
            <p>عروض الأسعار</p>
            <small>{{ $quotesPending ?? 0 }} معلق</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $salesOrdersCount ?? 0 }}</h3>
            <p>طلبات البيع</p>
            <small>{{ $salesOrdersPending ?? 0 }} معلق</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $invoicesCount ?? 0 }}</h3>
            <p>الفواتير</p>
            <small>{{ $invoicesPending ?? 0 }} معلق</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format($paymentsTotal ?? 0, 2) }}</h3>
            <p>إجمالي التحصيل</p>
            <small>{{ $paymentsCount ?? 0 }} عملية</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $customersCount ?? 0 }}</h3>
            <p>العملاء</p>
            <small>رصيد: ${{ number_format($customerBalances ?? 0, 2) }}</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $suppliersCount ?? 0 }}</h3>
            <p>الموردون</p>
            <small>رصيد: ${{ number_format($supplierBalances ?? 0, 2) }}</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $lowStockProducts ?? 0 }}</h3>
            <p>تنبيهات المخزون</p>
            <small>منخفض</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format($totalRevenue ?? 0, 2) }}</h3>
            <p>إجمالي الإيرادات</p>
            <small>مستحق: ${{ number_format($totalDue ?? 0, 2) }}</small>
        </div>
    </div>
</div>

<!-- Stock Alerts -->
@if($lowStockProducts > 0)
<div class="alert alert-warning" style="margin-bottom: 20px;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>تنبيه:</strong> هناك {{ $lowStockProducts }} منتجات بمخزون منخفض. 
    <a href="{{ route('admin.stock-alerts') }}" class="btn btn-sm btn-warning">عرض التفاصيل</a>
</div>
@endif

<div class="dashboard-grid">
    <!-- Monthly Sales Chart -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> المبيعات الشهرية</h3>
        </div>
        <div class="card-body">
            <canvas id="monthlySalesChart" height="200"></canvas>
        </div>
    </div>

    <!-- Sales by Status -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> حالة الطلبات</h3>
        </div>
        <div class="card-body">
            <canvas id="salesByStatusChart" height="200"></canvas>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Latest Quotes -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice"></i> آخر عروض الأسعار</h3>
            <a href="{{ route('admin.quotes.index') }}" class="btn btn-sm">عرض الكل</a>
        </div>
        <div class="card-body">
            @if(isset($latestQuotes) && $latestQuotes->count() > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم العرض</th>
                            <th>العميل</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestQuotes as $quote)
                        <tr>
                            <td>{{ $quote->quote_number }}</td>
                            <td>{{ $quote->customer?->name ?? '-' }}</td>
                            <td>${{ number_format($quote->total, 2) }}</td>
                            <td>
                                <span class="badge badge-{{ $quote->status === 'accepted' ? 'success' : ($quote->status === 'draft' ? 'warning' : 'secondary') }}">
                                    {{ $quote->status }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty-state">لا توجد عروض أسعار</p>
            @endif
        </div>
    </div>

    <!-- Latest Sales Orders -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-shopping-cart"></i> آخر طلبات البيع</h3>
            <a href="{{ route('admin.sales-orders.index') }}" class="btn btn-sm">عرض الكل</a>
        </div>
        <div class="card-body">
            @if(isset($latestSalesOrders) && $latestSalesOrders->count() > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestSalesOrders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->customer?->name ?? '-' }}</td>
                            <td>${{ number_format($order->total, 2) }}</td>
                            <td>
                                <span class="badge badge-{{ $order->status === 'delivered' ? 'success' : ($order->status === 'pending' ? 'warning' : 'secondary') }}">
                                    {{ $order->status }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty-state">لا توجد طلبات بيع</p>
            @endif
        </div>
    </div>

    <!-- Latest Payments -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-money-bill-wave"></i> آخر المدفوعات</h3>
            <a href="{{ route('admin.payments.index') }}" class="btn btn-sm">عرض الكل</a>
        </div>
        <div class="card-body">
            @if(isset($latestPayments) && $latestPayments->count() > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>رقم الدفعة</th>
                            <th>العميل</th>
                            <th>المبلغ</th>
                            <th>الطريقة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestPayments as $payment)
                        <tr>
                            <td>{{ $payment->payment_number }}</td>
                            <td>{{ $payment->customer?->name ?? '-' }}</td>
                            <td>${{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->payment_method }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty-state">لا توجد مدفوعات</p>
            @endif
        </div>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Monthly Sales Chart
    const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
    new Chart(monthlySalesCtx, {
        type: 'line',
        data: {
            labels: @js($monthlySales->pluck('month') ?? []),
            datasets: [{
                label: 'المبيعات',
                data: @js($monthlySales->pluck('total') ?? []),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Sales by Status Chart
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
