@extends('admin.layout')

@section('title', 'المحاسبة')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-calculator"></i> المحاسبة</h1>
    <p>نظرة عامة على الحسابات اليومية والقيود المالية.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $accountsCount }}</h3>
            <p>حسابات دفتر الأستاذ</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $entriesCount }}</h3>
            <p>قيود اليومية</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-balance-scale"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format($balanceSum, 2) }}</h3>
            <p>الرصيد الكلي</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> إجراءات المحاسبة السريعة</h3>
        </div>
        <div class="card-body">
            <ul class="module-actions">
                <li><a href="{{ route('admin.accounting.journal') }}">قيود اليومية</a></li>
                <li><a href="{{ route('admin.accounting.ledger') }}">دفتر الأستاذ</a></li>
                <li><a href="{{ route('admin.accounting.trial-balance') }}">ميزان المراجعة</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
