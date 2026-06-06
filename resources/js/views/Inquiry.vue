<template>
    <div class="inquiry-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>استفسار</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span>/</span>
                    <span>استفسار</span>
                </div>
            </div>
        </section>

        <!-- Inquiry Section -->
        <section class="contact-section fade-up" id="inquiry">
            <div class="container">
                <div class="section-header">
                    <h2>أرسل استفسارك</h2>
                    <p>فريقنا جاهز للإجابة على جميع أسئلتك حول منتجاتنا وخدماتنا</p>
                </div>

                <div v-if="successMessage" class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    {{ successMessage }}
                </div>

                <div v-if="errorMessage" class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    {{ errorMessage }}
                </div>

                <div v-if="productName" class="alert alert-info">
                    <strong>استفسار عن المنتج:</strong> {{ productName }}
                    <br>
                    يمكنك تعديل الموضوع إذا كان الاستفسار عن عرض سعر أو توصيل.
                </div>

                <div class="contact-wrapper">
                    <div class="contact-info">
                        <h3>لماذا تتواصل معنا؟</h3>
                        
                        <div class="inquiry-reasons">
                            <div class="reason-item">
                                <i class="fas fa-question-circle"></i>
                                <div>
                                    <h4>استفسار عن منتج</h4>
                                    <p>احصل على معلومات تفصيلية عن أي منتج</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-calculator"></i>
                                <div>
                                    <h4>طلب عرض سعر</h4>
                                    <p>احصل على عرض سعر مخصص لمشروعك</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-truck"></i>
                                <div>
                                    <h4>التوصيل والشحن</h4>
                                    <p>استفسر عن خيارات التوصيل والمواعيد</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-cogs"></i>
                                <div>
                                    <h4>الدعم الفني</h4>
                                    <p>احصل على استشارة فنية متخصصة</p>
                                </div>
                            </div>
                        </div>

                        <div class="social-links">
                            <h4>أو تواصل مباشرة</h4>
                            <div class="quick-buttons">
                                <a :href="whatsappUrl" class="quick-btn whatsapp" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                    <span>واتساب</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-wrapper">
                        <h3>نموذج الاستفسار</h3>
                        <form class="contact-form" @submit.prevent="submitForm">
                            <div class="form-row two-cols">
                                <div class="form-group">
                                    <label for="name">الاسم الكامل <span class="required">*</span></label>
                                    <input type="text" id="name" v-model="form.name" required placeholder="أدخل اسمك الكامل">
                                </div>

                                <div class="form-group">
                                    <label for="phone">رقم الهاتف <span class="required">*</span></label>
                                    <input type="tel" id="phone" v-model="form.phone" required placeholder="+963 ...">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">البريد الإلكتروني (اختياري)</label>
                                <input type="email" id="email" v-model="form.email" placeholder="example@email.com">
                            </div>

                            <input v-if="productId" type="hidden" v-model="form.product_id">

                            <div class="form-group">
                                <label for="subject">موضوع الاستفسار <span class="required">*</span></label>
                                <select id="subject" v-model="form.subject" required>
                                    <option value="">اختر موضوع الاستفسار</option>
                                    <option value="product_inquiry">استفسار عن منتج</option>
                                    <option value="price_quote">طلب عرض سعر</option>
                                    <option value="delivery">التوصيل والشحن</option>
                                    <option value="technical_support">الدعم الفني</option>
                                    <option value="partnership">شراكة تجارية</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="message">تفاصيل الاستفسار <span class="required">*</span></label>
                                <textarea id="message" v-model="form.message" rows="6" required placeholder="اكتب تفاصيل استفسارك هنا..."></textarea>
                            </div>

                            <button type="submit" class="btn-submit" :disabled="loading">
                                <i class="fas fa-paper-plane"></i>
                                {{ loading ? 'جاري الإرسال...' : 'إرسال الاستفسار' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useInquiriesStore } from '@/stores/inquiries';

const route = useRoute();
const inquiriesStore = useInquiriesStore();

const loading = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const productId = ref(null);
const productName = ref('');
const whatsappUrl = ref('https://wa.me/963900000000');

const form = ref({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    product_id: null
});

onMounted(() => {
    if (route.query.product_id) {
        productId.value = route.query.product_id;
        form.value.product_id = route.query.product_id;
    }
    if (route.query.product_name) {
        productName.value = route.query.product_name;
        form.value.subject = 'product_inquiry';
    }
});

async function submitForm() {
    if (!form.value.name || !form.value.phone || !form.value.subject || !form.value.message) {
        errorMessage.value = 'يرجى ملء جميع الحقول المطلوبة';
        return;
    }

    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await inquiriesStore.create(form.value);
        successMessage.value = 'تم إرسال استفسارك بنجاح، سنتواصل معك في أقرب وقت ممكن.';
        form.value = { name: '', email: '', phone: '', subject: '', message: '', product_id: null };
        productId.value = null;
        productName.value = '';
    } catch (error) {
        errorMessage.value = 'فشل في إرسال الاستفسار، يرجى المحاولة مرة أخرى';
        console.error('Failed to submit inquiry:', error);
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.inquiry-page {
    min-height: 100vh;
}

/* Page Header */
.page-header {
    padding-top: 120px;
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #2563eb 100%);
    color: white;
    padding-bottom: 4rem;
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
    background: url('/assets/images/hero-bg.jpg') center/cover;
    opacity: 0.08;
    animation: backgroundPan 30s ease-in-out infinite alternate;
}

@keyframes backgroundPan {
    0% {
        transform: scale(1);
    }
    100% {
        transform: scale(1.1);
    }
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    position: relative;
    z-index: 1;
}

.page-header h1 {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
    letter-spacing: -0.02em;
    text-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.125rem;
    opacity: 0.95;
    font-weight: 500;
}

.breadcrumb a {
    color: white;
    text-decoration: none;
    transition: opacity 0.3s;
}

.breadcrumb a:hover {
    opacity: 0.8;
}

/* Contact Section */
.contact-section {
    padding: 6rem 0;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    position: relative;
}

.contact-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 90% 10%, rgba(59, 130, 246, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 10% 90%, rgba(59, 130, 246, 0.03) 0%, transparent 50%);
    pointer-events: none;
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
    position: relative;
    z-index: 1;
}

.section-header h2 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    color: #1e3a8a;
    letter-spacing: -0.02em;
}

.section-header p {
    color: #6b7280;
    font-size: 1.25rem;
    font-weight: 400;
}

.alert {
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #34d399;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #f87171;
}

.alert-info {
    background: linear-gradient(135deg, #e9f7fe 0%, #d1fae5 100%);
    color: #055160;
    border: 1px solid #38bdf8;
}

.contact-wrapper {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 3rem;
    position: relative;
    z-index: 1;
}

/* Contact Info */
.contact-info {
    background: white;
    padding: 2.5rem;
    border-radius: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.contact-info::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.02) 0%, rgba(30, 58, 138, 0.02) 100%);
    pointer-events: none;
}

.contact-info h3 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 2rem;
    color: #1e3a8a;
    letter-spacing: -0.01em;
    position: relative;
    z-index: 1;
}

.inquiry-reasons {
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
    position: relative;
    z-index: 1;
}

.reason-item {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
    padding: 1rem;
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.reason-item:hover {
    background: rgba(59, 130, 246, 0.05);
    transform: translateX(5px);
}

.reason-item i {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    transition: transform 0.3s;
}

.reason-item:hover i {
    transform: scale(1.1) rotate(5deg);
}

.reason-item h4 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 0.375rem;
    color: #1a1a1a;
    letter-spacing: -0.01em;
}

.reason-item p {
    color: #6b7280;
    font-size: 0.9375rem;
    line-height: 1.6;
}

.social-links {
    margin-top: 2.5rem;
    padding-top: 2rem;
    border-top: 1px solid #e5e7eb;
    position: relative;
    z-index: 1;
}

.social-links h4 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #1a1a1a;
}

.quick-buttons {
    margin-top: 1rem;
}

.quick-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.quick-btn.whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
    color: white;
}

.quick-btn.whatsapp:hover {
    background: linear-gradient(135deg, #128c7e 0%, #075e54 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.4);
}

/* Contact Form */
.contact-form-wrapper {
    background: white;
    padding: 2.5rem;
    border-radius: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.contact-form-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.02) 0%, rgba(30, 58, 138, 0.02) 100%);
    pointer-events: none;
}

.contact-form-wrapper h3 {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 2rem;
    color: #1e3a8a;
    letter-spacing: -0.01em;
    position: relative;
    z-index: 1;
}

.contact-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    position: relative;
    z-index: 1;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.form-group label {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.01em;
}

.required {
    color: #ef4444;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.875rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.9375rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Fade Up Animation */
.fade-up {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUp 0.8s ease-out forwards;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1024px) {
    .page-header h1 {
        font-size: 2.5rem;
    }

    .section-header h2 {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .page-header h1 {
        font-size: 2rem;
    }

    .page-header {
        padding-top: 100px;
        padding-bottom: 3rem;
    }

    .contact-wrapper {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .contact-info,
    .contact-form-wrapper {
        padding: 2rem;
    }

    .section-header h2 {
        font-size: 1.75rem;
    }

    .section-header p {
        font-size: 1.125rem;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.75rem;
    }

    .contact-info,
    .contact-form-wrapper {
        padding: 1.5rem;
    }

    .quick-btn {
        padding: 0.875rem 2rem;
        font-size: 0.9375rem;
    }
}
</style>
