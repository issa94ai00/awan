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
            <div class="blk_item">
                @php
                    $showPrice = (get_setting('show_product_price', '1') == '1') && (($product->show_price ?? false) === true) && (floatval($product->price ?? 0) > 0);
                @endphp
                <div style="display:none" class="pinfo_{{ $product->id }}">
                    {"title":"{{ addslashes($product->name_ar) }}","imgUrl":"{{ $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/products/default-product.jpg') }}","attrs":{
                        "Price":"{{ $showPrice ? ( ($product->sale_price && $product->sale_price < $product->price) ? '$'.number_format($product->sale_price,2) : '$'.number_format($product->price,2)) : 'To be discussed' }}",
                        "Brand Name":"{{ $product->brand ?? '—' }}",
                        "Model Number":"{{ $product->model ?? $product->sku ?? '—' }}",
                        "Minimum Order Quantity":"{{ $product->min_order ?? 1 }}",
                        "Delivery Time":"{{ $product->delivery_time ?? 'TBD' }}",
                        "Place of Origin":"{{ $product->origin ?? get_setting('site_country') ?? 'Local' }}",
                        "Description":"{{ addslashes(strip_tags($product->short_description_ar ?? $product->description_ar ?? '')) }}"
                    }}
                </div>

                <div class="img_box">
                    <a href="{{ route('product.show', $product) }}" title="{{ $product->name_ar }}">
                        <img class="lazy thumb" src="{{ $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/products/default-product.jpg') }}" alt="{{ $product->name_ar }}" style="display: inline; padding: 0px;">
                    </a>
                </div>

                <div class="txtlist_box">
                    <h2 class="blk_title">
                        <a href="{{ route('product.show', $product) }}" title="{{ $product->name_ar }}">
                            {{ Str::limit($product->name_ar, 50, '...') }}
                        </a>
                    </h2>
                    <div class="blk_tables">
                        @if($showPrice)
                            <span>السعر:<b title="{{ $product->price ? '$'.number_format($product->price,2) : '' }}"> {{ $product->sale_price && $product->sale_price < $product->price ? '$'.number_format($product->sale_price,2) : '$'.number_format($product->price,2) }}</b></span>
                        @else
                            <span>السعر:<b title="يتم التناقش حول السعر"> قابل للنقاش</b></span>
                        @endif
                        <span><b class="green" style="cursor:pointer;" onclick="window.location='{{ route('inquiry.create', ['product_id' => $product->id, 'product_name' => $product->name_ar]) }}'">اطلب عرضًا</b></span>
                    </div>
                    <ul class="blk_ul">
                        @if($product->brand)
                            <li><span class="key">العلامة:</span>{{ $product->brand }}</li>
                        @endif
                        @if($product->model || $product->sku)
                            <li><span class="key">الموديل:</span>{{ $product->model ?? $product->sku }}</li>
                        @endif
                        <li><span class="key">الحد الأدنى:</span>{{ $product->min_order ?? 1 }}</li>
                        <li><span class="key">التسليم:</span>{{ $product->delivery_time ?? 'قريباً' }}</li>
                        <li>
                            <a class="viewmore" href="{{ route('product.show', $product) }}" title="عرض التفاصيل">التفاصيل الكاملة</a>
                        </li>
                    </ul>
                </div>

                <div class="xplist_box">
                    <p class="xplist_tit">
                        <a href="{{ route('home') }}" title="المورد">
                            <span class="iconfont x-iconconstuction"></span>{{ $product->supplier_name ?? get_setting('site_name') ?? 'مورد محلي' }}
                        </a>
                    </p>
                    <p class="xplist_txt">{{ $product->supplier_location ?? get_setting('site_city') ?? 'غير معروف' }}, {{ $product->supplier_region ?? get_setting('site_region') ?? '' }}</p>
                    <ul>
                        <li><em><i class="icons-hg"></i></em><span>{{ $product->supplier_years ?? 'سنة واحدة' }}</span></li>
                        <li><em><i class="icons-verified"></i></em><span>مورد معتمد</span></li>
                    </ul>
                    <div class="xplist_btn">
                        <a href="{{ route('inquiry.create', ['product_id' => $product->id, 'product_name' => $product->name_ar]) }}">
                            <span class="xplist_btn_tit">
                                <b class="xplist_top">تواصل معنا</b>
                                <b class="xplist_bot">اطلب عرضًا</b>
                            </span>
                            <i class="iconfont x-iconwebsite"></i>
                        </a>
                    </div>
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
