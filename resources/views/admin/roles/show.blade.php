@extends('admin.layout')

@section('title', 'عرض تفاصيل الدور')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> دور: {{ $role->display_name ?? $role->name }}</h1>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>الاسم:</strong> {{ $role->name }}</p>
                <p><strong>الاسم المعروض:</strong> {{ $role->display_name ?? '-' }}</p>
                <p><strong>الوصف:</strong> {{ $role->description ?? '-' }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>الحالة:</strong> {{ $role->is_active ? 'نشط' : 'غير نشط' }}</p>
                <p><strong>عدد المستخدمين:</strong> {{ $role->users()->count() }}</p>
                <p><strong>عدد الصلاحيات:</strong> {{ $role->permissions()->count() }}</p>
            </div>
        </div>
        
        <h3>الصلاحيات</h3>
        <div class="permissions-list">
            @foreach($role->permissions->groupBy('module') as $module => $modulePermissions)
            <div class="permission-group">
                <h4>{{ $module }}</h4>
                <ul>
                    @foreach($modulePermissions as $permission)
                    <li>{{ $permission->display_name }}</li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
    </div>
</div>

@push('styles')
<style>
.permissions-list {
    margin-top: 20px;
}
.permission-group {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}
.permission-group h4 {
    margin-bottom: 10px;
    color: #667eea;
}
.permission-group ul {
    list-style: none;
    padding: 0;
}
.permission-group li {
    padding: 5px 0;
    border-bottom: 1px solid #e9ecef;
}
</style>
@endpush
