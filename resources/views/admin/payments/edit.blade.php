@extends('admin.layout')

@section('title', 'تعديل الدفعة')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-bill-wave"></i> تعديل دفعة #{{ $payment->payment_number }}</h1>
    <a href="{{ route('admin.payments.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.payments.update', $payment) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>العميل</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" {{ $payment->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>الفاتورة</label>
                <select name="invoice_id" class="form-control" required>
                    <option value="">اختر الفاتورة</option>
                    @foreach($invoices as $invoice)
                    <option value="{{ $invoice->id }}" {{ $payment->invoice_id == $invoice->id ? 'selected' : '' }}>{{ $invoice->invoice_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>طريقة الدفع</label>
                <select name="payment_method" class="form-control" required>
                    <option value="cash" {{ $payment->payment_method === 'cash' ? 'selected' : '' }}>نقدي</option>
                    <option value="card" {{ $payment->payment_method === 'card' ? 'selected' : '' }}>بطاقة</option>
                    <option value="bank_transfer" {{ $payment->payment_method === 'bank_transfer' ? 'selected' : '' }}>تحويل بنكي</option>
                    <option value="check" {{ $payment->payment_method === 'check' ? 'selected' : '' }}>شيك</option>
                </select>
            </div>
            <div class="form-group">
                <label>المبلغ</label>
                <input type="number" name="amount" class="form-control" value="{{ $payment->amount }}" required step="0.01">
            </div>
            <div class="form-group">
                <label>تاريخ الدفع</label>
                <input type="date" name="payment_date" class="form-control" value="{{ $payment->payment_date?->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label>رقم المرجع</label>
                <input type="text" name="reference" class="form-control" value="{{ $payment->reference }}">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3">{{ $payment->notes }}</textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
    </div>
</div>
@endsection
