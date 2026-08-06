@extends('admin.layout')

@section('title', 'التقارير')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> التقارير ولوحات التحكم</h1>
    <p>ملخص لنقاط الأداء الرئيسية وإمكانية تصدير البيانات.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format($salesRevenue, 2) }}</h3>
            <p>الإيرادات المدفوعة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $openInvoices }}</h3>
            <p>الفواتير المعلقة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-truck-loading"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $purchaseOrders }}</h3>
            <p>أوامر الشراء</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $employeeCount }}</h3>
            <p>الموظفون</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-file-export"></i> تصدير التقارير</h3>
            <a href="{{ route('admin.reports.export') }}" class="btn btn-sm">انتقال</a>
        </div>
        <div class="card-body">
            <p>يمكنك تنزيل تقارير الفترة المالية وتقارير المبيعات والمخزون من هنا.</p>
        </div>
    </div>
</div>
@endsection
