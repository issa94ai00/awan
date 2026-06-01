@extends('admin.layout')

@section('title', 'عرض تفاصيل عرض السعر')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> عرض سعر #{{ $quote->quote_number }}</h1>
    <a href="{{ route('admin.quotes.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>العميل:</strong> {{ $quote->customer?->name ?? '-' }}</p>
                <p><strong>تاريخ الإنشاء:</strong> {{ $quote->created_at->format('Y-m-d') }}</p>
                <p><strong>تاريخ الصلاحية:</strong> {{ $quote->valid_until?->format('Y-m-d') ?? '-' }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>الحالة:</strong> {{ $quote->status }}</p>
                <p><strong>المجموع الفرعي:</strong> ${{ number_format($quote->subtotal, 2) }}</p>
                <p><strong>الخصم:</strong> ${{ number_format($quote->discount, 2) }}</p>
                <p><strong>الضريبة:</strong> ${{ number_format($quote->tax, 2) }}</p>
                <p><strong>الإجمالي:</strong> ${{ number_format($quote->total, 2) }}</p>
            </div>
        </div>
        
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
                @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->product?->name_ar ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        @if($quote->notes)
        <p><strong>ملاحظات:</strong> {{ $quote->notes }}</p>
        @endif
        
        @if($quote->terms)
        <p><strong>الشروط:</strong> {{ $quote->terms }}</p>
        @endif
        
        @if($quote->status === 'accepted')
        <form action="{{ route('admin.quotes.convert-to-sales-order', $quote) }}" method="POST" style="margin-top: 20px;">
            @csrf
            <button type="submit" class="btn btn-success">
                <i class="fas fa-exchange-alt"></i> تحويل إلى طلب بيع
            </button>
        </form>
        @endif
    </div>
</div>
@endsection
