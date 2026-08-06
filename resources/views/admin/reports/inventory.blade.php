@extends('admin.layout')

@section('title', 'تقرير المخزون')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-boxes"></i> تقرير المخزون</h1>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-top: 20px;">
    <div class="stat-card">
        <div class="stat-info">
            <h3>{{ $products->count() }}</h3>
            <p>إجمالي الأصناف</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>{{ $lowStockProducts->count() }}</h3>
            <p>مخزون منخفض</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>{{ $outOfStockProducts->count() }}</h3>
            <p>نفد من المخزون</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>${{ number_format($totalStockValue, 2) }}</h3>
            <p>قيمة المخزون</p>
        </div>
    </div>
</div>

<!-- Stock by Category -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> المخزون حسب الفئة</h3>
    </div>
    <div class="card-body">
        <canvas id="stockByCategoryChart" height="200"></canvas>
    </div>
</div>

<!-- Low Stock Products -->
@if($lowStockProducts->count() > 0)
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle"></i> منتجات بمخزون منخفض</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الفئة</th>
                    <th>الكمية الحالية</th>
                    <th>السعر</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lowStockProducts as $product)
                <tr>
                    <td>{{ $product->name_ar }}</td>
                    <td>{{ $product->category?->name_ar ?? '-' }}</td>
                    <td><span class="badge badge-warning">{{ $product->stock_quantity }}</span></td>
                    <td>${{ number_format($product->price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Recent Purchase Receipts -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-truck-loading"></i> آخر إيصالات الاستلام</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم الإيصال</th>
                    <th>المورد</th>
                    <th>تاريخ الاستلام</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseReceipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_number }}</td>
                    <td>{{ $receipt->supplier?->name ?? '-' }}</td>
                    <td>{{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const stockByCategoryCtx = document.getElementById('stockByCategoryChart').getContext('2d');
    new Chart(stockByCategoryCtx, {
        type: 'doughnut',
        data: {
            labels: @js($stockByCategory->pluck('category.name_ar') ?? []),
            datasets: [{
                data: @js($stockByCategory->pluck('total') ?? []),
                backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#a8edea']
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
