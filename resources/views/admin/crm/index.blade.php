@extends('admin.layout')

@section('title', 'إدارة العملاء')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-friends"></i> إدارة العملاء</h1>
    <p>مركز التعامل مع العملاء والتذاكر والدعم.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $customersCount }}</h3>
            <p>عدد العملاء</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $ticketsCount }}</h3>
            <p>التذاكر</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-comments"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $openTickets }}</h3>
            <p>التذاكر المفتوحة</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> إجراءات CRM</h3>
        </div>
        <div class="card-body">
            <ul class="module-actions">
                <li><a href="{{ route('admin.crm.customers') }}">العملاء</a></li>
                <li><a href="{{ route('admin.crm.tickets') }}">التذاكر</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
