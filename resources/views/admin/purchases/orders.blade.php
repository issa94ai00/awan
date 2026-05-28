@extends('admin.layout')

@section('title', 'أوامر الشراء')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-signature"></i> أوامر الشراء</h1>
    <p>إدارة طلبات المشتريات من الموردين.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($orders->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المورد</th>
                        <th>الحالة</th>
                        <th>المجموع</th>
                        <th>تاريخ الإنشاء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->supplier?->name ?? 'غير محدد' }}</td>
                            <td>{{ ucfirst($order->status) }}</td>
                            <td>${{ number_format($order->total, 2) }}</td>
                            <td>{{ $order->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $orders->links() }}
        @else
            <p class="empty-state">لا توجد أوامر شراء حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
