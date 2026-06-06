<template>
    <div class="settings-page">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>إعدادات النظام</h2>
                        <p class="text-muted">تحكم كامل في إعدادات الموقع، التعريب، العملة، الإشعارات والمحتوى.</p>
                    </div>
                </div>
            </template>

            <el-tabs v-model="activeTab" type="border-card">
                <el-tab-pane label="عام" name="general">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="اسم الموقع">
                                    <el-input v-model="form.site_name" placeholder="أوان التقدم" />
                                </el-form-item>
                            </el-col>

                            <el-col :xs="24" :md="12">
                                <el-form-item label="شعار الموقع (Tagline)">
                                    <el-input v-model="form.site_tagline" placeholder="نحو إدارة أعمال أكثر ذكاءً" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="وصف الموقع">
                            <el-input
                                type="textarea"
                                :rows="4"
                                v-model="form.site_description"
                                placeholder="أدخل وصفًا موجزًا للموقع أو الشركة"
                            />
                        </el-form-item>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="عرض اسم الموقع في الشعار">
                                    <el-switch v-model="form.show_site_name" active-text="نشط" inactive-text="غير نشط" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="عرض أسعار المنتجات">
                                    <el-switch v-model="form.show_product_price" active-text="مفعل" inactive-text="معطل" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="الشعار والصور" name="media">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="شعار الموقع">
                                    <input type="file" accept="image/*" @change="onFileSelect($event, 'logo')" />
                                    <div v-if="logoPreview" class="preview-image mt-3">
                                        <img :src="logoPreview" alt="Logo Preview" />
                                    </div>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="أيقونة الموقع (Favicon)">
                                    <input type="file" accept="image/*" @change="onFileSelect($event, 'favicon')" />
                                    <div v-if="faviconPreview" class="preview-image mt-3" style="max-width: 96px;">
                                        <img :src="faviconPreview" alt="Favicon Preview" />
                                    </div>
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="العملة واللغة" name="localization">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="العملة الافتراضية">
                                    <el-select v-model="form.default_currency" placeholder="اختر العملة">
                                        <el-option
                                            v-for="item in currencies"
                                            :key="item.value"
                                            :label="item.label"
                                            :value="item.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="اللغة الافتراضية">
                                    <el-select v-model="form.default_language" placeholder="اختر اللغة">
                                        <el-option
                                            v-for="item in languages"
                                            :key="item.value"
                                            :label="item.label"
                                            :value="item.value"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="المنطقة الزمنية">
                            <el-select v-model="form.timezone" placeholder="اختر المنطقة الزمنية">
                                <el-option
                                    v-for="item in timezones"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="تواصل" name="contact">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="رقم الهاتف">
                                    <el-input v-model="form.contact_phone" placeholder="+201234567890" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="رقم الواتساب">
                                    <el-input v-model="form.contact_whatsapp" placeholder="+201234567890" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="البريد الإلكتروني">
                                    <el-input v-model="form.contact_email" type="email" placeholder="info@example.com" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="ساعات العمل">
                                    <el-input v-model="form.working_hours" placeholder="الأحد - الخميس 9:00 - 17:00" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="العنوان">
                            <el-input type="textarea" :rows="3" v-model="form.address" placeholder="العنوان الكامل للمقر أو الشركة" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="وسائل التواصل" name="social">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="فيسبوك">
                                    <el-input v-model="form.facebook" placeholder="https://facebook.com/..." />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="انستغرام">
                                    <el-input v-model="form.instagram" placeholder="https://instagram.com/..." />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="تويتر / X">
                                    <el-input v-model="form.twitter" placeholder="https://twitter.com/..." />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="يوتيوب">
                                    <el-input v-model="form.youtube" placeholder="https://youtube.com/..." />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="لينكدإن">
                            <el-input v-model="form.linkedin" placeholder="https://linkedin.com/..." />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="SEO" name="seo">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="عنوان الموقع (Meta Title)">
                                    <el-input v-model="form.meta_title" placeholder="عنوان الموقع لمحركات البحث" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="الكلمات المفتاحية (Meta Keywords)">
                                    <el-input v-model="form.meta_keywords" placeholder="erp, pos, erp system" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="وصف الموقع (Meta Description)">
                            <el-input type="textarea" :rows="3" v-model="form.meta_description" placeholder="وصف قصير يظهر في نتائج البحث" />
                        </el-form-item>

                        <el-form-item label="Google Analytics ID">
                            <el-input v-model="form.google_analytics" placeholder="G-XXXXXXXXXX" />
                        </el-form-item>

                        <el-form-item label="صورة Open Graph">
                            <input type="file" accept="image/*" @change="onFileSelect($event, 'ogImage')" />
                            <div v-if="ogImagePreview" class="preview-image mt-3">
                                <img :src="ogImagePreview" alt="OG Image" />
                            </div>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="الإشعارات" name="notifications">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="إشعارات البريد الإلكتروني">
                                    <el-switch v-model="form.email_notifications" active-text="مفعل" inactive-text="معطل" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="إشعارات SMS">
                                    <el-switch v-model="form.sms_notifications" active-text="مفعل" inactive-text="معطل" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="إشعارات Push">
                                    <el-switch v-model="form.push_notifications" active-text="مفعل" inactive-text="معطل" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="تنبيهات النظام الداخلية">
                                    <el-switch v-model="form.system_notifications" active-text="مفعل" inactive-text="معطل" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>
            </el-tabs>

            <div class="form-actions">
                <el-button type="primary" :loading="submitting" @click="submitSettings">
                    حفظ الإعدادات
                </el-button>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { ElMessage } from 'element-plus';

const settingsStore = useSettingsStore();
const activeTab = ref('general');
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
    site_name: '',
    site_tagline: '',
    site_description: '',
    show_site_name: true,
    show_product_price: true,
    default_currency: 'USD',
    default_language: 'ar',
    timezone: 'Asia/Riyadh',
    contact_phone: '',
    contact_whatsapp: '',
    contact_email: '',
    address: '',
    working_hours: '',
    facebook: '',
    instagram: '',
    twitter: '',
    youtube: '',
    linkedin: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    google_analytics: '',
    email_notifications: false,
    sms_notifications: false,
    push_notifications: false,
    system_notifications: true
});

const logoFile = ref(null);
const faviconFile = ref(null);
const ogImageFile = ref(null);
const logoPreview = ref('');
const faviconPreview = ref('');
const ogImagePreview = ref('');

const currencies = [
    { value: 'USD', label: 'دولار أمريكي - USD' },
    { value: 'EUR', label: 'يورو - EUR' },
    { value: 'SAR', label: 'ريال سعودي - SAR' },
    { value: 'SYP', label: 'ليرة سورية - SYP' },
    { value: 'AED', label: 'درهم إماراتي - AED' },
    { value: 'EGP', label: 'جنيه مصري - EGP' }
];

const languages = [
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'Français' }
];

const timezones = [
    { value: 'Asia/Riyadh', label: 'Asia/Riyadh' },
    { value: 'Asia/Dubai', label: 'Asia/Dubai' },
    { value: 'Asia/Amman', label: 'Asia/Amman' },
    { value: 'Africa/Cairo', label: 'Africa/Cairo' },
    { value: 'Europe/Istanbul', label: 'Europe/Istanbul' },
    { value: 'Europe/Paris', label: 'Europe/Paris' },
    { value: 'UTC', label: 'UTC' }
];

const normalizeBoolean = (value) => {
    return value === '1' || value === 1 || value === true || value === 'true';
};

const loadSettings = (settings) => {
    if (!settings || Object.keys(settings).length === 0) {
        return;
    }

    form.site_name = settings.site_name ?? '';
    form.site_tagline = settings.site_tagline ?? '';
    form.site_description = settings.site_description ?? '';
    form.show_site_name = normalizeBoolean(settings.show_site_name ?? '1');
    form.show_product_price = normalizeBoolean(settings.show_product_price ?? '1');
    form.default_currency = settings.default_currency || 'USD';
    form.default_language = settings.default_language || 'ar';
    form.timezone = settings.timezone || 'Asia/Riyadh';
    form.contact_phone = settings.contact_phone || '';
    form.contact_whatsapp = settings.contact_whatsapp || '';
    form.contact_email = settings.contact_email || '';
    form.address = settings.address || settings.contact_address || '';
    form.working_hours = settings.working_hours || '';
    form.facebook = settings.facebook || settings.contact_facebook || '';
    form.instagram = settings.instagram || settings.contact_instagram || '';
    form.twitter = settings.twitter || settings.contact_twitter || '';
    form.youtube = settings.youtube || settings.contact_youtube || '';
    form.linkedin = settings.linkedin || settings.contact_linkedin || '';
    form.meta_title = settings.meta_title || '';
    form.meta_description = settings.meta_description || '';
    form.meta_keywords = settings.meta_keywords || '';
    form.google_analytics = settings.google_analytics || '';
    form.email_notifications = normalizeBoolean(settings.email_notifications ?? '0');
    form.sms_notifications = normalizeBoolean(settings.sms_notifications ?? '0');
    form.push_notifications = normalizeBoolean(settings.push_notifications ?? '0');
    form.system_notifications = normalizeBoolean(settings.system_notifications ?? '1');

    logoPreview.value = settings.logo ? `/storage/${settings.logo}` : settings.site_logo ? `/storage/${settings.site_logo}` : '';
    faviconPreview.value = settings.favicon ? `/storage/${settings.favicon}` : settings.site_favicon ? `/storage/${settings.site_favicon}` : '';
    ogImagePreview.value = settings.og_image ? `/storage/${settings.og_image}` : '';
};

watch(
    () => settingsStore.data,
    (settings) => {
        loadSettings(settings);
    },
    { immediate: true }
);

const fetchSettings = async () => {
    try {
        await settingsStore.fetch();
    } catch (error) {
        ElMessage.error('حدث خطأ أثناء جلب إعدادات النظام');
    }
};

const onFileSelect = (event, field) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    if (!file.type.startsWith('image/')) {
        ElMessage.error('يمكن رفع صور فقط');
        return;
    }

    const maxSizeMB = 3;
    if (file.size / 1024 / 1024 > maxSizeMB) {
        ElMessage.error(`حجم الصورة يجب أن يكون أقل من ${maxSizeMB}MB`);
        return;
    }

    const previewUrl = URL.createObjectURL(file);

    if (field === 'logo') {
        logoFile.value = file;
        logoPreview.value = previewUrl;
    } else if (field === 'favicon') {
        faviconFile.value = file;
        faviconPreview.value = previewUrl;
    } else if (field === 'ogImage') {
        ogImageFile.value = file;
        ogImagePreview.value = previewUrl;
    }
};

const submitSettings = async () => {
    submitting.value = true;

    try {
        const formData = new FormData();

        Object.keys(form).forEach((key) => {
            const value = form[key];
            formData.append(`settings[${key}]`, value === true ? '1' : value === false ? '0' : value ?? '');
        });

        if (logoFile.value) {
            formData.append('logo', logoFile.value);
        }
        if (faviconFile.value) {
            formData.append('favicon', faviconFile.value);
        }
        if (ogImageFile.value) {
            formData.append('og_image', ogImageFile.value);
        }

        const response = await settingsStore.save(formData);

        if (response?.data?.success) {
            ElMessage.success('تم حفظ الإعدادات بنجاح');
            loadSettings(response.data.data.settings || settingsStore.data);
        } else {
            ElMessage.error(response?.data?.message || 'فشل حفظ الإعدادات');
        }
    } catch (error) {
        const message = error.response?.data?.message || 'فشل حفظ الإعدادات';
        ElMessage.error(message);
    } finally {
        submitting.value = false;
    }
};

onMounted(fetchSettings);
</script>

<style scoped>
.settings-page {
    padding: 0;
}

.card-header {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.card-header h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
}

.text-muted {
    color: #6b7280;
    font-size: 0.95rem;
}

.form-actions {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
}

.preview-image {
    margin-top: 1rem;
    max-width: 320px;
}

.preview-image img {
    width: 100%;
    height: auto;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.uploader-icon {
    font-size: 28px;
    color: #8c939d;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
