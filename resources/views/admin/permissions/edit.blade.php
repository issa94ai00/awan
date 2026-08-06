@extends('admin.layout')

@section('title', 'تعديل الصلاحية')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-key"></i> تعديل الصلاحية: {{ $permission->display_name ?? $permission->name }}</h1>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.permissions.update', $permission) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>اسم الصلاحية</label>
                <input type="text" name="name" class="form-control" value="{{ $permission->name }}" required>
            </div>
            <div class="form-group">
                <label>الاسم المعروض</label>
                <input type="text" name="display_name" class="form-control" value="{{ $permission->display_name }}" required>
            </div>
            <div class="form-group">
                <label>الوحدة</label>
                <select name="module" class="form-control" required>
                    <option value="">اختر الوحدة</option>
                    <option value="dashboard" {{ $permission->module === 'dashboard' ? 'selected' : '' }}>لوحة التحكم</option>
                    <option value="categories" {{ $permission->module === 'categories' ? 'selected' : '' }}>الفئات</option>
                    <option value="products" {{ $permission->module === 'products' ? 'selected' : '' }}>المنتجات</option>
                    <option value="inquiries" {{ $permission->module === 'inquiries' ? 'selected' : '' }}>الاستفسارات</option>
                    <option value="settings" {{ $permission->module === 'settings' ? 'selected' : '' }}>الإعدادات</option>
                    <option value="users" {{ $permission->module === 'users' ? 'selected' : '' }}>المستخدمين</option>
                    <option value="roles" {{ $permission->module === 'roles' ? 'selected' : '' }}>الأدوار</option>
                    <option value="permissions" {{ $permission->module === 'permissions' ? 'selected' : '' }}>الصلاحيات</option>
                    <option value="reports" {{ $permission->module === 'reports' ? 'selected' : '' }}>التقارير</option>
                    <option value="sales" {{ $permission->module === 'sales' ? 'selected' : '' }}>المبيعات</option>
                    <option value="inventory" {{ $permission->module === 'inventory' ? 'selected' : '' }}>المخزون</option>
                    <option value="purchases" {{ $permission->module === 'purchases' ? 'selected' : '' }}>المشتريات</option>
                    <option value="hr" {{ $permission->module === 'hr' ? 'selected' : '' }}>الموارد البشرية</option>
                </select>
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" class="form-control" rows="3">{{ $permission->description }}</textarea>
            </div>
            <div class="form-group">
                <label class="d-block">
                    <input type="checkbox" name="is_active" value="1" {{ $permission->is_active ? 'checked' : '' }} class="form-check-input">
                    نشط
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
    </div>
</div>
