@extends('admin.layout')

@section('title', 'إنشاء دور')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> إنشاء دور جديد</h1>
    <div class="header-actions">
        <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> عودة
        </a>
    </div>
</div>

<div class="card interactive-card">
    <div class="card-body">
        <form action="{{ route('admin.roles.store') }}" method="POST" id="roleForm">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>اسم الدور <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required>
                    <small class="form-text">مثال: manager, editor, viewer</small>
                </div>
                <div class="form-group">
                    <label>الاسم المعروض <span class="required">*</span></label>
                    <input type="text" name="display_name" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" checked class="form-check-input">
                    <span class="form-check-label">نشط</span>
                </label>
            </div>
            
            <h3 style="margin: 2rem 0 1rem 0; color: var(--text-dark);">الصلاحيات</h3>
            <div class="permissions-list">
                @foreach($permissionsByModule as $module => $modulePermissions)
                <div class="permission-group" data-module="{{ $module }}">
                    <h4>
                        {{ $module }}
                        <span class="select-all" onclick="toggleModulePermissions(this)">تحديد الكل</span>
                    </h4>
                    <div class="permission-grid">
                        @foreach($modulePermissions as $permission)
                        <label class="form-check">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="form-check-input permission-checkbox" id="permission-{{ $permission->id }}">
                            <span class="form-check-label">{{ $permission->display_name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ الدور
                </button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleModulePermissions(element) {
    const group = element.closest('.permission-group');
    const checkboxes = group.querySelectorAll('.permission-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    element.textContent = allChecked ? 'تحديد الكل' : 'إلغاء التحديد';
}

// Add form validation feedback
document.getElementById('roleForm').addEventListener('submit', function(e) {
    const checkedPermissions = document.querySelectorAll('.permission-checkbox:checked');
    if (checkedPermissions.length === 0) {
        e.preventDefault();
        alert('يرجى تحديد صلاحية واحدة على الأقل');
    }
});
</script>
@endpush
