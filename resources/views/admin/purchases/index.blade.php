@extends('admin.layout')

@section('title', 'المشتريات')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> المشتريات</h1>
    <p>إدارة الموردين وأوامر الشراء.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $suppliersCount }}</h3>
            <p>الموردون</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $ordersCount }}</h3>
            <p>أوامر الشراء</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $pendingOrders }}</h3>
            <p>أوامر معلقة</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> إجراءات المشتريات</h3>
        </div>
        <div class="card-body">
            <ul class="module-actions">
                <li><a href="{{ route('admin.purchases.suppliers') }}">الموردون</a></li>
                <li><a href="{{ route('admin.purchases.orders') }}">أوامر الشراء</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
