@extends('admin.layout')

@section('title', 'عرض تفاصيل إيصال الاستلام')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> إيصال استلام #{{ $receipt->receipt_number }}</h1>
    <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>المورد:</strong> {{ $receipt->supplier?->name ?? '-' }}</p>
                <p><strong>أمر الشراء:</strong> {{ $receipt->purchaseOrder?->order_number ?? '-' }}</p>
                <p><strong>تاريخ الاستلام:</strong> {{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>الإجمالي:</strong> ${{ number_format($receipt->total, 2) }}</p>
            </div>
        </div>
        
        @if($receipt->notes)
        <p><strong>ملاحظات:</strong> {{ $receipt->notes }}</p>
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
                @foreach($receipt->items as $item)
                <tr>
                    <td>{{ $item->product?->name_ar ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="alert alert-success" style="margin-top: 20px;">
            <i class="fas fa-check-circle"></i>
            <strong>تم تحديث المخزون:</strong>
            <ul>
                @foreach($receipt->items as $item)
                <li>{{ $item->product?->name_ar ?? '-' }}: +{{ $item->quantity }} وحدة</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
