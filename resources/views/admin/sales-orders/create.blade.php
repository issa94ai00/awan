@extends('admin.layout')

@section('title', 'إنشاء طلب بيع')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> إنشاء طلب بيع جديد</h1>
    <a href="{{ route('admin.sales-orders.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.sales-orders.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>العميل</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ الطلب</label>
                <input type="date" name="order_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label>تاريخ التسليم المتوقع</label>
                <input type="date" name="expected_delivery" class="form-control">
            </div>
            <div class="form-group">
                <label>عنوان التسليم</label>
                <input type="text" name="shipping_address" class="form-control">
            </div>
            <div class="form-group">
                <label>الخصم</label>
                <input type="number" name="discount" class="form-control" value="0" step="0.01">
            </div>
            <div class="form-group">
                <label>الضريبة</label>
                <input type="number" name="tax" class="form-control" value="0" step="0.01">
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
                        <option value="{{ $product->id }}">{{ $product->name_ar }} - ${{ $product->price }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[0][quantity]" class="form-control" placeholder="الكمية" required>
                    <input type="number" name="items[0][unit_price]" class="form-control" placeholder="السعر" step="0.01" required>
                    <button type="button" class="btn btn-danger remove-item">حذف</button>
                </div>
            </div>
            <button type="button" class="btn btn-secondary add-item">إضافة صنف</button>
            
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
                    <option value="{{ $product->id }}">{{ $product->name_ar }} - ${{ $product->price }}</option>
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
