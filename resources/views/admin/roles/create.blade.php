@extends('admin.layout')

@section('title', 'إنشاء دور')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> إنشاء دور جديد</h1>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>اسم الدور</label>
                <input type="text" name="name" class="form-control" required>
                <small class="form-text">مثال: manager, editor, viewer</small>
            </div>
            <div class="form-group">
                <label>الاسم المعروض</label>
                <input type="text" name="display_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="d-block">
                    <input type="checkbox" name="is_active" value="1" checked class="form-check-input">
                    نشط
                </label>
            </div>
            
            <h3>الصلاحيات</h3>
            <div class="permissions-list">
                @foreach($permissionsByModule as $module => $modulePermissions)
                <div class="permission-group">
                    <h4>{{ $module }}</h4>
                    @foreach($modulePermissions as $permission)
                    <div class="form-check">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="form-check-input" id="permission-{{ $permission->id }}">
                        <label class="form-check-label" for="permission-{{ $permission->id }}">
                            {{ $permission->display_name }}
                        </label>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
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
.form-check {
    margin-bottom: 8px;
}
</style>
@endpush
