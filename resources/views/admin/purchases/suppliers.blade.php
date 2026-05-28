@extends('admin.layout')

@section('title', 'الموردون')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-tie"></i> الموردون</h1>
    <p>قائمة الموردين وتفاصيل الاتصال.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($suppliers->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الشركة</th>
                        <th>الهاتف</th>
                        <th>البريد الإلكتروني</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->company ?? '-' }}</td>
                            <td>{{ $supplier->phone ?? '-' }}</td>
                            <td>{{ $supplier->email ?? '-' }}</td>
                            <td>{{ ucfirst($supplier->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $suppliers->links() }}
        @else
            <p class="empty-state">لا يوجد موردين حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
