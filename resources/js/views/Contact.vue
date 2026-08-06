<template>
    <div class="contact-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>إتصل بنا</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">›</span>
                    <span>إتصل بنا</span>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="contact-section fade-up" id="contact">
            <div class="container">
                <div class="section-header">
                    <h2>نحن هنا لمساعدتك</h2>
                    <p>تواصل معنا للاستفسارات، الطلبات، أو أي معلومات تحتاجها</p>
                </div>

                <div class="contact-wrapper">
                    <div class="contact-info">
                        <h3>معلومات التواصل</h3>
                        
                        <div class="contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <h4>الهاتف</h4>
                                <p dir="ltr">{{ phoneNumber }}</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>البريد الإلكتروني</h4>
                                <p>{{ email }}</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>الموقع</h4>
                                <p>سورية - دمشق</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>ساعات العمل</h4>
                                <p>السبت - الخميس: 9:00 ص - 6:00 م</p>
                            </div>
                        </div>

                        <div class="social-links">
                            <h4>تابعنا على</h4>
                            <div class="social-icons">
                                <a :href="facebookUrl" class="social-icon facebook" target="_blank">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a :href="whatsappUrl" class="social-icon whatsapp" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a :href="instagramUrl" class="social-icon instagram" target="_blank">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-wrapper">
                        <h3>أرسل لنا رسالة</h3>
                        <form class="contact-form" @submit.prevent="submitForm">
                            <div class="form-group">
                                <label for="name">الاسم الكامل</label>
                                <input type="text" id="name" v-model="form.name" required placeholder="أدخل اسمك الكامل">
                            </div>

                            <div class="form-group">
                                <label for="email">البريد الإلكتروني</label>
                                <input type="email" id="email" v-model="form.email" required placeholder="example@email.com">
                            </div>

                            <div class="form-group">
                                <label for="phone">رقم الهاتف</label>
                                <input type="tel" id="phone" v-model="form.phone" placeholder="+963 ...">
                            </div>

                            <div class="form-group">
                                <label for="subject">الموضوع</label>
                                <select id="subject" v-model="form.subject" required>
                                    <option value="">اختر الموضوع</option>
                                    <option value="inquiry">استفسار عام</option>
                                    <option value="order">طلب منتجات</option>
                                    <option value="support">دعم فني</option>
                                    <option value="partnership">شراكة تجارية</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="message">الرسالة</label>
                                <textarea id="message" v-model="form.message" rows="5" required placeholder="اكتب رسالتك هنا..."></textarea>
                            </div>

                            <button type="submit" class="btn-submit" :disabled="loading">
                                <i class="fas fa-paper-plane"></i>
                                {{ loading ? 'جاري الإرسال...' : 'إرسال الرسالة' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="quick-contact">
                    <h3>تواصل مباشر</h3>
                    <div class="quick-buttons">
                        <a :href="whatsappUrl" class="quick-btn whatsapp" target="_blank">
                            <i class="fab fa-whatsapp"></i>
                            <span>واتساب</span>
                        </a>
                        <a :href="`tel:${phoneNumber}`" class="quick-btn phone">
                            <i class="fas fa-phone"></i>
                            <span>اتصل الآن</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useInquiriesStore } from '@/stores/inquiries';

const inquiriesStore = useInquiriesStore();

const loading = ref(false);
const phoneNumber = ref('+963 900 000 000');
const email = ref('info@awan-altakaddom.com');
const whatsappUrl = ref('https://wa.me/963900000000');
const facebookUrl = ref('#');
const instagramUrl = ref('#');

const form = ref({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: ''
});

async function submitForm() {
    if (!form.value.name || !form.value.email || !form.value.subject || !form.value.message) {
        alert('يرجى ملء جميع الحقول المطلوبة');
        return;
    }

    loading.value = true;
    try {
        await inquiriesStore.create(form.value);
        alert('تم إرسال رسالتك بنجاح، سنتواصل معك في أقرب وقت ممكن.');
        form.value = { name: '', email: '', phone: '', subject: '', message: '' };
    } catch (error) {
        alert('فشل في إرسال الرسالة، يرجى المحاولة مرة أخرى');
        console.error('Failed to submit form:', error);
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.contact-page {
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

/* Contact Section */
.contact-section {
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

.contact-wrapper {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 3rem;
    margin-bottom: 3rem;
}

/* Contact Info */
.contact-info {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.contact-info h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #1e3a8a;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.contact-item:last-child {
    border-bottom: none;
}

.contact-item i {
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

.contact-item h4 {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #1a1a1a;
}

.contact-item p {
    color: #6b7280;
    font-size: 0.875rem;
}

.social-links {
    margin-top: 1.5rem;
}

.social-links h4 {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #1a1a1a;
}

.social-icons {
    display: flex;
    gap: 0.75rem;
}

.social-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
}

.social-icon.facebook {
    background: #1877f2;
}

.social-icon.whatsapp {
    background: #25d366;
}

.social-icon.instagram {
    background: #e4405f;
}

.social-icon:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Contact Form */
.contact-form-wrapper {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.contact-form-wrapper h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #1e3a8a;
}

.contact-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
}

.btn-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-submit:hover:not(:disabled) {
    background: #2563eb;
    transform: translateY(-2px);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Quick Contact */
.quick-contact {
    text-align: center;
    padding: 2rem;
    background: #f9fafb;
    border-radius: 12px;
}

.quick-contact h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #1e3a8a;
}

.quick-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.quick-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.quick-btn.whatsapp {
    background: #25d366;
    color: white;
}

.quick-btn.whatsapp:hover {
    background: #128c7e;
    transform: translateY(-2px);
}

.quick-btn.phone {
    background: #3b82f6;
    color: white;
}

.quick-btn.phone:hover {
    background: #2563eb;
    transform: translateY(-2px);
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

    .contact-wrapper {
        grid-template-columns: 1fr;
    }

    .quick-buttons {
        flex-direction: column;
    }
}
</style>
