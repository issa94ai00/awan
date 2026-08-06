@extends('admin.layout')

@section('title', 'عملاء CRM')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-users"></i> عملاء CRM</h1>
    <p>سجل العملاء وأرقام الاتصال.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($customers->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الهاتف</th>
                        <th>البريد الإلكتروني</th>
                        <th>الشركة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone ?? '-' }}</td>
                            <td>{{ $customer->email ?? '-' }}</td>
                            <td>{{ $customer->company ?? '-' }}</td>
                            <td>{{ ucfirst($customer->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $customers->links() }}
        @else
            <p class="empty-state">لا يوجد عملاء CRM حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
