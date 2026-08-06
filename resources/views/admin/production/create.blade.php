@extends('admin.layout')

@section('title', 'إنشاء أمر إنتاج')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-industry"></i> إنشاء أمر إنتاج</h1>
    <p>إنشاء أمر إنتاج جديد لتصدير منتج نهائي.</p>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle"></i> بيانات أمر الإنتاج</h3>
        <a href="{{ route('admin.production.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> عودة
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.production.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>المنتج <span class="required">*</span></label>
                    <select name="product_id" required>
                        <option value="">اختر المنتج</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name_ar }} - {{ $product->name_en }}</option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>الكمية <span class="required">*</span></label>
                    <input type="number" name="quantity" value="1" min="1" required>
                    @error('quantity')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>تاريخ البدء</label>
                    <input type="date" name="start_date">
                    @error('start_date')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" name="end_date">
                    @error('end_date')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>التكلفة</label>
                    <input type="number" name="cost" step="0.01" min="0" value="0">
                    @error('cost')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full-width">
                    <label>ملاحظات</label>
                    <textarea name="notes" rows="4" maxlength="1000"></textarea>
                    @error('notes')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ أمر الإنتاج
                </button>
                <a href="{{ route('admin.production.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
