@extends('admin.layout')

@section('title', 'عرض تفاصيل طلب البيع')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> طلب بيع #{{ $salesOrder->order_number }}</h1>
    <a href="{{ route('admin.sales-orders.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>العميل:</strong> {{ $salesOrder->customer?->name ?? '-' }}</p>
                <p><strong>تاريخ الطلب:</strong> {{ $salesOrder->order_date?->format('Y-m-d') }}</p>
                <p><strong>تاريخ التسليم المتوقع:</strong> {{ $salesOrder->expected_delivery?->format('Y-m-d') ?? '-' }}</p>
                @if($salesOrder->quote)
                <p><strong>عرض السعر المرجعي:</strong> {{ $salesOrder->quote->quote_number }}</p>
                @endif
            </div>
            <div class="col-md-6">
                <p><strong>الحالة:</strong> {{ $salesOrder->status }}</p>
                <p><strong>المجموع الفرعي:</strong> ${{ number_format($salesOrder->subtotal, 2) }}</p>
                <p><strong>الخصم:</strong> ${{ number_format($salesOrder->discount, 2) }}</p>
                <p><strong>الضريبة:</strong> ${{ number_format($salesOrder->tax, 2) }}</p>
                <p><strong>الإجمالي:</strong> ${{ number_format($salesOrder->total, 2) }}</p>
            </div>
        </div>
        
        @if($salesOrder->shipping_address)
        <p><strong>عنوان التسليم:</strong> {{ $salesOrder->shipping_address }}</p>
        @endif
        
        <h3>الأصناف</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الكمية</th>
                    <th>السعر</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesOrder->items as $item)
                <tr>
                    <td>{{ $item->product?->name_ar ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        @if($salesOrder->notes)
        <p><strong>ملاحظات:</strong> {{ $salesOrder->notes }}</p>
        @endif
        
        @if($salesOrder->status === 'confirmed')
        <form action="{{ route('admin.sales-orders.convert-to-invoice', $salesOrder) }}" method="POST" style="margin-top: 20px;">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="fas fa-exchange-alt"></i> تحويل إلى فاتورة
            </button>
        </form>
        @endif
    </div>
</div>
@endsection
