@extends('layout')

@section('title', 'الهوية والرؤية')

@section('content')
<section class="page-header" style="padding-top: 120px; background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));">
    <div class="container">
        <h1>الهوية والرؤية</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>الهوية والرؤية</span>
        </div>
    </div>
</section>

<section class="features fade-up" id="vision">
    <div class="container">
        <div class="section-header">
            <h2>الهوية والرؤية</h2>
            <p>نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.</p>
        </div>
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-award"></i>
                <h3>جودة عالمية</h3>
                <p>منتجات مختارة بعناية من أفضل الموردين لتناسب المشاريع الاحترافية.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-compass-drafting"></i>
                <h3>تصميم عصري</h3>
                <p>حلول ومواد تجمع بين الأداء العالي والمظهر الحديث.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-handshake"></i>
                <h3>شراكة موثوقة</h3>
                <p>دعم مستمر وخبرة لمساعدتك في اختيار الأنسب لمشروعك.</p>
            </div>
        </div>
    </div>
</section>
@endsection
