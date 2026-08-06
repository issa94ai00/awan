<template>
    <div class="vision-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>الهوية والرؤية</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">›</span>
                    <span>الهوية والرؤية</span>
                </div>
            </div>
        </section>

        <!-- Vision Section -->
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
    </div>
</template>

<script setup>
// Vision page component
</script>

<style scoped>
.vision-page {
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

/* Features Section */
.features {
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

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.feature-item {
    background: white;
    padding: 2.5rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.feature-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.feature-item i {
    font-size: 3rem;
    color: #3b82f6;
    margin-bottom: 1.5rem;
}

.feature-item h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #1a1a1a;
}

.feature-item p {
    color: #6b7280;
    line-height: 1.8;
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

    .features-grid {
        grid-template-columns: 1fr;
    }
}
</style>
