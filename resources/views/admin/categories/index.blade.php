@extends('admin.layout')

@section('title', 'إدارة الفئات')

@section('content')
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-folder-open"></i> إدارة الفئات</h1>
        <p>إدارة فئات المنتجات والخدمات</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> فئة جديدة
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.categories.index') }}" method="GET" class="search-form">
            <div class="input-group">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث في الفئات...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
        <div class="filter-group">
            <select name="status" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>نشط</option>
                <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>غير نشط</option>
            </select>
        </div>
    </div>

    <div class="card-body">
        @if(isset($categories) && $categories->count() > 0)
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th width="60">الأيقونة</th>
                        <th>اسم الفئة</th>
                        <th>الوصف</th>
                        <th width="100">المنتجات</th>
                        <th width="80">الترتيب</th>
                        <th width="80">الحالة</th>
                        <th width="150">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                    <tr>
                        <td>{{ $category->id }}</td>
                        <td>
                            <div class="category-icon-small" style="background: rgba(0, 90, 156, 0.1); border-radius: 8px; padding: 8px; text-align: center;">
                                <i class="fas {{ $category->icon ?? 'fa-folder' }}" style="color: var(--accent-blue); font-size: 1.2rem;"></i>
                            </div>
                        </td>
                        <td>
                            <strong>{{ $category->name_ar }}</strong>
                            <br><small class="text-muted">{{ $category->name_en }}</small>
                        </td>
                        <td>{{ Str::limit($category->description, 50) }}</td>
                        <td>
                            <span class="badge badge-info">{{ $category->products_count ?? $category->product_count ?? 0 }}</span>
                        </td>
                        <td>{{ $category->sort_order }}</td>
                        <td>
                            <span class="badge badge-{{ $category->is_active ? 'success' : 'danger' }}">
                                {{ $category->is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-warning" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الفئة؟');">
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

            @if(method_exists($categories, 'links') && $categories->hasPages())
            <nav class="pagination-tailwind" aria-label="Pagination">
                <!-- Mobile view -->
                <div class="mobile-pagination">
                    @if($categories->onFirstPage())
                    <span class="btn-prev disabled">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </span>
                    @else
                    <a href="{{ $categories->previousPageUrl() }}" class="btn-prev">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </a>
                    @endif

                    @if($categories->hasMorePages())
                    <a href="{{ $categories->nextPageUrl() }}" class="btn-next">
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
                        عرض <span>{{ $categories->firstItem() }}</span> إلى <span>{{ $categories->lastItem() }}</span> من <span>{{ $categories->total() }}</span> فئة
                    </p>

                    <div class="pagination-buttons">
                        <!-- Previous -->
                        @if($categories->onFirstPage())
                        <span class="page-btn prev disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        @else
                        <a href="{{ $categories->previousPageUrl() }}" class="page-btn prev">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        @endif

                        <!-- Page Numbers -->
                        @foreach($categories->getUrlRange(1, $categories->lastPage()) as $page => $url)
                            @if($page == $categories->currentPage())
                            <span class="page-btn active">{{ $page }}</span>
                            @else
                            <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                            @endif
                        @endforeach

                        <!-- Next -->
                        @if($categories->hasMorePages())
                        <a href="{{ $categories->nextPageUrl() }}" class="page-btn next">
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
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <p>لا توجد فئات حالياً</p>
                <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">إضافة فئة جديدة</a>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-submit filter on change
document.querySelectorAll('.filter-group select').forEach(select => {
    select.addEventListener('change', function() {
        this.closest('form')?.submit();
    });
});
</script>
@endpush
