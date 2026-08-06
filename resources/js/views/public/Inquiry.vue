<template>
    <div class="inquiry-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ t('inquiry') || 'استفسار' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ t('inquiry') || 'استفسار' }}</span>
                </div>
            </div>
        </section>

        <!-- Inquiry Section -->
        <section class="contact-section fade-up" id="inquiry">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('send_your_inquiry') || 'أرسل استفسارك' }}</h2>
                    <p>{{ t('inquiry_subtitle') || 'فريقنا جاهز للإجابة على جميع أسئلتك حول منتجاتنا وخدماتنا' }}</p>
                </div>

                <div class="contact-wrapper">
                    <div class="contact-info">
                        <h3>{{ t('why_contact_us') || 'لماذا تتواصل معنا؟' }}</h3>
                        
                        <div class="inquiry-reasons">
                            <div class="reason-item">
                                <i class="fas fa-question-circle"></i>
                                <div>
                                    <h4>{{ t('product_inquiry_reason') || 'استفسار عن منتج' }}</h4>
                                    <p>{{ t('product_inquiry_desc') || 'احصل على معلومات تفصيلية عن أي منتج' }}</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-calculator"></i>
                                <div>
                                    <h4>{{ t('price_quote_reason') || 'طلب عرض سعر' }}</h4>
                                    <p>{{ t('price_quote_desc') || 'احصل على عرض سعر مخصص لمشروعك' }}</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-truck"></i>
                                <div>
                                    <h4>{{ t('delivery_shipping_reason') || 'التوصيل والشحن' }}</h4>
                                    <p>{{ t('delivery_shipping_desc') || 'استفسر عن خيارات التوصيل والمواعيد' }}</p>
                                </div>
                            </div>

                            <div class="reason-item">
                                <i class="fas fa-cogs"></i>
                                <div>
                                    <h4>{{ t('tech_support_reason') || 'الدعم الفني' }}</h4>
                                    <p>{{ t('tech_support_desc') || 'احصل على استشارة فنية متخصصة' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="social-links" style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--bg-gray);">
                            <h4>{{ t('or_contact_directly') || 'أو تواصل مباشرة' }}</h4>
                            <div class="quick-buttons" style="margin-top: 15px;">
                                <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000')" class="quick-btn whatsapp" target="_blank" style="padding: 10px 20px; font-size: 0.9rem;">
                                    <i class="fab fa-whatsapp"></i>
                                    <span>{{ t('whatsapp') || 'واتساب' }}</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-wrapper">
                        <h3>{{ t('inquiry_form') || 'نموذج الاستفسار' }}</h3>
                        
                        <div v-if="submitted" class="success-message-box" style="background: #e6f4ea; color: #137333; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 20px;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            <strong>{{ t('inquiry_sent_success') || 'تم إرسال استفسارك بنجاح!' }}</strong>
                            <p style="margin: 5px 0 0 0; font-size: 0.9rem;">{{ t('inquiry_review_msg') || 'سنقوم بمراجعته والرد عليك قريباً.' }}</p>
                        </div>
                        
                        <form v-else @submit.prevent="submitForm" class="contact-form">
                            <div class="form-row two-cols">
                                <div class="form-group">
                                    <label for="name">{{ t('full_name') || 'الاسم الكامل' }} <span style="color: #dc3545;">*</span></label>
                                    <input type="text" id="name" v-model="form.name" required>
                                </div>

                                <div class="form-group">
                                    <label for="phone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color: #dc3545;">*</span></label>
                                    <input type="tel" id="phone" v-model="form.phone" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">{{ t('email_label') || 'البريد الإلكتروني' }}</label>
                                <input type="email" id="email" v-model="form.email">
                            </div>

                            <div class="form-group">
                                <label for="subject">{{ t('inquiry_subject') || 'موضوع الاستفسار' }} <span style="color: #dc3545;">*</span></label>
                                <select id="subject" v-model="form.subject" required>
                                    <option value="">{{ t('inquiry_subject') }}</option>
                                    <option value="product_inquiry">{{ t('product_inquiry_reason') || 'استفسار عن منتج' }}</option>
                                    <option value="price_quote">{{ t('price_quote_reason') || 'طلب عرض سعر' }}</option>
                                    <option value="delivery">{{ t('delivery_shipping_reason') || 'التوصيل والشحن' }}</option>
                                    <option value="technical_support">{{ t('tech_support_reason') || 'الدعم الفني' }}</option>
                                    <option value="partnership">{{ t('business_partnership') || 'شراكة تجارية' }}</option>
                                    <option value="other">{{ t('inquiry_subject_options.other') || 'أخرى' }}</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="message">{{ t('inquiry_details') || 'تفاصيل الاستفسار' }} <span style="color: #dc3545;">*</span></label>
                                <textarea id="message" v-model="form.message" rows="6" required></textarea>
                            </div>

                            <div v-if="error" class="error-message-box" style="color:#dc3545; margin-bottom: 15px;">
                                {{ error }}
                            </div>

                            <button type="submit" class="btn-submit" :disabled="submitting">
                                <span v-if="submitting"><i class="fas fa-spinner fa-spin"></i> {{ t('sending') || 'جاري الإرسال...' }}</span>
                                <span v-else><i class="fas fa-paper-plane"></i> {{ t('send_inquiry') || 'إرسال الاستفسار' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.data);
const { t } = useI18n();

const form = reactive({
    name: '',
    phone: '',
    email: '',
    subject: '',
    message: ''
});

const submitting = ref(false);
const submitted = ref(false);
const error = ref(null);

const submitForm = async () => {
    submitting.value = true;
    error.value = null;
    try {
        const res = await axios.post('/api/v1/inquiries', form);
        if (res.data?.success) {
            submitted.value = true;
        } else {
            error.value = res.data?.message || 'حدث خطأ أثناء إرسال استفسارك';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'فشل الاتصال بالخادم، يرجى إعادة المحاولة';
    } finally {
        submitting.value = false;
    }
};
</script>

<style scoped>
.inquiry-page-view {
    padding-bottom: 3rem;
}

.contact-section {
    padding: 3rem 0 5rem;
}

.contact-wrapper {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 40px;
    align-items: start;
    margin-top: 30px;
}

@media (max-width: 992px) {
    .contact-wrapper {
        grid-template-columns: 1fr;
    }
}

.contact-info {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    padding: 30px;
    color: #1e293b;
    transition: all 0.4s ease;
}

[data-theme="dark"] .contact-info {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
    color: #f1f5f9;
}

.contact-info h3 {
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 25px;
    color: var(--mobile-primary);
}

[data-theme="dark"] .contact-info h3 {
    color: var(--mobile-primary);
}

.inquiry-reasons {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.reason-item {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.reason-item i {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

[data-theme="dark"] .reason-item i {
    background: color-mix(in srgb, var(--mobile-primary) 15%, transparent);
    color: var(--mobile-primary);
}

.reason-item:hover i {
    transform: scale(1.1) rotate(5deg);
    background: var(--mobile-primary);
    color: white;
}

[data-theme="dark"] .reason-item:hover i {
    background: var(--mobile-primary);
    color: #0f172a;
}

.reason-item h4 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #0f172a;
}

[data-theme="dark"] .reason-item h4 {
    color: #f1f5f9;
}

.reason-item p {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0;
}

[data-theme="dark"] .reason-item p {
    color: #94a3b8;
}

.contact-form-wrapper {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    padding: 30px;
}

[data-theme="dark"] .contact-form-wrapper {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

.contact-form-wrapper h3 {
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 25px;
    color: var(--mobile-primary);
}

[data-theme="dark"] .contact-form-wrapper h3 {
    color: var(--mobile-primary);
}

.contact-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 0.9rem;
    font-weight: 700;
    color: #334155;
}

[data-theme="dark"] .form-group label {
    color: #cbd5e1;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 18px;
    background: rgba(255, 255, 255, 0.5);
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: 14px;
    font-size: 0.95rem;
    color: #1e293b;
    font-family: inherit;
    box-sizing: border-box;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] .form-group input,
[data-theme="dark"] .form-group select,
[data-theme="dark"] .form-group textarea {
    background: rgba(15, 23, 42, 0.35);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f1f5f9;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    background: white;
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 12%, transparent);
}

[data-theme="dark"] .form-group input:focus,
[data-theme="dark"] .form-group select:focus,
[data-theme="dark"] .form-group textarea:focus {
    background: rgba(15, 23, 42, 0.55);
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--mobile-primary) 18%, transparent);
}

.form-row.two-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 640px) {
    .form-row.two-cols {
        grid-template-columns: 1fr;
    }
}

.btn-submit {
    padding: 14px 28px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    background: linear-gradient(135deg, var(--mobile-primary) 0%, color-mix(in srgb, var(--mobile-primary) 80%, white) 100%);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.quick-btn.whatsapp {
    background: #25d366;
    color: white;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-weight: 700;
}

.quick-btn.whatsapp:hover {
    background: #20ba5a;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(37, 211, 102, 0.25);
}
</style>
