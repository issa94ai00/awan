@extends('admin.layout')

@section('title', 'تعديل طلب البيع')

@push('styles')
<style>
    .item-row {
        display: grid;
        grid-template-columns: 2.5fr 1fr 1fr auto;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1rem;
        padding: 1.25rem;
        background: var(--bg-light);
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        transition: var(--transition);
    }
    .item-row:hover {
        border-color: var(--border-color-dark);
        box-shadow: var(--shadow-sm);
    }
    @media (max-width: 768px) {
        .item-row {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        .item-row .btn-danger {
            width: 100%;
            margin-top: 0.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-shopping-cart"></i> تعديل طلب بيع #{{ $salesOrder->order_number }}</h1>
        <p>تحديث تفاصيل الطلب وتعديل المنتجات المدرجة فيه ومراجعة الحسابات</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.sales-orders.show', $salesOrder) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.sales-orders.update', $salesOrder) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label style="font-weight: 600;">العميل <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ $salesOrder->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">تاريخ الطلب</label>
                    <input type="date" name="order_date" class="form-control" value="{{ $salesOrder->order_date?->format('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">تاريخ التسليم المتوقع</label>
                    <input type="date" name="expected_delivery" class="form-control" value="{{ $salesOrder->expected_delivery?->format('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">عنوان التسليم</label>
                    <input type="text" name="shipping_address" class="form-control" value="{{ $salesOrder->shipping_address }}" placeholder="أدخل عنوان الشحن والتسليم...">
                </div>
            </div>

            <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label style="font-weight: 600;">حالة الطلب <span class="text-danger">*</span></label>
                    <select name="status" class="form-control" required>
                        <option value="pending" {{ $salesOrder->status === 'pending' ? 'selected' : '' }}>معلق</option>
                        <option value="confirmed" {{ $salesOrder->status === 'confirmed' ? 'selected' : '' }}>مؤكد</option>
                        <option value="processing" {{ $salesOrder->status === 'processing' ? 'selected' : '' }}>قيد المعالجة</option>
                        <option value="shipped" {{ $salesOrder->status === 'shipped' ? 'selected' : '' }}>تم الشحن</option>
                        <option value="delivered" {{ $salesOrder->status === 'delivered' ? 'selected' : '' }}>تم التسليم</option>
                        <option value="cancelled" {{ $salesOrder->status === 'cancelled' ? 'selected' : '' }}>ملغي</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">الخصم</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="number" name="discount" class="form-control" value="{{ $salesOrder->discount }}" step="0.01" style="padding-left: 2rem;">
                        <span style="position: absolute; left: 12px; color: var(--text-light); font-weight: 600;">$</span>
                    </div>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">الضريبة</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="number" name="tax" class="form-control" value="{{ $salesOrder->tax }}" step="0.01" style="padding-left: 2rem;">
                        <span style="position: absolute; left: 12px; color: var(--text-light); font-weight: 600;">$</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label style="font-weight: 600;">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="أضف أي ملاحظات خاصة بالطلب هنا...">{{ $salesOrder->notes }}</textarea>
            </div>
            
            <div style="border-top: 1px solid var(--border-color); margin: 2rem 0; padding-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h3 style="margin: 0;"><i class="fas fa-boxes" style="color: var(--accent-blue);"></i> الأصناف والكميات</h3>
                    <button type="button" class="btn btn-secondary add-item" style="border: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">
                        <i class="fas fa-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                
                <div id="items-container" style="margin-bottom: 1.5rem;">
                    @foreach($salesOrder->items as $index => $item)
                    <div class="item-row">
                        <select name="items[{{ $index }}][product_id]" class="form-control" required>
                            <option value="">اختر الصنف</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ $item->product_id == $product->id ? 'selected' : '' }}>{{ $product->name_ar }} - ${{ $product->price }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="items[{{ $index }}][quantity]" class="form-control" placeholder="الكمية" value="{{ $item->quantity }}" required min="1">
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="number" name="items[{{ $index }}][unit_price]" class="form-control" placeholder="السعر" value="{{ $item->unit_price }}" step="0.01" required min="0" style="padding-left: 2rem;">
                            <span style="position: absolute; left: 12px; color: var(--text-light);">$</span>
                        </div>
                        <button type="button" class="btn btn-danger remove-item" style="padding: 0.625rem 1rem;"><i class="fas fa-trash"></i></button>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div class="form-actions" style="border-top: 1px solid var(--border-color); padding-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="{{ route('admin.sales-orders.show', $salesOrder) }}" class="btn btn-secondary" style="border: 1px solid var(--border-color);">إلغاء</a>
                <button type="submit" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;"><i class="fas fa-save"></i> حفظ التحديثات</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.querySelector('.add-item').addEventListener('click', function() {
        const container = document.getElementById('items-container');
        const index = container.children.length;
        const html = `
            <div class="item-row">
                <select name="items[${index}][product_id]" class="form-control" required>
                    <option value="">اختر الصنف</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name_ar }} - $${{ $product->price }}</option>
                    @endforeach
                </select>
                <input type="number" name="items[${index}][quantity]" class="form-control" placeholder="الكمية" required min="1">
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="number" name="items[${index}][unit_price]" class="form-control" placeholder="السعر" step="0.01" required min="0" style="padding-left: 2rem;">
                    <span style="position: absolute; left: 12px; color: var(--text-light);">$</span>
                </div>
                <button type="button" class="btn btn-danger remove-item" style="padding: 0.625rem 1rem;"><i class="fas fa-trash"></i></button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
            const btn = e.target.classList.contains('remove-item') ? e.target : e.target.closest('.remove-item');
            const container = document.getElementById('items-container');
            if (container.children.length > 1) {
                btn.closest('.item-row').remove();
            } else {
                alert('يجب أن يحتوي طلب البيع على صنف واحد على الأقل.');
            }
        }
    });
</script>
@endpush
@endsection
