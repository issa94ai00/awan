@extends('admin.layout')

@section('title', 'إنشاء صلاحية')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-key"></i> إنشاء صلاحية جديدة</h1>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.permissions.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>اسم الصلاحية</label>
                <input type="text" name="name" class="form-control" required>
                <small class="form-text">مثال: products.create, users.edit</small>
            </div>
            <div class="form-group">
                <label>الاسم المعروض</label>
                <input type="text" name="display_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>الوحدة</label>
                <select name="module" class="form-control" required>
                    <option value="">اختر الوحدة</option>
                    <option value="dashboard">لوحة التحكم</option>
                    <option value="categories">الفئات</option>
                    <option value="products">المنتجات</option>
                    <option value="inquiries">الاستفسارات</option>
                    <option value="settings">الإعدادات</option>
                    <option value="users">المستخدمين</option>
                    <option value="roles">الأدوار</option>
                    <option value="permissions">الصلاحيات</option>
                    <option value="reports">التقارير</option>
                    <option value="sales">المبيعات</option>
                    <option value="inventory">المخزون</option>
                    <option value="purchases">المشتريات</option>
                    <option value="hr">الموارد البشرية</option>
                </select>
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
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
    </div>
</div>
