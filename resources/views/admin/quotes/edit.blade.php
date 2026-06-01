@extends('admin.layout')

@section('title', 'تعديل عرض السعر')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> تعديل عرض سعر #{{ $quote->quote_number }}</h1>
    <a href="{{ route('admin.quotes.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.quotes.update', $quote) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>العميل</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" {{ $quote->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>تاريخ الصلاحية</label>
                <input type="date" name="valid_until" class="form-control" value="{{ $quote->valid_until?->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label>الخصم</label>
                <input type="number" name="discount" class="form-control" value="{{ $quote->discount }}" step="0.01">
            </div>
            <div class="form-group">
                <label>الضريبة</label>
                <input type="number" name="tax" class="form-control" value="{{ $quote->tax }}" step="0.01">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3">{{ $quote->notes }}</textarea>
            </div>
            <div class="form-group">
                <label>الشروط</label>
                <textarea name="terms" class="form-control" rows="3">{{ $quote->terms }}</textarea>
            </div>
            
            <h3>الأصناف</h3>
            <div id="items-container">
                @foreach($quote->items as $index => $item)
                <div class="item-row">
                    <select name="items[{{ $index }}][product_id]" class="form-control" required>
                        <option value="">اختر الصنف</option>
                        @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ $item->product_id == $product->id ? 'selected' : '' }}>{{ $product->name_ar }} - ${{ $product->price }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[{{ $index }}][quantity]" class="form-control" placeholder="الكمية" value="{{ $item->quantity }}" required>
                    <input type="number" name="items[{{ $index }}][unit_price]" class="form-control" placeholder="السعر" value="{{ $item->unit_price }}" step="0.01" required>
                    <button type="button" class="btn btn-danger remove-item">حذف</button>
                </div>
                @endforeach
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
