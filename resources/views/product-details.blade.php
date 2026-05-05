@extends('layout')

@section('content')
<section class="page-header" style="margin-top: 80px;">
    <div class="container">
        <h1>{{ $product->name_ar }}</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            @if($product->category)
            <a href="{{ route('category.show', $product->category) }}">{{ $product->category->name_ar }}</a>
            <span>/</span>
            @endif
            <span>{{ $product->name_ar }}</span>
        </div>
    </div>
</section>

<section class="product-details-section fade-up">
    <div class="container">
        <div class="product-details-grid">
            <div class="product-images">
                <!-- Main Carousel Container -->
                <div class="product-image-carousel">
                    <!-- Main Image Display -->
                    <div class="carousel-main">
                        <div class="carousel-container">
                            @php
                                $allImages = [];
                                if ($product->image_main) {
                                    $allImages[] = [
                                        'url' => asset('storage/' . $product->image_main),
                                        'alt' => $product->name_ar
                                    ];
                                }
                                if ($product->image_gallery) {
                                    $galleryImages = json_decode($product->image_gallery, true) ?? [];
                                    foreach ($galleryImages as $image) {
                                        $allImages[] = [
                                            'url' => asset('storage/' . $image),
                                            'alt' => $product->name_ar
                                        ];
                                    }
                                }
                            @endphp
                            
                            @if(!empty($allImages))
                                <div class="carousel-track">
                                    @foreach($allImages as $index => $image)
                                        <div class="carousel-slide {{ $index === 0 ? 'active' : '' }}" data-index="{{ $index }}">
                                            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" onerror="this.src='{{ asset('assets/images/products/default-product.jpg') }}'">
                                        </div>
                                    @endforeach
                                </div>
                                
                                <!-- Navigation Arrows -->
                                @if(count($allImages) > 1)
                                    <button class="carousel-nav carousel-prev" type="button">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <button class="carousel-nav carousel-next" type="button">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                @endif
                            @else
                                <div class="carousel-slide active">
                                    <img src="{{ asset('assets/images/products/default-product.jpg') }}" alt="{{ $product->name_ar }}">
                                </div>
                            @endif
                        </div>
                        
                        <!-- Image Counter -->
                        @if(count($allImages) > 1)
                            <div class="carousel-counter">
                                <span class="current-index">1</span> / <span class="total-images">{{ count($allImages) }}</span>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Thumbnail Navigation -->
                    @if(count($allImages) > 1)
                        <div class="carousel-thumbnails">
                            <div class="thumbnails-container">
                                @foreach($allImages as $index => $image)
                                    <button class="thumbnail-btn {{ $index === 0 ? 'active' : '' }}" type="button" data-index="{{ $index }}">
                                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                        <div class="thumbnail-overlay"></div>
                                    </button>
                                @endforeach
                            </div>
                            
                            <!-- Thumbnail Navigation -->
                            <button class="thumbnails-nav thumbnails-prev" type="button">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button class="thumbnails-nav thumbnails-next" type="button">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="product-details-info">
                <div class="product-details-header">
                    <span class="product-category-badge">{{ $product->category->name_ar ?? 'منتجات بناء' }}</span>
                    <h1 class="product-title">{{ $product->name_ar }}</h1>
                    @if($product->name_en)
                    <h2 class="product-subtitle">{{ $product->name_en }}</h2>
                    @endif
                </div>

                @if (get_setting('show_product_price', '1') == '1' && $product->show_price && ($product->price ?? 0) > 0)
                <div class="product-details-price">
                    <span class="price-label">السعر:</span>
                    <span class="price-value">${{ number_format($product->price, 2) }}</span>
                </div>
                @endif

                <div class="product-description">
                    <h3>الوصف</h3>
                    <p>{{ $product->description_ar ?? 'لا يوجد وصف متاح' }}</p>
                    @if($product->description_en)
                    <p class="description-en">{{ $product->description_en }}</p>
                    @endif
                </div>

                <div class="product-meta">
                    @if($product->brand)
                    <div class="meta-item">
                        <span class="meta-label">العلامة التجارية:</span>
                        <span class="meta-value">{{ $product->brand }}</span>
                    </div>
                    @endif
                    @if($product->model)
                    <div class="meta-item">
                        <span class="meta-label">الموديل:</span>
                        <span class="meta-value">{{ $product->model }}</span>
                    </div>
                    @endif
                    <div class="meta-item">
                        <span class="meta-label">التوفر:</span>
                        <span class="meta-value {{ $product->in_stock ? 'in-stock' : 'out-of-stock' }}">
                            {{ $product->in_stock ? 'متوفر' : 'غير متوفر' }}
                        </span>
                    </div>
                </div>

                <div class="product-actions">
                    <a href="https://wa.me/{{ get_setting('contact_whatsapp') ?? '963900000000' }}?text=مرحباً، أنا مهتم بمنتج: {{ $product->name_ar }}" class="btn-whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i>
                        استفسار عبر واتساب
                    </a>
                    <a href="tel:{{ get_setting('contact_phone') ?? '+963900000000' }}" class="btn-phone">
                        <i class="fas fa-phone"></i>
                        اتصل بنا
                    </a>
                    <button type="button" class="btn-inquiry" onclick="document.getElementById('productInquiryModal').style.display='block'">
                        <i class="fas fa-question-circle"></i>
                        إرسال استفسار
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Product Inquiry Modal -->
<div id="productInquiryModal" class="inquiry-modal">
    <div class="inquiry-modal-content">
        <div class="inquiry-modal-header">
            <h2>استفسار عن المنتج</h2>
            <span class="inquiry-modal-close" onclick="document.getElementById('productInquiryModal').style.display='none'">&times;</span>
        </div>
        <div class="inquiry-modal-body">
            <div class="inquiry-product-info">
                <img src="{{ $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/products/default-product.jpg') }}" alt="{{ $product->name_ar }}" class="inquiry-product-img">
                <div class="inquiry-product-details">
                    <h3>{{ $product->name_ar }}</h3>
                    @if($product->name_en)
                    <p class="product-en">{{ $product->name_en }}</p>
                    @endif
                    @if (get_setting('show_product_price', '1') == '1' && $product->show_price && ($product->price ?? 0) > 0)
                    <p class="product-price">${{ number_format($product->price, 2) }}</p>
                    @endif
                </div>
            </div>

            @if(session('inquiry_success'))
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    {{ session('inquiry_success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="product-inquiry-form" action="{{ route('inquiry.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="product_name" value="{{ $product->name_ar }}">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="inquiry_name">الاسم الكامل <span class="required">*</span></label>
                        <input type="text" id="inquiry_name" name="name" required placeholder="أدخل اسمك الكامل" value="{{ old('name') }}">
                    </div>
                    <div class="form-group">
                        <label for="inquiry_phone">رقم الهاتف <span class="required">*</span></label>
                        <input type="tel" id="inquiry_phone" name="phone" required placeholder="+963 ..." value="{{ old('phone') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inquiry_email">البريد الإلكتروني (اختياري)</label>
                    <input type="email" id="inquiry_email" name="email" placeholder="example@email.com" value="{{ old('email') }}">
                </div>

                <div class="form-group">
                    <label for="inquiry_subject">موضوع الاستفسار <span class="required">*</span></label>
                    <select id="inquiry_subject" name="subject" required>
                        <option value="">اختر موضوع الاستفسار</option>
                        <option value="product_details" {{ old('subject') == 'product_details' ? 'selected' : '' }}>تفاصيل أكثر عن المنتج</option>
                        <option value="availability" {{ old('subject') == 'availability' ? 'selected' : '' }}>التوفر والكمية</option>
                        <option value="price_inquiry" {{ old('subject') == 'price_inquiry' ? 'selected' : '' }}>استفسار عن السعر</option>
                        <option value="delivery" {{ old('subject') == 'delivery' ? 'selected' : '' }}>التوصيل والشحن</option>
                        <option value="installation" {{ old('subject') == 'installation' ? 'selected' : '' }}>التركيب والصيانة</option>
                        <option value="other" {{ old('subject') == 'other' ? 'selected' : '' }}>أخرى</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="inquiry_message">تفاصيل الاستفسار <span class="required">*</span></label>
                    <textarea id="inquiry_message" name="message" rows="4" required placeholder="اكتب تفاصيل استفسارك هنا...">{{ old('message') }}</textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('productInquiryModal').style.display='none'">
                        <i class="fas fa-times"></i>
                        إلغاء
                    </button>
                    <button type="submit" class="btn-submit-inquiry">
                        <i class="fas fa-paper-plane"></i>
                        إرسال الاستفسار
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($related_products && $related_products->count())
<section class="related-products-section fade-up">
    <div class="container">
        <div class="section-header">
            <h2>منتجات ذات صلة</h2>
            <p>منتجات أخرى من نفس الفئة</p>
        </div>
        <div class="products-grid">
            @foreach($related_products as $related)
            <div class="product-card">
                <div class="product-image">
                    <img src="{{ $related->image_main ? asset('storage/' . $related->image_main) : asset('assets/images/products/default-product.jpg') }}" alt="{{ $related->name_ar }}" loading="lazy" onerror="this.src='{{ asset('assets/images/products/default-product.jpg') }}'">
                    <div class="product-overlay">
                        <a href="{{ route('product.show', $related) }}" class="view-btn"><i class="fas fa-eye"></i></a>
                    </div>
                </div>
                <div class="product-info">
                    <h3>{{ $related->name_ar }}</h3>
                    @if (get_setting('show_product_price', '1') == '1' && $related->show_price && ($related->price ?? 0) > 0)
                    <div class="product-price">${{ number_format($related->price, 2) }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection

@push('scripts')
<script>
// Product Image Carousel (Horizontal Scroll)
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('.product-image-carousel');
    if (!carousel) return;

    const track = carousel.querySelector('.carousel-track');
    const slides = carousel.querySelectorAll('.carousel-slide');
    const thumbnails = carousel.querySelectorAll('.thumbnail-btn');
    const prevBtn = carousel.querySelector('.carousel-prev');
    const nextBtn = carousel.querySelector('.carousel-next');
    const thumbnailsPrev = carousel.querySelector('.thumbnails-prev');
    const thumbnailsNext = carousel.querySelector('.thumbnails-next');
    const currentIndexSpan = carousel.querySelector('.current-index');
    const thumbnailsContainer = carousel.querySelector('.thumbnails-container');

    if (!track || slides.length <= 1) return;

    let currentIndex = 0;
    let autoplayInterval;
    let isProgrammaticScroll = false;

    const isRTL = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl'
        || (getComputedStyle(track).direction || '').toLowerCase() === 'rtl';

    function detectRtlScrollType() {
        if (!isRTL) return 'ltr';

        // Detect browser RTL scrollLeft behavior: 'negative' | 'reverse' | 'default'
        const initial = track.scrollLeft;
        track.scrollLeft = 1;
        const after = track.scrollLeft;
        track.scrollLeft = initial;

        // In some browsers, setting scrollLeft in RTL clamps to 0
        if (after === 0) return 'negative';
        return 'reverse';
    }

    const rtlScrollType = detectRtlScrollType();

    function getMaxScrollLeft() {
        return Math.max(0, track.scrollWidth - track.clientWidth);
    }

    function getNormalizedScrollLeft() {
        if (!isRTL) return track.scrollLeft;

        const max = getMaxScrollLeft();
        if (rtlScrollType === 'negative') {
            // RTL negative: scrollLeft goes from 0 to -max
            return Math.abs(track.scrollLeft);
        }

        // RTL reverse: scrollLeft goes from max (start) to 0 (end)
        return max - track.scrollLeft;
    }

    function setNormalizedScrollLeft(normalizedLeft, behavior = 'smooth') {
        const max = getMaxScrollLeft();
        const clamped = Math.min(Math.max(0, normalizedLeft), max);

        let targetLeft = clamped;
        if (isRTL) {
            if (rtlScrollType === 'negative') {
                targetLeft = -clamped;
            } else {
                targetLeft = max - clamped;
            }
        }

        track.scrollTo({ left: targetLeft, behavior });
    }

    function getSlideWidth() {
        return track.clientWidth || 1;
    }

    function setActive(index) {
        slides.forEach(s => s.classList.remove('active'));
        if (slides[index]) slides[index].classList.add('active');

        thumbnails.forEach(t => t.classList.remove('active'));
        if (thumbnails[index]) {
            thumbnails[index].classList.add('active');
            thumbnails[index].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        if (currentIndexSpan) currentIndexSpan.textContent = index + 1;
        currentIndex = index;
    }

    function goToSlide(index, behavior = 'smooth') {
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;

        const left = index * getSlideWidth();
        isProgrammaticScroll = true;
        setNormalizedScrollLeft(left, behavior);
        setTimeout(() => { isProgrammaticScroll = false; }, 250);

        setActive(index);
    }

    function nextSlide() { goToSlide(currentIndex + 1); }
    function prevSlide() { goToSlide(currentIndex - 1); }

    // Update active slide on manual scroll (fast swipe)
    let rafId;
    track.addEventListener('scroll', () => {
        if (isProgrammaticScroll) return;
        if (rafId) cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(() => {
            const width = getSlideWidth();
            const index = Math.round(getNormalizedScrollLeft() / width);
            if (index !== currentIndex && index >= 0 && index < slides.length) {
                setActive(index);
            }
        });
    }, { passive: true });

    // Nav buttons
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);
    if (nextBtn) nextBtn.addEventListener('click', nextSlide);

    // Thumbnail clicks
    thumbnails.forEach((thumb, index) => {
        thumb.addEventListener('click', () => goToSlide(index));
    });

    // Thumbnail navigation (horizontal)
    if (thumbnailsPrev && thumbnailsContainer) {
        thumbnailsPrev.addEventListener('click', () => {
            thumbnailsContainer.scrollBy({ left: isRTL ? 200 : -200, behavior: 'smooth' });
        });
    }
    if (thumbnailsNext && thumbnailsContainer) {
        thumbnailsNext.addEventListener('click', () => {
            thumbnailsContainer.scrollBy({ left: isRTL ? -200 : 200, behavior: 'smooth' });
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') prevSlide();
        if (e.key === 'ArrowRight') nextSlide();
    });

    // Autoplay (pause on hover)
    function startAutoplay() {
        autoplayInterval = setInterval(nextSlide, 4000);
    }
    function stopAutoplay() {
        clearInterval(autoplayInterval);
    }

    startAutoplay();
    carousel.addEventListener('mouseenter', stopAutoplay);
    carousel.addEventListener('mouseleave', startAutoplay);

    // Ensure correct initial state
    setActive(0);
});
</script>
@endpush

@push('styles')
<style>
/* Product Image Carousel Styles */
.product-image-carousel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.carousel-main {
    position: relative;
}

.carousel-container {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    background: #f8f9fa;
}

.carousel-track {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 transparent;
}

.carousel-track::-webkit-scrollbar {
    height: 6px;
}

.carousel-track::-webkit-scrollbar-track {
    background: transparent;
}

.carousel-track::-webkit-scrollbar-thumb {
    background: #dee2e6;
    border-radius: 3px;
}

.carousel-slide {
    min-width: 100%;
    scroll-snap-align: start;
    opacity: 1;
    position: relative;
}

.carousel-slide.active {
    opacity: 1;
}

.carousel-slide img {
    width: 100%;
    height: 400px;
    object-fit: contain;
    background: white;
    scroll-snap-align: start;
}

/* Navigation Arrows */
.carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    font-size: 18px;
}

.carousel-nav:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: translateY(-50%) scale(1.1);
}

.carousel-prev {
    right: 15px;
}

.carousel-next {
    left: 15px;
}

/* Image Counter */
.carousel-counter {
    position: absolute;
    bottom: 15px;
    left: 15px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    z-index: 10;
}

/* Thumbnail Navigation */
.carousel-thumbnails {
    position: relative;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 10px 40px;
}

.thumbnails-container {
    display: flex;
    gap: 10px;
    overflow-y: hidden;
    overflow-x: auto;
    max-height: 80px;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 transparent;
}

.thumbnails-container::-webkit-scrollbar {
    height: 4px;
}

.thumbnails-container::-webkit-scrollbar-track {
    background: transparent;
}

.thumbnails-container::-webkit-scrollbar-thumb {
    background: #dee2e6;
    border-radius: 2px;
}

.thumbnail-btn {
    flex: 0 0 auto;
    width: 60px;
    height: 60px;
    border: 2px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}

.thumbnail-btn:hover {
    border-color: #007bff;
    transform: scale(1.05);
}

.thumbnail-btn.active {
    border-color: #007bff;
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
}

.thumbnail-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    transition: background 0.3s ease;
}

.thumbnail-btn:hover .thumbnail-overlay {
    background: rgba(0, 0, 0, 0.1);
}

/* Thumbnail Navigation Arrows */
.thumbnails-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 12px;
    z-index: 10;
}

.thumbnails-nav:hover {
    background: rgba(0, 0, 0, 0.8);
}

.thumbnails-prev {
    right: 5px;
}

.thumbnails-next {
    left: 5px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .carousel-slide img {
        height: 300px;
    }
    
    .carousel-nav {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    
    .thumbnail-btn {
        width: 50px;
        height: 50px;
    }
    
    .carousel-thumbnails {
        padding: 8px 35px;
    }
}

@media (max-width: 480px) {
    .carousel-slide img {
        height: 250px;
    }
    
    .carousel-nav {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
    
    .carousel-prev {
        right: 10px;
    }
    
    .carousel-next {
        left: 10px;
    }
    
    .thumbnail-btn {
        width: 45px;
        height: 45px;
    }
    
    .carousel-counter {
        font-size: 12px;
        padding: 6px 10px;
    }
}

/* RTL Support */
[dir="rtl"] .carousel-prev {
    left: 15px;
    right: auto;
}

[dir="rtl"] .carousel-next {
    right: 15px;
    left: auto;
}

[dir="rtl"] .thumbnails-prev {
    left: 5px;
    right: auto;
}

[dir="rtl"] .thumbnails-next {
    right: 5px;
    left: auto;
}
</style>
@endpush
