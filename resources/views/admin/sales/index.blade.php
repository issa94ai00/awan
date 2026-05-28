@extends('admin.layout')

@section('title', 'المبيعات')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> المبيعات</h1>
    <p>ملخص فواتير المبيعات والعملاء.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $invoicesCount }}</h3>
            <p>عدد الفواتير</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $customersCount }}</h3>
            <p>العملاء</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format($revenue, 2) }}</h3>
            <p>الإيرادات المدفوعة</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice"></i> الروابط السريعة</h3>
        </div>
        <div class="card-body">
            <ul class="module-actions">
                <li><a href="{{ route('admin.sales.invoices') }}">الفواتير</a></li>
                <li><a href="{{ route('admin.sales.customers') }}">العملاء</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
