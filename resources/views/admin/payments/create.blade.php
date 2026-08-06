@extends('admin.layout')

@section('title', 'إنشاء دفعة')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-bill-wave"></i> إنشاء دفعة جديدة</h1>
    <a href="{{ route('admin.payments.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.payments.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>العميل</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }} (الرصيد: ${{ number_format($customer->balance, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>الفاتورة</label>
                <select name="invoice_id" class="form-control" required>
                    <option value="">اختر الفاتورة</option>
                    @foreach($invoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }} - ${{ number_format($invoice->due_amount, 2) }} مستحق</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>طريقة الدفع</label>
                <select name="payment_method" class="form-control" required>
                    <option value="cash">نقدي</option>
                    <option value="card">بطاقة</option>
                    <option value="bank_transfer">تحويل بنكي</option>
                    <option value="check">شيك</option>
                </select>
            </div>
            <div class="form-group">
                <label>المبلغ</label>
                <input type="number" name="amount" class="form-control" required step="0.01">
            </div>
            <div class="form-group">
                <label>تاريخ الدفع</label>
                <input type="date" name="payment_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label>رقم المرجع</label>
                <input type="text" name="reference" class="form-control">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
    </div>
</div>
@endsection
