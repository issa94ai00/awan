@extends('admin.layout')

@section('title', 'الموارد البشرية')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-users"></i> الموارد البشرية</h1>
    <p>إدارة الموظفين والحضور والإجازات.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $employeesCount }}</h3>
            <p>الموظفون</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $leaveRequestsCount }}</h3>
            <p>طلبات إجازة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $attendanceTodayCount }}</h3>
            <p>الحضور اليوم</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> إجراءات الموارد البشرية</h3>
        </div>
        <div class="card-body">
            <ul class="module-actions">
                <li><a href="{{ route('admin.hr.employees') }}">الموظفون</a></li>
                <li><a href="{{ route('admin.hr.attendance') }}">الحضور</a></li>
                <li><a href="{{ route('admin.hr.leaves') }}">الإجازات</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
