<template>
    <div class="about-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>من نحن</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">›</span>
                    <span>من نحن</span>
                </div>
            </div>
        </section>

        <!-- About Section -->
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

        <!-- Stats Section -->
        <section class="stats-section fade-up">
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
    </div>
</template>

<script setup>
// About page component
</script>

<style scoped>
.about-page {
    min-height: 100vh;
}

/* Page Header */
.page-header {
    padding-top: 120px;
    background: linear-gradient(135deg, var(--mobile-primary-dark), #5a6b7a);
    color: white;
    padding-bottom: 3rem;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.04) 0%, transparent 50%);
    pointer-events: none;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    opacity: 0.9;
}

.breadcrumb a {
    color: white;
    text-decoration: none;
    transition: opacity 0.3s;
}

.breadcrumb a:hover {
    opacity: 0.8;
}

/* About Section */
.about-section {
    padding: 4rem 0;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1e3a8a;
}

.section-header p {
    color: #6b7280;
    font-size: 1.125rem;
}

.about-content {
    display: flex;
    flex-direction: column;
    gap: 3rem;
}

.about-story h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #1e3a8a;
}

.about-story p {
    line-height: 1.8;
    color: #374151;
    margin-bottom: 1rem;
}

.about-values h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #1e3a8a;
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.value-item {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.value-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.value-item i {
    font-size: 2.5rem;
    color: #3b82f6;
    margin-bottom: 1rem;
}

.value-item h4 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #1a1a1a;
}

.value-item p {
    color: #6b7280;
    font-size: 0.875rem;
    line-height: 1.6;
}

.about-services h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #1e3a8a;
}

.services-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.service-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.service-item:hover {
    transform: translateX(10px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.service-item i {
    width: 40px;
    height: 40px;
    background: #3b82f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.service-item span {
    font-weight: 500;
    color: #1a1a1a;
}

/* Stats Section */
.stats-section {
    padding: 4rem 0;
    background: #f9fafb;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
}

.stat-item {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.stat-item i {
    font-size: 2.5rem;
    color: #3b82f6;
    margin-bottom: 1rem;
}

.stat-item h3 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1e3a8a;
}

.stat-item p {
    color: #6b7280;
    font-size: 0.875rem;
}

/* Fade Up Animation */
.fade-up {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUp 0.6s ease-out forwards;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .page-header h1 {
        font-size: 1.75rem;
    }

    .section-header h2 {
        font-size: 1.5rem;
    }

    .values-grid {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
