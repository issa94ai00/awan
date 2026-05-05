@extends('admin.layout')

@section('title', isset($category) ? 'تعديل الفئة' : 'فئة جديدة')

@section('content')

@if ($errors->any())
<div class="alert alert-danger">
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-folder-open"></i> {{ isset($category) ? 'تعديل الفئة' : 'فئة جديدة' }}</h1>
        <p>{{ isset($category) ? 'تحديث بيانات الفئة' : 'إضافة فئة جديدة للمنتجات' }}</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة للقائمة
        </a>
    </div>
</div>

<div class="form-card">
    <form action="{{ isset($category) ? route('admin.categories.update', $category->slug) : route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if(isset($category))
            @method('PUT')
        @endif

        <div class="form-grid">
            <div class="form-group">
                <label for="name_ar">اسم الفئة (عربي) <span class="required">*</span></label>
                <input type="text" id="name_ar" name="name_ar" value="{{ old('name_ar', $category->name_ar ?? '') }}" class="form-control @error('name_ar') is-invalid @enderror" required>
                @error('name_ar')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="name_en">اسم الفئة (إنجليزي) <span class="required">*</span></label>
                <input type="text" id="name_en" name="name_en" value="{{ old('name_en', $category->name_en ?? '') }}" class="form-control @error('name_en') is-invalid @enderror" required>
                @error('name_en')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label for="slug">الرابط المختصر (Slug)</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug', $category->slug ?? '') }}" class="form-control @error('slug') is-invalid @enderror" placeholder="يتم توليده تلقائياً من الاسم الإنجليزي">
            <small class="form-text">اتركه فارغاً للتوليد التلقائي</small>
            @error('slug')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="icon">الأيقونة</label>
                <div class="icon-picker">
                    <input type="text" id="icon" name="icon" value="{{ old('icon', $category->icon ?? 'fa-folder') }}" class="form-control" placeholder="fas fa-folder">
                    <div class="icon-preview">
                        <i class="fas {{ old('icon', $category->icon ?? 'fa-folder') }}"></i>
                    </div>
                </div>
                <small class="form-text">استخدم أيقونات Font Awesome (مثال: fas fa-folder)</small>
            </div>

            <div class="form-group col-md-6">
                <label for="sort_order">الترتيب</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="form-control" min="0">
            </div>
        </div>

        <div class="form-group">
            <label for="description">الوصف</label>
            <textarea id="description" name="description" rows="4" class="form-control">{{ old('description', $category->description ?? '') }}</textarea>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="image">صورة الفئة</label>
                <input type="file" id="image" name="image" class="form-control-file" accept="image/*">
                @if(isset($category) && $category->image)
                    <div class="image-preview mt-2">
                        <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name_ar }}" style="max-width: 150px; border-radius: 8px;">
                    </div>
                @endif
            </div>

            <div class="form-group col-md-6">
                <label class="d-block">الحالة</label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }} class="custom-control-input">
                    <label class="custom-control-label" for="is_active">نشط</label>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ isset($category) ? 'تحديث' : 'حفظ' }}
            </button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
// Auto-generate slug from English name
document.getElementById('name_en')?.addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value) {
        slugInput.value = this.value.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// Icon preview
document.getElementById('icon')?.addEventListener('input', function() {
    const preview = document.querySelector('.icon-preview i');
    if (preview) {
        preview.className = this.value;
    }
});
</script>
@endpush
