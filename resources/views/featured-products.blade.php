@extends('layout')

@section('content')
<section class="page-header" style="padding-top: 120px; background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));">
    <div class="container">
        <h1>المنتجات المميزة</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>المنتجات المميزة</span>
        </div>
    </div>
</section>

<section class="featured-products-section fade-up" style="padding: 60px 0;">
    <div class="container">
        <div class="section-header">
            <h2>منتجاتنا المميزة</h2>
            <p>اختر من مجموعتنا المتميزة من منتجات البناء عالية الجودة</p>
        </div>

        @if($featured_products->count() > 0)
            <div class="products-grid">
                @foreach($featured_products as $product)
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
                        <div class="product-category">{{ $product->category->name_ar ?? 'منتجات بناء' }}</div>
                        @if (get_setting('show_product_price', '1') == '1' && $product->show_price && ($product->price ?? 0) > 0)
                        <div class="product-price">${{ number_format($product->price, 2) }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            @if(method_exists($featured_products, 'links') && $featured_products->hasPages())
            <nav class="pagination-tailwind" aria-label="Pagination">
                <!-- Mobile view -->
                <div class="mobile-pagination">
                    @if($featured_products->onFirstPage())
                    <span class="btn-prev disabled">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </span>
                    @else
                    <a href="{{ $featured_products->previousPageUrl() }}" class="btn-prev">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </a>
                    @endif

                    @if($featured_products->hasMorePages())
                    <a href="{{ $featured_products->nextPageUrl() }}" class="btn-next">
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
                        عرض <span>{{ $featured_products->firstItem() }}</span> إلى <span>{{ $featured_products->lastItem() }}</span> من <span>{{ $featured_products->total() }}</span> منتج
                    </p>

                    <div class="pagination-buttons">
                        <!-- Previous -->
                        @if($featured_products->onFirstPage())
                        <span class="page-btn prev disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        @else
                        <a href="{{ $featured_products->previousPageUrl() }}" class="page-btn prev">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        @endif

                        <!-- Page Numbers -->
                        @foreach($featured_products->getUrlRange(1, $featured_products->lastPage()) as $page => $url)
                            @if($page == $featured_products->currentPage())
                            <span class="page-btn active">{{ $page }}</span>
                            @else
                            <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                            @endif
                        @endforeach

                        <!-- Next -->
                        @if($featured_products->hasMorePages())
                        <a href="{{ $featured_products->nextPageUrl() }}" class="page-btn next">
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
            <div class="no-products" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-box-open" style="font-size: 4rem; color: var(--accent-blue); margin-bottom: 20px;"></i>
                <h3 style="color: var(--primary-dark); margin-bottom: 15px;">لا توجد منتجات مميزة حالياً</h3>
                <p style="color: var(--text-gray);">يمكنك تصفح جميع منتجاتنا من خلال صفحة الفئات</p>
                <a href="{{ route('categories.index') }}" class="btn" style="margin-top: 20px;">
                    <i class="fas fa-th-large"></i>
                    تصفح الفئات
                </a>
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scroll behavior to pagination links
    const paginationLinks = document.querySelectorAll('.pagination-container a');
    
    paginationLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            // Store scroll position before navigation
            sessionStorage.setItem('featuredProductsScroll', 'true');
        });
    });
    
    // Check if we should scroll to products section after page load
    if (sessionStorage.getItem('featuredProductsScroll')) {
        sessionStorage.removeItem('featuredProductsScroll');
        const productsSection = document.querySelector('.featured-products-section');
        if (productsSection) {
            setTimeout(function() {
                productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
});
</script>
@endpush
