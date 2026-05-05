@extends('layout')
@section('content')

<!-- Preloader with Pulsing Logo -->
<div id="preloader" class="preloader">
    <div class="preloader-content">
        <div class="logo-pulse">
            <img src="<?php echo get_setting('site_logo') ? asset(get_setting('site_logo')) : asset('assets/images/logo.png'); ?>" 
                 alt="<?php echo get_setting('site_name') ?? 'أوان التقدم'; ?>" 
                 class="preloader-logo">
            <div class="spinner-ring"></div>
        </div>
        <div class="preloader-text">
            <?php echo get_setting('site_name') ?? 'أوان التقدم'; ?>
        </div>
    </div>
</div>

<section class="hero" id="home">
    <div class="hero-content">
        <h1>نبني معاً غد سورية الأجمل</h1>
        <p>مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.</p>
    </div>
    <div class="floating-icons">
        <i class="fas fa-trowel"></i>
        <i class="fas fa-wrench"></i>
        <i class="fas fa-paint-roller"></i>
        <i class="fas fa-hard-hat"></i>
    </div>
</section>

@if(isset($featured_products) && $featured_products->count())

<section class="categories fade-up" id="categories">
        <div class="container">
            <div class="section-header">
                <h2>فئات المنتجات الرئيسية</h2>
                <p>تصفح الفئات الأكثر طلباً مع وصف سريع لكل فئة.</p>
            </div>
            <div class="categories-grid">
                @if(isset($categories) && count($categories))
                    @foreach ($categories as $category)
                    <div class="category-card" onclick="window.location.href='{{ route('category.show', $category) }}'">
                        <div class="category-icon"><i class="fas {{ $category->icon ?? 'fa-cube' }}"></i></div>
                        <h3>{{ $category->name_ar }}</h3>
                        <p>{{ $category->description ?? 'حلول ومواد بناء عالية الجودة' }}</p>
                        <span class="category-count">{{ $category->product_count ?? 0 }} منتج</span>
                    </div>
                    @endforeach
                @else
                    <div style="text-align:center; padding: 1rem; color:#666;">لا توجد فئات متاحة حالياً</div>
                @endif
            </div>
        </div>
    </section>

<section class="featured-products fade-up" id="featured-products">
    <div class="container">
        <div class="section-header">
            <h2>منتجات مميزة</h2>
            <p>أحدث وأفضل المنتجات في مجال البناء</p>
        </div>
            <div class="products-grid">
            @foreach ($featured_products as $product)
            <div class="product-card">
                <div class="product-image">
                    <img src="{{ $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/products/default-product.jpg') }}" alt="{{ $product->name_ar }}">
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
    </div>
</section>
@endif

<!-- If no featured products, show call-to-action section -->
@if(!isset($featured_products) || !$featured_products->count())
<section class="cta-section" style="background: var(--bg-light); padding: 80px 0; text-align: center;">
    <div class="container">
        <div class="cta-content">
            <h2 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; color: var(--primary-dark);">
                اكتشف عالمنا المتميز
            </h2>
            <p style="font-size: 1.2rem; margin-bottom: 2rem; color: #666;">
                نقدم أفضل مستلزمات البناء بجودة عالمية وأسعار تنافسية
            </p>
            <div class="cta-actions">
                <a href="{{ route('categories.index') }}" class="btn btn-primary btn-lg" style="background: var(--accent-blue); color: white; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 0 10px;">
                    <i class="fas fa-th-large"></i> استكشف الفئات
                </a>
                <a href="{{ route('contact') }}" class="btn btn-outline-primary btn-lg" style="border: 2px solid var(--accent-blue); color: var(--accent-blue); padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 0 10px;">
                    <i class="fas fa-phone"></i> تواصل معنا
                </a>
            </div>
        </div>
    </div>
</section>
@endif
@endsection

@push('styles')
<style>
/* Preloader Styles */
.preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
}

.preloader.fade-out {
    opacity: 0;
    visibility: hidden;
}

.preloader-content {
    text-align: center;
    color: white;
}

.logo-pulse {
    position: relative;
    margin-bottom: 2rem;
    display: inline-block;
}

.preloader-logo {
    width: 180px;
    height: auto;
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.preloader-text {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.spinner-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 220px;
    height: 220px;
    margin-top: -110px;
    margin-left: -110px;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top: 3px solid white;
    border-radius: 50%;
    animation: spin 2s linear infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.1);
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Hide main content initially */
.main-content {
    opacity: 0;
    transition: opacity 0.5s ease-in;
}

.main-content.visible {
    opacity: 1;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const preloader = document.getElementById('preloader');
    const mainContent = document.querySelector('.categories, .cta-section');
    
    // Hide preloader after page loads
    setTimeout(function() {
        preloader.classList.add('fade-out');
        
        // Show main content
        if (mainContent) {
            mainContent.style.opacity = '1';
        }
        
        // Remove preloader from DOM after transition
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 500);
    }, 4000); // 4 seconds preload time
});
</script>
@endpush
