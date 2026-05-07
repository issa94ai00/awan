@extends('admin.layout')

@section('title', 'نقطة البيع')

@section('content')
<div class="pos-page">
    <div class="page-header">
        <h1><i class="fas fa-cash-register"></i> نقطة البيع</h1>
        <p>اختر الفئة لعرض المنتجات</p>
    </div>

    {{-- Category Filter Tabs --}}
    <div class="pos-categories-tabs">
        <button class="cat-tab active" data-category="all" onclick="filterCategory('all')">
            <i class="fas fa-th-large"></i>
            <span>الكل</span>
        </button>
        @foreach($categories as $category)
            <button class="cat-tab" data-category="{{ $category->id }}" onclick="filterCategory({{ $category->id }})">
                @if($category->icon)
                    <i class="{{ $category->icon }}"></i>
                @else
                    <i class="fas fa-folder"></i>
                @endif
                <span>{{ $category->name_ar }}</span>
                <small>{{ $category->product_count }}</small>
            </button>
        @endforeach
    </div>

    {{-- Categories Grid --}}
    <div class="pos-categories-grid" id="categoriesGrid">
        @foreach($categories as $category)
        <div class="pos-category-card" data-category-id="{{ $category->id }}">
            <div class="category-card-header">
                @if($category->image)
                    <img src="{{ Storage::url($category->image) }}" alt="{{ $category->name_ar }}" class="category-img">
                @else
                    <div class="category-icon-wrapper">
                        @if($category->icon)
                            <i class="{{ $category->icon }}"></i>
                        @else
                            <i class="fas fa-folder-open"></i>
                        @endif
                    </div>
                @endif
            </div>
            <div class="category-card-body">
                <h3 class="category-name">{{ $category->name_ar }}</h3>
                <p class="category-name-en">{{ $category->name_en }}</p>
                <div class="category-meta">
                    <span class="product-count">
                        <i class="fas fa-box"></i>
                        {{ $category->product_count }} منتج
                    </span>
                </div>
            </div>
            <div class="category-card-footer">
                <button class="btn-show-products" onclick="toggleProducts({{ $category->id }})">
                    <i class="fas fa-eye"></i>
                    عرض المنتجات
                </button>
            </div>

            {{-- Products List (hidden by default) --}}
            <div class="category-products-list" id="products-{{ $category->id }}" style="display: none;">
                @if($category->products->count() > 0)
                    <div class="products-grid">
                        @foreach($category->products as $product)
                        <div class="pos-product-card {{ $product->in_stock ? '' : 'out-of-stock' }}">
                            <div class="product-image">
                                @if($product->image_main)
                                    <img src="{{ Storage::url($product->image_main) }}" alt="{{ $product->name_ar }}">
                                @else
                                    <div class="product-placeholder">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                @endif
                                @unless($product->in_stock)
                                    <span class="stock-badge out">غير متوفر</span>
                                @endunless
                            </div>
                            <div class="product-info">
                                <h4>{{ $product->name_ar }}</h4>
                                @if($product->show_price && $product->price)
                                    <span class="product-price">${{ number_format($product->price, 2) }}</span>
                                @else
                                    <span class="product-price contact">اتصل للسعر</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="no-products">
                        <i class="fas fa-box-open"></i>
                        <p>لا توجد منتجات في هذه الفئة</p>
                    </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Empty State --}}
    @if($categories->count() === 0)
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h3>لا توجد فئات</h3>
        <p>لم يتم إضافة أي فئات بعد</p>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة فئة جديدة
        </a>
    </div>
    @endif
</div>

<style>
    .pos-page {
        padding: 0;
    }

    /* Category Tabs */
    .pos-categories-tabs {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding: 1rem 0;
        margin-bottom: 1.5rem;
        scrollbar-width: thin;
        scrollbar-color: var(--accent-blue) transparent;
    }

    .pos-categories-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .pos-categories-tabs::-webkit-scrollbar-thumb {
        background: var(--accent-blue);
        border-radius: 4px;
    }

    .cat-tab {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        border: 2px solid var(--border-color);
        border-radius: 50px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        font-family: 'Cairo', sans-serif;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .cat-tab i {
        font-size: 1rem;
    }

    .cat-tab small {
        background: var(--bg-light);
        padding: 0.1rem 0.5rem;
        border-radius: 20px;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .cat-tab:hover {
        border-color: var(--accent-blue);
        color: var(--accent-blue);
        background: rgba(0, 90, 156, 0.05);
    }

    .cat-tab.active {
        border-color: var(--accent-blue);
        background: var(--accent-blue);
        color: white;
    }

    .cat-tab.active small {
        background: rgba(255,255,255,0.25);
        color: white;
    }

    /* Categories Grid */
    .pos-categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    /* Category Card */
    .pos-category-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .pos-category-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0, 90, 156, 0.12);
        border-color: rgba(0, 90, 156, 0.15);
    }

    .pos-category-card.hidden-card {
        display: none;
    }

    /* Card Header */
    .category-card-header {
        height: 160px;
        overflow: hidden;
        position: relative;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .category-card-header .category-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .pos-category-card:hover .category-img {
        transform: scale(1.08);
    }

    .category-icon-wrapper {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: rgba(255,255,255,0.9);
    }

    /* Card Body */
    .category-card-body {
        padding: 1.25rem 1.25rem 0.75rem;
        text-align: center;
    }

    .category-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.15rem;
    }

    .category-name-en {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
    }

    .category-meta {
        display: flex;
        justify-content: center;
        gap: 0.75rem;
    }

    .product-count {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: rgba(0, 90, 156, 0.08);
        color: var(--accent-blue);
        padding: 0.3rem 0.85rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* Card Footer */
    .category-card-footer {
        padding: 0.75rem 1.25rem 1.25rem;
        text-align: center;
    }

    .btn-show-products {
        width: 100%;
        padding: 0.65rem 1rem;
        border: 2px solid var(--accent-blue);
        border-radius: 10px;
        background: transparent;
        color: var(--accent-blue);
        font-family: 'Cairo', sans-serif;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-show-products:hover {
        background: var(--accent-blue);
        color: white;
    }

    .btn-show-products.active {
        background: var(--accent-blue);
        color: white;
    }

    .btn-show-products.active i::before {
        content: "\f06e";
    }

    /* Products List */
    .category-products-list {
        border-top: 1px solid var(--border-color);
        padding: 1.25rem;
        background: var(--bg-light);
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 0.75rem;
    }

    /* Product Card */
    .pos-product-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .pos-product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    }

    .pos-product-card.out-of-stock {
        opacity: 0.6;
    }

    .product-image {
        height: 100px;
        overflow: hidden;
        position: relative;
        background: var(--bg-light);
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .pos-product-card:hover .product-image img {
        transform: scale(1.05);
    }

    .product-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-size: 1.8rem;
    }

    .stock-badge {
        position: absolute;
        top: 6px;
        left: 6px;
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .stock-badge.out {
        background: var(--danger);
        color: white;
    }

    .product-info {
        padding: 0.6rem 0.75rem;
        text-align: center;
    }

    .product-info h4 {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-price {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--success);
    }

    .product-price.contact {
        font-size: 0.7rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* No Products */
    .no-products {
        text-align: center;
        padding: 1.5rem;
        color: var(--text-muted);
    }

    .no-products i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        display: block;
        opacity: 0.5;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
        opacity: 0.4;
    }

    .empty-state h3 {
        font-size: 1.3rem;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }

    .empty-state .btn {
        margin-top: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .pos-categories-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }

        .category-card-header {
            height: 120px;
        }

        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        }
    }

    @media (max-width: 480px) {
        .pos-categories-grid {
            grid-template-columns: 1fr;
        }

        .products-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<script>
    function filterCategory(categoryId) {
        // Update active tab
        document.querySelectorAll('.cat-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`.cat-tab[data-category="${categoryId}"]`).classList.add('active');

        // Filter cards
        const cards = document.querySelectorAll('.pos-category-card');
        cards.forEach(card => {
            if (categoryId === 'all') {
                card.classList.remove('hidden-card');
            } else {
                const cardId = card.getAttribute('data-category-id');
                card.classList.toggle('hidden-card', cardId !== String(categoryId));
            }
        });
    }

    function toggleProducts(categoryId) {
        const productsList = document.getElementById('products-' + categoryId);
        const btn = productsList.closest('.pos-category-card').querySelector('.btn-show-products');

        if (productsList.style.display === 'none') {
            productsList.style.display = 'block';
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> إخفاء المنتجات';
        } else {
            productsList.style.display = 'none';
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-eye"></i> عرض المنتجات';
        }
    }
</script>
@endsection
