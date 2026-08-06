@extends('admin.layout')

@section('title', 'الموظفون')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-tie"></i> الموظفون</h1>
    <p>قائمة الموظفين المسجلين في النظام.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($employees->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الوظيفة</th>
                        <th>القسم</th>
                        <th>الهاتف</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $employee)
                        <tr>
                            <td>{{ $employee->first_name }} {{ $employee->last_name }}</td>
                            <td>{{ $employee->position ?? '-' }}</td>
                            <td>{{ $employee->department ?? '-' }}</td>
                            <td>{{ $employee->phone ?? '-' }}</td>
                            <td>{{ ucfirst($employee->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $employees->links() }}
        @else
            <p class="empty-state">لا توجد موظفين حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
