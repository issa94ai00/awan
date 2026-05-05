@extends('admin.layout')

@section('title', 'إدارة المنتجات')

@section('content')
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-boxes"></i> إدارة المنتجات</h1>
        <p>إدارة منتجات الموقع والمخزون</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> منتج جديد
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.products.index') }}" method="GET" class="search-form">
            <div class="input-group">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث في المنتجات...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
        <div class="filter-group">
            <select name="category" onchange="this.form.submit()">
                <option value="">جميع الفئات</option>
                @foreach($categories ?? [] as $cat)
                    <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name_ar }}</option>
                @endforeach
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>نشط</option>
                <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>غير نشط</option>
            </select>
        </div>
    </div>

    <div class="card-body">
        @if(isset($products) && $products->count() > 0)
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="60">صورة</th>
                        <th>المنتج</th>
                        <th>الفئة</th>
                        <th width="100">السعر</th>
                        <th width="80">المخزون</th>
                        <th width="80">الحالة</th>
                        <th width="80">مميز</th>
                        <th width="150">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td>
                            <img src="{{ $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/products/default-product.jpg') }}" 
                                 alt="{{ $product->name_ar }}" class="product-thumb">
                        </td>
                        <td>
                            <strong>{{ $product->name_ar }}</strong>
                            <br><small class="text-muted">{{ Str::limit($product->name_en, 30) }}</small>
                        </td>
                        <td>{{ $product->category?->name_ar ?? '-' }}</td>
                        <td>
                            @if (get_setting('show_product_price', '1') == '1')
                            ${{ number_format($product->price, 2) }}
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $product->in_stock ? 'success' : 'danger' }}">
                                {{ $product->in_stock ? 'متوفر' : 'نفذ' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $product->is_active ? 'success' : 'danger' }}">
                                {{ $product->is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>
                        <td>
                            @if($product->is_featured)
                                <i class="fas fa-star text-warning"></i>
                            @else
                                <i class="fas fa-star text-muted" style="opacity: 0.3;"></i>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('product.show', $product) }}" target="_blank" class="btn btn-sm btn-info" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-warning" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($products, 'links') && $products->hasPages())
            <nav class="pagination-tailwind" aria-label="Pagination">
                <!-- Mobile view -->
                <div class="mobile-pagination">
                    @if($products->onFirstPage())
                    <span class="btn-prev disabled">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </span>
                    @else
                    <a href="{{ $products->previousPageUrl() }}" class="btn-prev">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </a>
                    @endif

                    @if($products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" class="btn-next">
                        التالي
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    @else
                    <span class="btn-next disabled">
                        التالي
                        <i class="fas fa-chevron-left"></i>
                    </span>
                    @endif
                </div>

                <!-- Desktop view -->
                <div class="desktop-pagination">
                    <p class="pagination-info">
                        عرض <span>{{ $products->firstItem() }}</span> إلى <span>{{ $products->lastItem() }}</span> من <span>{{ $products->total() }}</span> منتج
                    </p>

                    <div class="pagination-buttons">
                        <!-- Previous -->
                        @if($products->onFirstPage())
                        <span class="page-btn prev disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        @else
                        <a href="{{ $products->previousPageUrl() }}" class="page-btn prev">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        @endif

                        <!-- Page Numbers -->
                        @foreach($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                            @if($page == $products->currentPage())
                            <span class="page-btn active">{{ $page }}</span>
                            @else
                            <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                            @endif
                        @endforeach

                        <!-- Next -->
                        @if($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" class="page-btn next">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        @else
                        <span class="page-btn next disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                        @endif
                    </div>
                </div>
            </nav>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-boxes" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <p>لا توجد منتجات حالياً</p>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">إضافة منتج جديد</a>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
.product-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #eee;
}
</style>
@endpush
