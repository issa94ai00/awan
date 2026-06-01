@extends('admin.layout')

@section('title', 'إنشاء إيصال استلام')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> إنشاء إيصال استلام جديد</h1>
    <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.purchase-receipts.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>المورد</label>
                <select name="supplier_id" class="form-control" required>
                    <option value="">اختر المورد</option>
                    @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>أمر الشراء</label>
                <select name="purchase_order_id" class="form-control">
                    <option value="">اختر أمر الشراء (اختياري)</option>
                    @foreach($purchaseOrders as $order)
                    <option value="{{ $order->id }}">{{ $order->order_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ الاستلام</label>
                <input type="date" name="receipt_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            
            <h3>الأصناف</h3>
            <div id="items-container">
                <div class="item-row">
                    <select name="items[0][product_id]" class="form-control" required>
                        <option value="">اختر الصنف</option>
                        @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name_ar }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[0][quantity]" class="form-control" placeholder="الكمية" required>
                    <input type="number" name="items[0][unit_price]" class="form-control" placeholder="السعر" step="0.01" required>
                    <button type="button" class="btn btn-danger remove-item">حذف</button>
                </div>
            </div>
            <button type="button" class="btn btn-secondary add-item">إضافة صنف</button>
            
            <div class="alert alert-info" style="margin-top: 20px;">
                <i class="fas fa-info-circle"></i>
                <strong>ملاحظة:</strong> سيتم تحديث كميات المخزون تلقائياً عند حفظ الإيصال.
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
