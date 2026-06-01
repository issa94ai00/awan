@extends('admin.layout')

@section('title', 'تعديل إيصال الاستلام')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> تعديل إيصال استلام #{{ $receipt->receipt_number }}</h1>
    <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.purchase-receipts.update', $receipt) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>المورد</label>
                <select name="supplier_id" class="form-control" required>
                    <option value="">اختر المورد</option>
                    @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ $receipt->supplier_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>أمر الشراء</label>
                <select name="purchase_order_id" class="form-control">
                    <option value="">اختر أمر الشراء (اختياري)</option>
                    @foreach($purchaseOrders as $order)
                    <option value="{{ $order->id }}" {{ $receipt->purchase_order_id == $order->id ? 'selected' : '' }}>{{ $order->order_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ الاستلام</label>
                <input type="date" name="receipt_date" class="form-control" value="{{ $receipt->receipt_date?->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3">{{ $receipt->notes }}</textarea>
            </div>
            
            <h3>الأصناف</h3>
            <div id="items-container">
                @foreach($receipt->items as $index => $item)
                <div class="item-row">
                    <select name="items[{{ $index }}][product_id]" class="form-control" required>
                        <option value="">اختر الصنف</option>
                        @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ $item->product_id == $product->id ? 'selected' : '' }}>{{ $product->name_ar }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[{{ $index }}][quantity]" class="form-control" placeholder="الكمية" value="{{ $item->quantity }}" required>
                    <input type="number" name="items[{{ $index }}][unit_price]" class="form-control" placeholder="السعر" value="{{ $item->unit_price }}" step="0.01" required>
                    <button type="button" class="btn btn-danger remove-item">حذف</button>
                </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-secondary add-item">إضافة صنف</button>
            
            <div class="alert alert-warning" style="margin-top: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>تنبيه:</strong> سيتم تحديث كميات المخزون تلقائياً عند حفظ التعديلات.
            </div>
            
            <button type="submit" class="btn btn-primary">حفظ</button>
        </form>
    </div>
</div>

@section('scripts')
<script>
    document.querySelector('.add-item').addEventListener('click', function() {
        const container = document.getElementById('items-container');
        const index = container.children.length;
        const html = `
            <div class="item-row">
                <select name="items[${index}][product_id]" class="form-control" required>
                    <option value="">اختر الصنف</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name_ar }}</option>
                    @endforeach
                </select>
                <input type="number" name="items[${index}][quantity]" class="form-control" placeholder="الكمية" required>
                <input type="number" name="items[${index}][unit_price]" class="form-control" placeholder="السعر" step="0.01" required>
                <button type="button" class="btn btn-danger remove-item">حذف</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item')) {
            e.target.closest('.item-row').remove();
        }
    });
</script>
@endsection
