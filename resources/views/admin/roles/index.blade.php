@extends('admin.layout')

@section('title', 'الأدوار')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> الأدوار</h1>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> دور جديد
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الاسم المعروض</th>
                    <th>الوصف</th>
                    <th>الحالة</th>
                    <th>عدد المستخدمين</th>
                    <th>عدد الصلاحيات</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->display_name ?? '-' }}</td>
                    <td>{{ $role->description ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $role->is_active ? 'success' : 'secondary' }}">
                            {{ $role->is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                    </td>
                    <td>{{ $role->users()->count() }}</td>
                    <td>{{ $role->permissions()->count() }}</td>
                    <td>
                        <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        @if($role->name !== 'admin')
                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">لا توجد أدوار</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
