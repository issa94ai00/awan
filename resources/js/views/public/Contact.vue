<template>
    <div class="contact-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ t('nav_contact') || 'إتصل بنا' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ t('nav_contact') || 'إتصل بنا' }}</span>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="contact-section fade-up" id="contact">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('here_to_help') || 'نحن هنا لمساعدتك' }}</h2>
                    <p>{{ t('contact_desc') || 'تواصل معنا للاستفسارات، الطلبات، أو أي معلومات تحتاجها' }}</p>
                </div>

                <div class="contact-wrapper">
                    <div class="contact-info">
                        <h3>{{ t('contact_info') || 'معلومات التواصل' }}</h3>
                        
                        <div class="contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <h4>{{ t('phone_label') || 'الهاتف' }}</h4>
                                <p dir="ltr">{{ settings.contact_phone || '+963 900 000 000' }}</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>{{ t('email_label') || 'البريد الإلكتروني' }}</h4>
                                <p>{{ settings.contact_email || 'info@awan-altakaddom.com' }}</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>{{ t('location_label') || 'الموقع' }}</h4>
                                <p>{{ settings.address || 'سورية - دمشق' }}</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>{{ t('working_hours') || 'ساعات العمل' }}</h4>
                                <p>{{ settings.working_hours || 'السبت - الخميس: 9:00 ص - 6:00 م' }}</p>
                            </div>
                        </div>

                        <div class="social-links">
                            <h4>{{ t('follow_us') || 'تابعنا على' }}</h4>
                            <div class="social-icons">
                                <a :href="settings.contact_facebook || '#'" class="social-icon facebook" target="_blank">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000')" class="social-icon whatsapp" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a :href="settings.contact_instagram || '#'" class="social-icon instagram" target="_blank">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-wrapper">
                        <h3>{{ t('send_us_message') || 'أرسل لنا رسالة' }}</h3>
                        
                        <div v-if="submitted" class="success-message-box" style="background: #e6f4ea; color: #137333; padding: 20px; border-radius: 12px; text-align: center; margin-bottom: 20px;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            <strong>{{ t('contact_success_title') || 'تم إرسال رسالتك بنجاح!' }}</strong>
                            <p style="margin: 5px 0 0 0; font-size: 0.9rem;">{{ t('contact_success_desc') || 'سنقوم بالرد عليك في أقرب وقت ممكن.' }}</p>
                        </div>
                        
                        <form v-else @submit.prevent="submitForm" class="contact-form">
                            <div class="form-group">
                                <label for="name">{{ t('full_name') || 'الاسم الكامل' }} <span style="color:#dc3545;">*</span></label>
                                <input type="text" id="name" v-model="form.name" required :placeholder="t('enter_full_name') || 'أدخل اسمك الكامل'">
                            </div>

                            <div class="form-group">
                                <label for="email">{{ t('email_label') || 'البريد الإلكتروني' }}</label>
                                <input type="email" id="email" v-model="form.email" placeholder="example@email.com">
                            </div>

                            <div class="form-group">
                                <label for="phone">{{ t('phone_label') || 'رقم الهاتف' }} <span style="color:#dc3545;">*</span></label>
                                <input type="tel" id="phone" v-model="form.phone" required placeholder="+963 ...">
                            </div>

                            <div class="form-group">
                                <label for="subject">{{ t('subject_label') || 'الموضوع' }} <span style="color:#dc3545;">*</span></label>
                                <select id="subject" v-model="form.subject" required>
                                    <option value="">{{ t('choose_subject') || 'اختر الموضوع' }}</option>
                                    <option value="inquiry">{{ t('subject_general') || 'استفسار عام' }}</option>
                                    <option value="order">{{ t('subject_order') || 'طلب منتجات' }}</option>
                                    <option value="support">{{ t('subject_support') || 'دعم فني' }}</option>
                                    <option value="partnership">{{ t('subject_partnership') || 'شراكة تجارية' }}</option>
                                    <option value="other">{{ t('subject_other') || 'أخرى' }}</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="message">{{ t('message_label') || 'الرسالة' }} <span style="color:#dc3545;">*</span></label>
                                <textarea id="message" v-model="form.message" rows="5" required :placeholder="t('write_message_here') || 'اكتب رسالتك هنا...'"></textarea>
                            </div>

                            <div v-if="error" class="error-message-box" style="color:#dc3545; margin-bottom: 15px;">
                                {{ error }}
                            </div>

                            <button type="submit" class="btn-submit" :disabled="submitting">
                                <span v-if="submitting"><i class="fas fa-spinner fa-spin"></i> {{ t('sending_message') || 'جاري الإرسال...' }}</span>
                                <span v-else><i class="fas fa-paper-plane"></i> {{ t('send_message_btn') || 'إرسال الرسالة' }}</span>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="quick-contact">
                    <h3>{{ t('direct_contact') || 'تواصل مباشر' }}</h3>
                    <div class="quick-buttons">
                        <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000') + '?text=' + encodeURIComponent(t('whatsapp_default_msg') || 'مرحباً، أنا مهتم بمعرفة المزيد عن منتجاتكم')" class="quick-btn whatsapp" target="_blank">
                            <i class="fab fa-whatsapp"></i>
                            <span>{{ t('whatsapp_name') || 'واتساب' }}</span>
                        </a>
                        <a :href="'tel:' + (settings.contact_phone || '+963900000000')" class="quick-btn phone">
                            <i class="fas fa-phone"></i>
                            <span>{{ t('call_now') || 'اتصل الآن' }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { getImageUrl } from '@/utils/imageUrl';

const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.data);
const { t, locale } = useI18n();

// SEO Meta Tags
const updateSEOMetaTags = () => {
    const siteName = settings.value.site_name || 'أوان التكادوم';
    const contactTitle = locale.value === 'en' ? 'Contact Us' : 'تواصل معنا';
    const contactDescription = locale.value === 'en' ? 'Get in touch with us for any inquiries or support' : 'تواصل معنا لأي استفسارات أو دعم';
    const ogImage = settings.value.og_image ? getImageUrl(settings.value.og_image) : '/assets/images/logo.png';
    
    // Update document title
    document.title = `${contactTitle} - ${siteName}`;
    
    // Update meta description
    const metaDescription = document.querySelector('meta[name="description"]');
    if (metaDescription) {
        metaDescription.setAttribute('content', contactDescription);
    }
    
    // Update og:title
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
        ogTitle.setAttribute('content', `${contactTitle} - ${siteName}`);
    }
    
    // Update og:description
    const ogDescription = document.querySelector('meta[property="og:description"]');
    if (ogDescription) {
        ogDescription.setAttribute('content', contactDescription);
    }
    
    // Update og:image
    const ogImageMeta = document.querySelector('meta[property="og:image"]');
    if (ogImageMeta) {
        ogImageMeta.setAttribute('content', ogImage);
    }
    
    // Update twitter:title
    const twitterTitle = document.querySelector('meta[property="twitter:title"]');
    if (twitterTitle) {
        twitterTitle.setAttribute('content', `${contactTitle} - ${siteName}`);
    }
    
    // Update twitter:description
    const twitterDescription = document.querySelector('meta[property="twitter:description"]');
    if (twitterDescription) {
        twitterDescription.setAttribute('content', contactDescription);
    }
    
    // Update twitter:image
    const twitterImage = document.querySelector('meta[property="twitter:image"]');
    if (twitterImage) {
        twitterImage.setAttribute('content', ogImage);
    }
};

const form = reactive({
    name: '',
    email: '',
    phone: '',
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
            error.value = res.data?.message || 'حدث خطأ أثناء إرسال الرسالة';
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'فشل الاتصال بالخادم، يرجى إعادة المحاولة';
    } finally {
        submitting.value = false;
    }
};

// Fetch settings on mount
onMounted(() => {
    settingsStore.fetch().catch((err) => console.warn(err));
    // Update SEO meta tags
    updateSEOMetaTags();
});
</script>

<style scoped>
.contact-page-view {
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

.contact-item {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding: 16px 0;
    border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .contact-item {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.contact-item:last-of-type {
    border-bottom: none;
}

.contact-item i {
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

[data-theme="dark"] .contact-item i {
    background: color-mix(in srgb, var(--mobile-primary) 15%, transparent);
    color: var(--mobile-primary);
}

.contact-item:hover i {
    transform: scale(1.1) rotate(5deg);
    background: var(--mobile-primary);
    color: white;
}

[data-theme="dark"] .contact-item:hover i {
    background: var(--mobile-primary);
    color: #0f172a;
}

.contact-item h4 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #0f172a;
}

[data-theme="dark"] .contact-item h4 {
    color: #f1f5f9;
}

.contact-item p {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0;
}

[data-theme="dark"] .contact-item p {
    color: #94a3b8;
}

.social-links {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .social-links {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.social-links h4 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 15px 0;
    color: #0f172a;
}

[data-theme="dark"] .social-links h4 {
    color: #f1f5f9;
}

.social-icons {
    display: flex;
    gap: 12px;
}

.social-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-icon:hover {
    transform: translateY(-4px) scale(1.05);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

.social-icon.facebook {
    background: #1877f2;
}

.social-icon.whatsapp {
    background: #25d366;
}

.social-icon.instagram {
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
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

[data-theme="dark"] .btn-submit {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
}

[data-theme="dark"] .btn-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4) !important;
}

.btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.quick-contact {
    margin-top: 40px;
    background: rgba(255, 255, 255, 0.5) !important;
    backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    border-radius: 24px !important;
    padding: 30px;
    text-align: center;
}

[data-theme="dark"] .quick-contact {
    background: rgba(30, 41, 59, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.05) !important;
}

.quick-contact h3 {
    font-size: 1.35rem;
    font-weight: 800;
    margin-bottom: 20px;
    color: var(--mobile-primary);
}

[data-theme="dark"] .quick-contact h3 {
    color: var(--mobile-primary);
}

.quick-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.quick-btn {
    padding: 14px 28px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    color: white;
}

.quick-btn.whatsapp {
    background: #25d366;
}

.quick-btn.whatsapp:hover {
    background: #20ba5a;
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(37, 211, 102, 0.3);
}

[data-theme="dark"] .quick-btn.whatsapp {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
    box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3) !important;
}

[data-theme="dark"] .quick-btn.whatsapp:hover {
    background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%) !important;
    box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4) !important;
}

.quick-btn.phone {
    background: var(--mobile-primary);
}

.quick-btn.phone:hover {
    background: var(--el-color-primary-light-3);
    transform: translateY(-3px);
    box-shadow: 0 6px 15px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

[data-theme="dark"] .quick-btn.phone {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
}

[data-theme="dark"] .quick-btn.phone:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%) !important;
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4) !important;
}
</style>
