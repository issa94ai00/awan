@extends('layout')

@section('content')
<section class="page-header" style="padding-top: 120px; background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));">
    <div class="container">
        <h1>الفئات</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>الفئات</span>
        </div>
    </div>
</section>

<section class="categories fade-up">
    <div class="container">
        <div class="section-header">
            <h2>جميع الفئات</h2>
            <p>تصفح جميع فئات المنتجات المتاحة</p>
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
@endsection
