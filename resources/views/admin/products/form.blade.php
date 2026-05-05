@extends('admin.layout')

@section('title', isset($product) ? 'تعديل المنتج' : 'منتج جديد')

@section('content')
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-box"></i> {{ isset($product) ? 'تعديل المنتج' : 'منتج جديد' }}</h1>
        <p>{{ isset($product) ? 'تحديث بيانات المنتج' : 'إضافة منتج جديد للموقع' }}</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة للقائمة
        </a>
    </div>
</div>

<div class="form-card">
    <form action="{{ isset($product) ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if(isset($product))
            @method('PUT')
        @endif

        <div class="form-tabs">
            <button type="button" class="tab-btn active" data-tab="basic">البيانات الأساسية</button>
            <button type="button" class="tab-btn" data-tab="details">التفاصيل</button>
            <button type="button" class="tab-btn" data-tab="images">الصور</button>
            <button type="button" class="tab-btn" data-tab="seo">SEO</button>
        </div>

        <div class="tab-content active" id="basic">
            <div class="form-grid">
                <div class="form-group">
                    <label for="category_id">الفئة <span class="required">*</span></label>
                    <select id="category_id" name="category_id" class="form-control @error('category_id') is-invalid @enderror" required>
                        <option value="">اختر الفئة</option>
                        @foreach($categories ?? [] as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $product->category_id ?? '') == $category->id ? 'selected' : '' }}>
                                {{ $category->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="name_ar">اسم المنتج (عربي) <span class="required">*</span></label>
                    <input type="text" id="name_ar" name="name_ar" value="{{ old('name_ar', $product->name_ar ?? '') }}" class="form-control @error('name_ar') is-invalid @enderror" required>
                    @error('name_ar')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="name_en">اسم المنتج (إنجليزي) <span class="required">*</span></label>
                <input type="text" id="name_en" name="name_en" value="{{ old('name_en', $product->name_en ?? '') }}" class="form-control @error('name_en') is-invalid @enderror" required>
            </div>

            <div class="form-group">
                <label for="slug">الرابط المختصر (Slug)</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug', $product->slug ?? '') }}" class="form-control" placeholder="يتم توليده تلقائياً">
                <small class="form-text">اتركه فارغاً للتوليد التلقائي من الاسم الإنجليزي</small>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="price">السعر ($)</label>
                    <input type="number" id="price" name="price" value="{{ old('price', $product->price ?? '') }}" class="form-control" step="0.01" min="0">
                </div>

                <div class="form-group col-md-4">
                    <label for="brand">العلامة التجارية</label>
                    <input type="text" id="brand" name="brand" value="{{ old('brand', $product->brand ?? '') }}" class="form-control">
                </div>

                <div class="form-group col-md-4">
                    <label for="model">الموديل</label>
                    <input type="text" id="model" name="model" value="{{ old('model', $product->model ?? '') }}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="d-block">
                    <input type="checkbox" name="show_price" value="1" {{ old('show_price', $product->show_price ?? true) ? 'checked' : '' }} class="form-check-input">
                    إظهار السعر للعملاء
                </label>
                <small class="form-text">عند إلغاء التحديد، لن يظهر سعر المنتج للعملاء</small>
            </div>
        </div>

        <div class="tab-content" id="details">
            <div class="form-group">
                <label for="description_ar">الوصف (عربي)</label>
                <textarea id="description_ar" name="description_ar" rows="5" class="form-control">{{ old('description_ar', $product->description_ar ?? '') }}</textarea>
            </div>

            <div class="form-group">
                <label for="description_en">الوصف (إنجليزي)</label>
                <textarea id="description_en" name="description_en" rows="5" class="form-control">{{ old('description_en', $product->description_en ?? '') }}</textarea>
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label class="d-block">الحالة</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }} class="custom-control-input">
                        <label class="custom-control-label" for="is_active">نشط</label>
                    </div>
                </div>

                <div class="form-group col-md-4">
                    <label class="d-block">المخزون</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" id="in_stock" name="in_stock" value="1" {{ old('in_stock', $product->in_stock ?? true) ? 'checked' : '' }} class="custom-control-input">
                        <label class="custom-control-label" for="in_stock">متوفر</label>
                    </div>
                </div>

                <div class="form-group col-md-4">
                    <label class="d-block">مميز</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $product->is_featured ?? false) ? 'checked' : '' }} class="custom-control-input">
                        <label class="custom-control-label" for="is_featured">عرض في الصفحة الرئيسية</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-content" id="images">
            <div class="form-group">
                <label for="image_main">الصورة الرئيسية</label>
                <input type="file" id="image_main" name="image_main" class="form-control-file" accept="image/*">
                @if(isset($product) && $product->image_main)
                    <div class="image-preview mt-3">
                        <img src="{{ asset('storage/' . $product->image_main) }}" alt="{{ $product->name_ar }}" style="max-width: 200px; border-radius: 8px;">
                    </div>
                @endif
            </div>

            <div class="form-group">
                <label for="image_gallery">معرض الصور (يمكن اختيار عدة صور)</label>
                <input type="file" id="image_gallery" name="image_gallery[]" class="form-control-file" accept="image/*" multiple>
                <small class="form-text">يمكنك رفع عدة صور دفعة واحدة</small>
                
                @if(isset($product) && $product->image_gallery)
                    @php
                        $galleryImages = json_decode($product->image_gallery, true) ?? [];
                    @endphp
                    @if(!empty($galleryImages))
                        <div class="existing-gallery mt-3">
                            <h6>الصور الحالية في المعرض:</h6>
                            <div class="gallery-images" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                                @foreach($galleryImages as $index => $image)
                                    <div class="gallery-item" style="position: relative; display: inline-block;">
                                        <img src="{{ asset('storage/' . $image) }}" alt="Gallery image {{ $index + 1 }}" 
                                             style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;">
                                        <button type="button" class="remove-gallery-image btn btn-danger btn-sm" 
                                                style="position: absolute; top: -5px; right: -5px; border-radius: 50%; width: 24px; height: 24px; padding: 0; display: flex; align-items: center; justify-content: center;"
                                                data-image="{{ $image }}">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <input type="hidden" name="remove_gallery_images[]" value="{{ $image }}" class="remove-image-input" style="display: none;">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="tab-content" id="seo">
            <div class="form-group">
                <label for="meta_title">عنوان SEO (Meta Title)</label>
                <input type="text" id="meta_title" name="seo[meta_title]" value="{{ old('seo.meta_title', $product->seo['meta_title'] ?? '') }}" class="form-control">
                <small class="form-text">اتركه فارغاً لاستخدام اسم المنتج تلقائياً</small>
            </div>

            <div class="form-group">
                <label for="meta_description">وصف SEO (Meta Description)</label>
                <textarea id="meta_description" name="seo[meta_description]" rows="3" class="form-control">{{ old('seo.meta_description', $product->seo['meta_description'] ?? '') }}</textarea>
                <small class="form-text">اتركه فارغاً لاستخدام وصف المنتج تلقائياً</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ isset($product) ? 'تحديث' : 'حفظ' }}
            </button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.dataset.tab;

        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        this.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    });
});

// Auto-generate slug
document.getElementById('name_en')?.addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value) {
        slugInput.value = this.value.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// Gallery image removal
document.querySelectorAll('.remove-gallery-image').forEach(button => {
    button.addEventListener('click', function() {
        const galleryItem = this.closest('.gallery-item');
        const hiddenInput = galleryItem.querySelector('.remove-image-input');
        
        if (this.classList.contains('removed')) {
            // Restore the image
            this.classList.remove('removed');
            this.innerHTML = '<i class="fas fa-times"></i>';
            this.style.backgroundColor = '#dc3545';
            hiddenInput.style.display = 'none';
            galleryItem.style.opacity = '1';
        } else {
            // Mark for removal
            this.classList.add('removed');
            this.innerHTML = '<i class="fas fa-undo"></i>';
            this.style.backgroundColor = '#28a745';
            hiddenInput.style.display = 'block';
            galleryItem.style.opacity = '0.5';
        }
    });
});
</script>
@endpush

@push('styles')
<style>
.form-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #eee;
    padding-bottom: 0.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: none;
    color: #666;
    font-weight: 600;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: #f5f5f5;
}

.tab-btn.active {
    background: var(--accent-blue);
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>
@endpush
