@extends('admin.layout')

@section('title', 'عرض تفاصيل الدفعة')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-bill-wave"></i> دفعة #{{ $payment->payment_number }}</h1>
    <a href="{{ route('admin.payments.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>العميل:</strong> {{ $payment->customer?->name ?? '-' }}</p>
                <p><strong>الفاتورة:</strong> {{ $payment->invoice?->invoice_number ?? '-' }}</p>
                <p><strong>طريقة الدفع:</strong> {{ $payment->payment_method }}</p>
                <p><strong>تاريخ الدفع:</strong> {{ $payment->payment_date?->format('Y-m-d') ?? '-' }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>الحالة:</strong> {{ $payment->status }}</p>
                <p><strong>المبلغ:</strong> ${{ number_format($payment->amount, 2) }}</p>
                <p><strong>رقم المرجع:</strong> {{ $payment->reference ?? '-' }}</p>
            </div>
        </div>
        
        @if($payment->notes)
        <p><strong>ملاحظات:</strong> {{ $payment->notes }}</p>
        @endif
        
        <div class="alert alert-info" style="margin-top: 20px;">
            <strong>تأثير على الأرصدة:</strong>
            <ul>
                <li>تم تحديث رصيد العميل: ${{ number_format($payment->amount, 2) }}</li>
                <li>تم تحديث المبلغ المدفوع للفاتورة: ${{ number_format($payment->amount, 2) }}</li>
                <li>تم تحديث المبلغ المستحق للفاتورة: ${{ number_format($payment->invoice?->due_amount ?? 0, 2) }}</li>
            </ul>
        </div>
    </div>
</div>
@endsection
