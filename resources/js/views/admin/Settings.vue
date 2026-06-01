<template>
    <div class="settings">
        <el-card shadow="hover">
            <template #header>
                <span>إعدادات النظام</span>
            </template>

            <el-tabs v-model="activeTab" type="border-card">
                <el-tab-pane label="الإعدادات العامة" name="general">
                    <el-form
                        ref="generalFormRef"
                        :model="generalSettings"
                        label-width="150px"
                        label-position="top"
                    >
                        <el-form-item label="اسم الموقع">
                            <el-input v-model="generalSettings.site_name" placeholder="أوان التكادوم" />
                        </el-form-item>

                        <el-form-item label="وصف الموقع">
                            <el-input
                                v-model="generalSettings.site_description"
                                type="textarea"
                                :rows="3"
                                placeholder="وصف الموقع"
                            />
                        </el-form-item>

                        <el-form-item label="الكلمات المفتاحية">
                            <el-input v-model="generalSettings.site_keywords" placeholder="keyword1, keyword2, keyword3" />
                        </el-form-item>

                        <el-form-item label="البريد الإلكتروني">
                            <el-input v-model="generalSettings.site_email" type="email" placeholder="info@example.com" />
                        </el-form-item>

                        <el-form-item label="رقم الهاتف">
                            <el-input v-model="generalSettings.site_phone" placeholder="+966 50 000 0000" />
                        </el-form-item>

                        <el-form-item label="العملة الافتراضية">
                            <el-select v-model="generalSettings.default_currency" style="width: 100%">
                                <el-option label="ريال سعودي" value="SAR" />
                                <el-option label="دولار أمريكي" value="USD" />
                                <el-option label="يورو" value="EUR" />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="اللغة الافتراضية">
                            <el-select v-model="generalSettings.default_language" style="width: 100%">
                                <el-option label="العربية" value="ar" />
                                <el-option label="English" value="en" />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="المنطقة الزمنية">
                            <el-select v-model="generalSettings.timezone" style="width: 100%">
                                <el-option label="Asia/Riyadh" value="Asia/Riyadh" />
                                <el-option label="UTC" value="UTC" />
                            </el-select>
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" :loading="saving" @click="saveGeneralSettings">
                                حفظ الإعدادات
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="الشعار والصور" name="logo">
                    <el-form label-width="150px" label-position="top">
                        <el-form-item label="شعار الموقع">
                            <el-upload
                                class="logo-uploader"
                                :action="uploadUrl"
                                :show-file-list="false"
                                :on-success="handleLogoSuccess"
                                :before-upload="beforeUpload"
                            >
                                <img v-if="logoSettings.site_logo" :src="logoSettings.site_logo" class="uploaded-logo" />
                                <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                            </el-upload>
                        </el-form-item>

                        <el-form-item label="أيقونة الموقع (Favicon)">
                            <el-upload
                                class="favicon-uploader"
                                :action="uploadUrl"
                                :show-file-list="false"
                                :on-success="handleFaviconSuccess"
                                :before-upload="beforeUpload"
                            >
                                <img v-if="logoSettings.site_favicon" :src="logoSettings.site_favicon" class="uploaded-favicon" />
                                <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                            </el-upload>
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" :loading="saving" @click="saveLogoSettings">
                                حفظ الصور
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="الإشعارات" name="notifications">
                    <el-form label-width="150px" label-position="top">
                        <el-form-item label="إشعارات البريد الإلكتروني">
                            <el-switch v-model="notificationSettings.email_enabled" />
                        </el-form-item>

                        <el-form-item label="إشعارات الطلبات">
                            <el-switch v-model="notificationSettings.order_notifications" />
                        </el-form-item>

                        <el-form-item label="إشعارات المخزون">
                            <el-switch v-model="notificationSettings.stock_alerts" />
                        </el-form-item>

                        <el-form-item label="إشعارات الاستفسارات">
                            <el-switch v-model="notificationSettings.inquiry_notifications" />
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" :loading="saving" @click="saveNotificationSettings">
                                حفظ الإعدادات
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';

const activeTab = ref('general');
const saving = ref(false);
const uploadUrl = '/api/upload';

const generalSettings = ref({
    site_name: 'أوان التكادوم',
    site_description: 'نظام إدارة متكامل',
    site_keywords: 'erp, management, system',
    site_email: 'info@awan.com',
    site_phone: '+966 50 000 0000',
    default_currency: 'SAR',
    default_language: 'ar',
    timezone: 'Asia/Riyadh'
});

const logoSettings = ref({
    site_logo: '',
    site_favicon: ''
});

const notificationSettings = ref({
    email_enabled: true,
    order_notifications: true,
    stock_alerts: true,
    inquiry_notifications: true
});

const beforeUpload = (file) => {
    const isImage = file.type.startsWith('image/');
    const isLt2M = file.size / 1024 / 1024 < 2;

    if (!isImage) {
        ElMessage.error('يمكن رفع صور فقط!');
        return false;
    }
    if (!isLt2M) {
        ElMessage.error('حجم الصورة يجب أن يكون أقل من 2MB!');
        return false;
    }
    return true;
};

const handleLogoSuccess = (response) => {
    logoSettings.value.site_logo = response.url;
    ElMessage.success('تم رفع الشعار بنجاح');
};

const handleFaviconSuccess = (response) => {
    logoSettings.value.site_favicon = response.url;
    ElMessage.success('تم رفع الأيقونة بنجاح');
};

const saveGeneralSettings = async () => {
    saving.value = true;
    await new Promise(resolve => setTimeout(resolve, 1000));
    ElMessage.success('تم حفظ الإعدادات العامة بنجاح');
    saving.value = false;
};

const saveLogoSettings = async () => {
    saving.value = true;
    await new Promise(resolve => setTimeout(resolve, 1000));
    ElMessage.success('تم حفظ الصور بنجاح');
    saving.value = false;
};

const saveNotificationSettings = async () => {
    saving.value = true;
    await new Promise(resolve => setTimeout(resolve, 1000));
    ElMessage.success('تم حفظ إعدادات الإشعارات بنجاح');
    saving.value = false;
};
</script>

<style scoped>
.settings {
    padding: 0;
}

.logo-uploader {
    width: 200px;
    height: 200px;
    border: 2px dashed #dcdfe6;
    border-radius: 8px;
    cursor: pointer;
    overflow: hidden;
    transition: border-color 0.3s ease;
}

.logo-uploader:hover {
    border-color: #409eff;
}

.uploaded-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.favicon-uploader {
    width: 64px;
    height: 64px;
    border: 2px dashed #dcdfe6;
    border-radius: 8px;
    cursor: pointer;
    overflow: hidden;
    transition: border-color 0.3s ease;
}

.favicon-uploader:hover {
    border-color: #409eff;
}

.uploaded-favicon {
    width: 100%;
    height: 100%;
    object-fit: contain;
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
