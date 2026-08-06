@extends('admin.layout')

@section('title', 'الصلاحيات')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-key"></i> الصلاحيات</h1>
    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> صلاحية جديدة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الاسم المعروض</th>
                    <th>الوحدة</th>
                    <th>الوصف</th>
                    <th>الحالة</th>
                    <th>عدد الأدوار</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                <tr>
                    <td>{{ $permission->name }}</td>
                    <td>{{ $permission->display_name ?? '-' }}</td>
                    <td>{{ $permission->module ?? '-' }}</td>
                    <td>{{ $permission->description ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $permission->is_active ? 'success' : 'secondary' }}">
                            {{ $permission->is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                    </td>
                    <td>{{ $permission->roles()->count() }}</td>
                    <td>
                        <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">لا توجد صلاحيات</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
