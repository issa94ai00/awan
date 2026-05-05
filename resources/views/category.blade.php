@extends('layout')
@section('content')
<section class="page-header" style="margin-top: 80px;">
    <div class="container">
        <h1>{{ $category->name_ar }}</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>{{ $category->name_ar }}</span>
        </div>
    </div>
</section>

<section class="products-section category-products-section fade-up">
    <div class="container">
        <div class="products-header">
            <div>
                <h2 class="section-title" style="margin-bottom: 0.5rem;">منتجات {{ $category->name_ar }}</h2>
                <p style="color:#556;">{{ $category->description ?? 'تصفح المنتجات ضمن هذه الفئة' }}</p>
            </div>
        </div>

        @if($products && $products->count())
        <div class="products-grid">
            @foreach ($products as $product)
            <div class="product-card">
                <div class="product-image">
                    <img src="{{ $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/products/default-product.jpg') }}" alt="{{ $product->name_ar }}" loading="lazy" onerror="this.src='{{ asset('assets/images/products/default-product.jpg') }}'">
                    <div class="product-overlay">
                        <a href="{{ route('product.show', $product) }}" class="view-btn"><i class="fas fa-eye"></i></a>
                        <a href="#" class="cart-btn"><i class="fas fa-shopping-cart"></i></a>
                    </div>
                </div>
                <div class="product-info">
                    <div class="product-name-container">
                        <h3 class="product-title">{{ $product->name_ar }}</h3>
                        @if($product->name_en)
                        <span class="product-subtitle">{{ $product->name_en }}</span>
                        @endif
                    </div>
                    <div class="product-category">{{ optional($product->category)->name_ar ?? 'منتجات بناء' }}</div>
                    @if (get_setting('show_product_price', '1') == '1' && $product->show_price && ($product->price ?? 0) > 0)
                    <div class="product-price">${{ number_format($product->price, 2) }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

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
        <div style="text-align:center; padding: 2rem; color:#666;">لا توجد منتجات حالياً ضمن هذه الفئة</div>
        @endif
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scroll behavior to pagination links
    const paginationLinks = document.querySelectorAll('.pagination-container a');
    
    paginationLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Store scroll position before navigation
            sessionStorage.setItem('categoryProductsScroll', 'true');
        });
    });
    
    // Check if we should scroll to products section after page load
    if (sessionStorage.getItem('categoryProductsScroll')) {
        sessionStorage.removeItem('categoryProductsScroll');
        const productsSection = document.querySelector('.category-products-section');
        if (productsSection) {
            setTimeout(function() {
                productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
});
</script>
@endpush
@endsection
