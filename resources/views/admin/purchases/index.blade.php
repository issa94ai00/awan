@extends('admin.layout')

@section('title', 'لوحة تحكم المشتريات')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-truck-loading"></i> المشتريات</h1>
        <p>نظرة عامة على المشتريات، الموردين، ومتابعة أوامر الشراء والوصولات الواردة</p>
    </div>
</div>

<!-- Stats counter grid -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon stat-icon-teal">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $suppliersCount }}</h3>
            <p>الموردون</p>
            <small>الموردين المعتمدين بالنظام</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $ordersCount }}</h3>
            <p>أوامر الشراء</p>
            <small>إجمالي أوامر الشراء الصادرة</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $pendingOrders }}</h3>
            <p>أوامر معلقة</p>
            <small>أوامر شراء بانتظار التأكيد</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\PurchaseReceipt::count() }}</h3>
            <p>إيصالات الاستلام</p>
            <small>إيصالات استلام البضائع والمخزون</small>
        </div>
    </div>
</div>

<!-- Category grid list -->
<h3 style="margin-bottom: 1.25rem; font-weight: 700; color: var(--text-dark);"><i class="fas fa-th-large text-muted"></i> أقسام المشتريات الرئيسية</h3>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Suppliers card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3><i class="fas fa-users" style="color: var(--accent-blue);"></i> إدارة الموردين</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.7;">إدارة بيانات الموردين المعتمدين، بيانات الاتصال، وتتبع الحسابات والأرصدة المالية المستحقة لهم ومتابعة التوريد.</p>
            <a href="{{ route('admin.purchases.suppliers') }}" class="btn btn-primary" style="width: 100%; font-weight: 600;">
                <i class="fas fa-eye"></i> عرض الموردين
            </a>
        </div>
    </div>

    <!-- Purchase Orders card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3><i class="fas fa-file-alt" style="color: var(--warning-dark);"></i> أوامر الشراء</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.7;">إنشاء ومتابعة أوامر الشراء الصادرة للموردين، وتتبع حالة الموافقة والتأكيد عليها لتحويلها لإيصالات استلام بضائع.</p>
            <a href="{{ route('admin.purchases.orders') }}" class="btn btn-primary" style="width: 100%; font-weight: 600; background-color: var(--warning-dark); border-color: var(--warning-dark);">
                <i class="fas fa-eye"></i> عرض أوامر الشراء
            </a>
        </div>
    </div>

    <!-- Receipts card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header" style="padding: 1.25rem 1.5rem;">
            <h3><i class="fas fa-receipt" style="color: var(--success-dark);"></i> إيصالات استلام البضائع</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.7;">توثيق عمليات استلام البضائع والمخازن الواردة من الموردين وتحديث مستويات المخزون تلقائياً وإثبات الدفعات.</p>
            <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-primary" style="width: 100%; font-weight: 600; background-color: var(--success); border-color: var(--success);">
                <i class="fas fa-eye"></i> إدارة إيصالات الاستلام
            </a>
        </div>
    </div>
</div>
@endsection
