@extends('layout')

@section('title', 'من نحن')

@section('content')
<section class="page-header" style="padding-top: 120px; background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));">
    <div class="container">
        <h1>من نحن</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>من نحن</span>
        </div>
    </div>
</section>

<section class="about-section fade-up" id="about">
    <div class="container">
        <div class="section-header">
            <h2>أوان التقدم</h2>
            <p>شريكك الموثوق في مستلزمات البناء في سورية</p>
        </div>
        
        <div class="about-content">
            <div class="about-story">
                <h3>قصتنا</h3>
                <p>نحن في <strong>أوان التقدم</strong> نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية. نؤمن بأن نجاح أي مشروع يبدأ باختيار المواد المناسبة، ولذلك نحرص على توفير منتجات تلبي أعلى معايير الجودة والمتانة.</p>
                <p>منذ تأسيسنا، وضعنا نصب أعيننا هدفاً واضحاً: تقديم حلول بناء متكاملة تجمع بين الابتكار والموثوقية، لنكون الداعم الحقيقي لمسيرة التطور العمراني في سورية.</p>
            </div>

            <div class="about-values">
                <h3>قيمنا</h3>
                <div class="values-grid">
                    <div class="value-item">
                        <i class="fas fa-gem"></i>
                        <h4>الجودة</h4>
                        <p>نختار منتجاتنا بعناية فائقة من أفضل الموردين العالميين لضمان أعلى معايير الجودة.</p>
                    </div>
                    <div class="value-item">
                        <i class="fas fa-lightbulb"></i>
                        <h4>الابتكار</h4>
                        <p>نستمر في البحث عن أحدث التقنيات والحلول العصرية لتقديم الأفضل دائماً.</p>
                    </div>
                    <div class="value-item">
                        <i class="fas fa-handshake"></i>
                        <h4>الثقة</h4>
                        <p>نبني علاقات طويلة الأمد مع عملائنا مبنية على الشفافية والمصداقية.</p>
                    </div>
                    <div class="value-item">
                        <i class="fas fa-users"></i>
                        <h4>الخدمة</h4>
                        <p>فريق متخصص جاهز لتقديم الاستشارات والدعم الفني في كل خطوة.</p>
                    </div>
                </div>
            </div>

            <div class="about-services">
                <h3>ما نقدمه</h3>
                <div class="services-list">
                    <div class="service-item">
                        <i class="fas fa-tint"></i>
                        <span>مضخات مياه عالية الجودة</span>
                    </div>
                    <div class="service-item">
                        <i class="fas fa-bath"></i>
                        <span>خلاطات حمامات وأكسسوارات صحية</span>
                    </div>
                    <div class="service-item">
                        <i class="fas fa-layer-group"></i>
                        <span>كلادينج وقواطع جبسية</span>
                    </div>
                    <div class="service-item">
                        <i class="fas fa-tools"></i>
                        <span>أدوات ومشابك وعلاقات معدنية</span>
                    </div>
                    <div class="service-item">
                        <i class="fas fa-cogs"></i>
                        <span>أنظمة تثبيت ورفع متخصصة</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stats-section fade-up" style="background: var(--bg-light); padding: 60px 0;">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <i class="fas fa-calendar-alt"></i>
                <h3>+10</h3>
                <p>سنوات خبرة</p>
            </div>
            <div class="stat-item">
                <i class="fas fa-project-diagram"></i>
                <h3>+500</h3>
                <p>مشروع منجز</p>
            </div>
            <div class="stat-item">
                <i class="fas fa-smile"></i>
                <h3>+1000</h3>
                <p>عميل سعيد</p>
            </div>
            <div class="stat-item">
                <i class="fas fa-award"></i>
                <h3>+50</h3>
                <p>شريك موثوق</p>
            </div>
        </div>
    </div>
</section>
@endsection
