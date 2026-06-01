@extends('admin.layout')

@section('title', 'تعديل أمر إنتاج')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-industry"></i> تعديل أمر إنتاج</h1>
    <p>تعديل بيانات أمر الإنتاج: {{ $productionOrder->order_number }}</p>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-edit"></i> تعديل بيانات أمر الإنتاج</h3>
        <a href="{{ route('admin.production.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> عودة
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.production.update', $productionOrder) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div class="form-group">
                    <label>رقم الأمر</label>
                    <input type="text" value="{{ $productionOrder->order_number }}" disabled>
                </div>

                <div class="form-group">
                    <label>المنتج <span class="required">*</span></label>
                    <select name="product_id" required>
                        <option value="">اختر المنتج</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ $productionOrder->product_id == $product->id ? 'selected' : '' }}>
                                {{ $product->name_ar }} - {{ $product->name_en }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>الكمية <span class="required">*</span></label>
                    <input type="number" name="quantity" value="{{ $productionOrder->quantity }}" min="1" required>
                    @error('quantity')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>الحالة <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="pending" {{ $productionOrder->status == 'pending' ? 'selected' : '' }}>معلق</option>
                        <option value="in_progress" {{ $productionOrder->status == 'in_progress' ? 'selected' : '' }}>قيد التنفيذ</option>
                        <option value="completed" {{ $productionOrder->status == 'completed' ? 'selected' : '' }}>مكتمل</option>
                        <option value="cancelled" {{ $productionOrder->status == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                    </select>
                    @error('status')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>تاريخ البدء</label>
                    <input type="date" name="start_date" value="{{ $productionOrder->start_date ? $productionOrder->start_date->format('Y-m-d') : '' }}">
                    @error('start_date')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" name="end_date" value="{{ $productionOrder->end_date ? $productionOrder->end_date->format('Y-m-d') : '' }}">
                    @error('end_date')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label>التكلفة</label>
                    <input type="number" name="cost" step="0.01" min="0" value="{{ $productionOrder->cost }}">
                    @error('cost')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full-width">
                    <label>ملاحظات</label>
                    <textarea name="notes" rows="4" maxlength="1000">{{ $productionOrder->notes }}</textarea>
                    @error('notes')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
                <a href="{{ route('admin.production.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
