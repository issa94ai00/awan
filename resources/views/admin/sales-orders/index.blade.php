@extends('admin.layout')

@section('title', 'طلبات البيع')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> طلبات البيع</h1>
    <a href="{{ route('admin.sales-orders.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> طلب بيع جديد
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesOrders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->customer?->name ?? '-' }}</td>
                    <td>${{ number_format($order->total, 2) }}</td>
                    <td>
                        <span class="badge badge-{{ $order->status === 'delivered' ? 'success' : ($order->status === 'pending' ? 'warning' : 'secondary') }}">
                            {{ $order->status }}
                        </span>
                    </td>
                    <td>{{ $order->created_at->format('Y-m-d') }}</td>
                    <td>
                        <a href="{{ route('admin.sales-orders.show', $order) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.sales-orders.edit', $order) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        @if($order->status === 'confirmed')
                        <form action="{{ route('admin.sales-orders.convert-to-invoice', $order) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-exchange-alt"></i> تحويل لفاتورة
                            </button>
                        </form>
                        @endif
                        <form action="{{ route('admin.sales-orders.destroy', $order) }}" method="POST" style="display: inline;">
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
                    <td colspan="6" class="text-center">لا توجد طلبات بيع</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
