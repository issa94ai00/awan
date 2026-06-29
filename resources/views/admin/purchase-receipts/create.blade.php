@extends('admin.layout')

@section('title', 'إنشاء إيصال استلام')

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
        <h1><i class="fas fa-truck-loading"></i> إنشاء إيصال استلام جديد</h1>
        <p>تسجيل البضائع والمنتجات الواردة من الموردين وتوثيق إدخالها إلى المخازن</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.purchase-receipts.store') }}" method="POST">
            @csrf
            
            <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label style="font-weight: 600;">المورد <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-control" required>
                        <option value="">اختر المورد</option>
                        @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">مرجع أمر الشراء</label>
                    <select name="purchase_order_id" class="form-control">
                        <option value="">اختر أمر الشراء (شراء مباشر إذا كان خالياً)</option>
                        @foreach($purchaseOrders as $order)
                        <option value="{{ $order->id }}" {{ request('purchase_order_id') == $order->id ? 'selected' : '' }}>
                            {{ $order->order_number }} - {{ $order->supplier?->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600;">تاريخ الاستلام الفعلي</label>
                    <input type="date" name="receipt_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-weight: 600;">ملاحظات الاستلام</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="أضف أي ملاحظات خاصة بعملية الاستلام أو حالة الشحنة..."></textarea>
            </div>
            
            <div style="border-top: 1px solid var(--border-color); margin: 2rem 0; padding-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h3 style="margin: 0;"><i class="fas fa-boxes" style="color: var(--accent-blue);"></i> الأصناف والكميات الواردة</h3>
                    <button type="button" class="btn btn-secondary add-item" style="border: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.25rem; font-weight: 600;">
                        <i class="fas fa-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                
                <div id="items-container" style="margin-bottom: 1.5rem;">
                    <div class="item-row">
                        <select name="items[0][product_id]" class="form-control" required>
                            <option value="">اختر الصنف</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name_ar }} (SKU: {{ $product->sku }})</option>
                            @endforeach
                        </select>
                        <input type="number" name="items[0][quantity]" class="form-control" placeholder="الكمية" required min="1">
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="number" name="items[0][unit_price]" class="form-control" placeholder="السعر" step="0.01" required min="0" style="padding-left: 2rem;">
                            <span style="position: absolute; left: 12px; color: var(--text-light);">$</span>
                        </div>
                        <button type="button" class="btn btn-danger remove-item" style="padding: 0.625rem 1rem;"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
            
            <!-- Alert Info -->
            <div class="alert alert-info" style="border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: center; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a;">
                <i class="fas fa-info-circle" style="font-size: 1.2rem; color: var(--accent-blue);"></i>
                <span style="font-size: 0.9rem;">سيتم تحديث مستويات المخزون وإدخال الكميات المذكورة أعلاه فوراً عند حفظ إيصال الاستلام.</span>
            </div>

            <div class="form-actions" style="border-top: 1px solid var(--border-color); padding-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary" style="border: 1px solid var(--border-color);">إلغاء</a>
                <button type="submit" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;"><i class="fas fa-save"></i> حفظ وإدخال للمخزن</button>
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
                    <option value="{{ $product->id }}">{{ $product->name_ar }} (SKU: {{ $product->sku }})</option>
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
                alert('يجب أن يحتوي إيصال الاستلام على صنف واحد على الأقل.');
            }
        }
    });
</script>
@endpush
@endsection
