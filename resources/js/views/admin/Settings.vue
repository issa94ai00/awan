<template>
    <div class="settings-page">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('system_settings') }}</h2>
                        <p class="text-muted">{{ $t('full_control_over_site_settings') }}</p>
                    </div>
                </div>
            </template>

            <el-tabs v-model="activeTab" type="border-card">
                <el-tab-pane :label="$t('general')" name="general">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="generalLang" size="small">
                                <el-radio-button value="ar">العربية</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="generalLang === 'ar'">
                            <el-row :gutter="20">
                                <el-col :xs="24" :md="12">
                                    <el-form-item label="اسم الموقع">
                                        <el-input v-model="form.site_name" placeholder="أوان التقدم" />
                                    </el-form-item>
                                </el-col>

                                <el-col :xs="24" :md="12">
                                    <el-form-item label="الشعار الفرعي">
                                        <el-input v-model="form.site_tagline" placeholder="نبني معاً غد سورية الأجمل" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-form-item label="وصف الموقع">
                                <el-input
                                    type="textarea"
                                    :rows="4"
                                    v-model="form.site_description"
                                    placeholder="وصف مختصر للموقع..."
                                />
                            </el-form-item>
                        </template>

                        <template v-else>
                            <el-row :gutter="20">
                                <el-col :xs="24" :md="12">
                                    <el-form-item label="Site Name">
                                        <el-input v-model="form.site_name_en" placeholder="Awaan Al-Takadom" />
                                    </el-form-item>
                                </el-col>

                                <el-col :xs="24" :md="12">
                                    <el-form-item label="Site Tagline">
                                        <el-input v-model="form.site_tagline_en" placeholder="Building a better tomorrow" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-form-item label="Site Description">
                                <el-input
                                    type="textarea"
                                    :rows="4"
                                    v-model="form.site_description_en"
                                    placeholder="Brief site description..."
                                />
                            </el-form-item>
                        </template>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('display_the_site_name_in_the_logo')">
                                    <el-switch v-model="form.show_site_name" :active-text="$t('active')" :inactive-text="$t('inactive')" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('view_product_prices')">
                                    <el-switch v-model="form.show_product_price" :active-text="$t('activated')" :inactive-text="$t('disabled')" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>



                <el-tab-pane :label="$t('currency_and_language')" name="localization">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('virtual_currency')">
                                    <el-select v-model="form.default_currency" :placeholder="$t('select_currency')">
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
                                <el-form-item :label="$t('default_language')">
                                    <el-select v-model="form.default_language" :placeholder="$t('choose_language')">
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

                        <el-form-item :label="$t('time_zone')">
                            <el-select v-model="form.timezone" :placeholder="$t('choose_the_time_zone')">
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

                <el-tab-pane :label="$t('communication')" name="contact">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('phone')">
                                    <el-input v-model="form.contact_phone" placeholder="+201234567890" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('whatsapp_number')">
                                    <el-input v-model="form.contact_whatsapp" placeholder="+201234567890" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('email')">
                                    <el-input v-model="form.contact_email" type="email" placeholder="info@example.com" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('working_hours')">
                                    <el-input v-model="form.working_hours" :placeholder="$t('sunday_thursday_9_00_17_00')" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="$t('address')">
                            <el-input type="textarea" :rows="3" v-model="form.address" :placeholder="$t('the_full_address_of_the')" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane :label="$t('communication_means')" name="social">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('facebook')">
                                    <el-input v-model="form.facebook" placeholder="https://facebook.com/..." />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('instagram')">
                                    <el-input v-model="form.instagram" placeholder="https://instagram.com/..." />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('twitter_x')">
                                    <el-input v-model="form.twitter" placeholder="https://twitter.com/..." />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('youtube')">
                                    <el-input v-model="form.youtube" placeholder="https://youtube.com/..." />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="$t('linkedin')">
                            <el-input v-model="form.linkedin" placeholder="https://linkedin.com/..." />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="SEO" name="seo">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('site_title_meta_title')">
                                    <el-input v-model="form.meta_title" :placeholder="$t('website_address_for_search_engines')" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('meta_keywords')">
                                    <el-input v-model="form.meta_keywords" placeholder="erp, pos, erp system" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item :label="$t('site_description_meta_description')">
                            <el-input type="textarea" :rows="3" v-model="form.meta_description" :placeholder="$t('a_short_description_appears_in')" />
                        </el-form-item>

                        <el-form-item label="Google Analytics ID">
                            <el-input v-model="form.google_analytics" placeholder="G-XXXXXXXXXX" />
                        </el-form-item>

                        <el-form-item :label="$t('open_graph_image')">
                            <input type="file" accept="image/*" @change="onFileSelect($event, 'ogImage')" />
                            <div v-if="ogImagePreview" class="preview-image mt-3">
                                <img :src="ogImagePreview" alt="OG Image" />
                            </div>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane :label="$t('notifications')" name="notifications">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('email_notifications')">
                                    <el-switch v-model="form.email_notifications" :active-text="$t('activated')" :inactive-text="$t('disabled')" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('sms_notifications')">
                                    <el-switch v-model="form.sms_notifications" :active-text="$t('activated')" :inactive-text="$t('disabled')" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('push_notifications')">
                                    <el-switch v-model="form.push_notifications" :active-text="$t('activated')" :inactive-text="$t('disabled')" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('internal_system_alerts')">
                                    <el-switch v-model="form.system_notifications" :active-text="$t('activated')" :inactive-text="$t('disabled')" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane :label="$t('who_are_we')" name="about">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="aboutLang" size="small">
                                <el-radio-button value="ar">العربية</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="aboutLang === 'ar'">
                            <el-form-item label="عنوان الصفحة">
                                <el-input v-model="form.about_title" placeholder="من نحن" />
                            </el-form-item>

                            <el-form-item label="وصف الصفحة">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.about_description"
                                    placeholder="نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية."
                                />
                            </el-form-item>

                            <el-form-item label="قصتنا">
                                <el-input
                                    type="textarea"
                                    :rows="5"
                                    v-model="form.about_story"
                                    placeholder="قصة الشركة وتاريخها..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">القيم</el-divider>
                            <div class="values-editor-grid">
                                <el-card v-for="i in 5" :key="'ar-val-'+i" class="value-editor-card" shadow="hover">
                                    <template #header>
                                        <span class="value-card-title">القيمة {{ i }}</span>
                                    </template>
                                    <el-form-item label="العنوان">
                                        <el-input v-model="form[`about_value_${i}_title`]" :placeholder="`عنوان القيمة ${i}`" />
                                    </el-form-item>
                                    <el-form-item label="الوصف">
                                        <el-input type="textarea" :rows="2" v-model="form[`about_value_${i}_desc`]" :placeholder="`وصف القيمة ${i}`" />
                                    </el-form-item>
                                </el-card>
                            </div>

                            <el-form-item label="ما نقدمه">
                                <el-input
                                    type="textarea"
                                    :rows="4"
                                    v-model="form.about_services"
                                    placeholder="الخدمات والمنتجات..."
                                />
                            </el-form-item>
                        </template>

                        <template v-else>
                            <el-form-item label="Page Title">
                                <el-input v-model="form.about_title_en" placeholder="About Us" />
                            </el-form-item>

                            <el-form-item label="Page Description">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.about_description_en"
                                    placeholder="We at Awaan Al-Takadom provide building materials that combine global quality with modern design..."
                                />
                            </el-form-item>

                            <el-form-item label="Our Story">
                                <el-input
                                    type="textarea"
                                    :rows="5"
                                    v-model="form.about_story_en"
                                    placeholder="Company story and history..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">Values</el-divider>
                            <div class="values-editor-grid">
                                <el-card v-for="i in 5" :key="'en-val-'+i" class="value-editor-card" shadow="hover">
                                    <template #header>
                                        <span class="value-card-title">Value {{ i }}</span>
                                    </template>
                                    <el-form-item label="Title">
                                        <el-input v-model="form[`about_value_${i}_title_en`]" :placeholder="`Value ${i} title`" />
                                    </el-form-item>
                                    <el-form-item label="Description">
                                        <el-input type="textarea" :rows="2" v-model="form[`about_value_${i}_desc_en`]" :placeholder="`Value ${i} description`" />
                                    </el-form-item>
                                </el-card>
                            </div>

                            <el-form-item label="What We Offer">
                                <el-input
                                    type="textarea"
                                    :rows="4"
                                    v-model="form.about_services_en"
                                    placeholder="Services and products we provide..."
                                />
                            </el-form-item>
                        </template>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('years_of_experience')">
                                    <el-input-number v-model="form.about_years" :min="0" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('completed_projects')">
                                    <el-input-number v-model="form.about_projects" :min="0" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('happy_customers')">
                                    <el-input-number v-model="form.about_customers" :min="0" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('trusted_partners')">
                                    <el-input-number v-model="form.about_partners" :min="0" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane :label="$t('identity_and_vision')" name="vision">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="visionLang" size="small">
                                <el-radio-button value="ar">العربية</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="visionLang === 'ar'">
                            <el-form-item label="عنوان الصفحة">
                                <el-input v-model="form.vision_title" placeholder="الهوية والرؤية" />
                            </el-form-item>

                            <el-form-item label="وصف الصفحة">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_description"
                                    placeholder="نبذة مختصرة عن هوية الشركة..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">الميزة الأولى</el-divider>
                            <el-form-item label="عنوان الميزة الأولى">
                                <el-input v-model="form.vision_feature_1_title" placeholder="جودة عالمية" />
                            </el-form-item>
                            <el-form-item label="وصف الميزة الأولى">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_1_description"
                                    placeholder="وصف الميزة الأولى..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">الميزة الثانية</el-divider>
                            <el-form-item label="عنوان الميزة الثانية">
                                <el-input v-model="form.vision_feature_2_title" placeholder="تصميم عصري" />
                            </el-form-item>
                            <el-form-item label="وصف الميزة الثانية">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_2_description"
                                    placeholder="وصف الميزة الثانية..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">الميزة الثالثة</el-divider>
                            <el-form-item label="عنوان الميزة الثالثة">
                                <el-input v-model="form.vision_feature_3_title" placeholder="شراكة موثوقة" />
                            </el-form-item>
                            <el-form-item label="وصف الميزة الثالثة">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_3_description"
                                    placeholder="وصف الميزة الثالثة..."
                                />
                            </el-form-item>
                        </template>

                        <template v-else>
                            <el-form-item label="Page Title">
                                <el-input v-model="form.vision_title_en" placeholder="Identity & Vision" />
                            </el-form-item>

                            <el-form-item label="Page Description">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_description_en"
                                    placeholder="A brief description of the company's identity..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">First Advantage</el-divider>
                            <el-form-item label="First Feature Title">
                                <el-input v-model="form.vision_feature_1_title_en" placeholder="International Quality" />
                            </el-form-item>
                            <el-form-item label="First Feature Description">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_1_description_en"
                                    placeholder="Description of the first feature..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">Second Advantage</el-divider>
                            <el-form-item label="Second Feature Title">
                                <el-input v-model="form.vision_feature_2_title_en" placeholder="Modern Design" />
                            </el-form-item>
                            <el-form-item label="Second Feature Description">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_2_description_en"
                                    placeholder="Description of the second feature..."
                                />
                            </el-form-item>

                            <el-divider content-position="left">Third Advantage</el-divider>
                            <el-form-item label="Third Feature Title">
                                <el-input v-model="form.vision_feature_3_title_en" placeholder="Trusted Partnership" />
                            </el-form-item>
                            <el-form-item label="Third Feature Description">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_3_description_en"
                                    placeholder="Description of the third feature..."
                                />
                            </el-form-item>
                        </template>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane :label="$t('design')" name="design">
                    <el-form ref="formRef" :model="form" label-width="140px" label-position="top">
                        <el-divider content-position="left">{{ $t('basic_pictures_on_the_site') }}</el-divider>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('site_logo')">
                                    <input type="file" accept="image/*" @change="onFileSelect($event, 'logo')" />
                                    <div v-if="logoPreview" class="preview-image mt-3">
                                        <img :src="logoPreview" alt="Logo Preview" style="max-height: 80px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb; padding: 4px;" />
                                    </div>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('website_icon_favicon')">
                                    <input type="file" accept="image/*" @change="onFileSelect($event, 'favicon')" />
                                    <div v-if="faviconPreview" class="preview-image mt-3" style="max-width: 96px;">
                                        <img :src="faviconPreview" alt="Favicon Preview" style="max-height: 48px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb; padding: 4px;" />
                                    </div>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20" class="mt-3">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('image_share_link_open_graph_image')">
                                    <input type="file" accept="image/*" @change="onFileSelect($event, 'ogImage')" />
                                    <div v-if="ogImagePreview" class="preview-image mt-3">
                                        <img :src="ogImagePreview" alt="OG Image Preview" style="max-height: 100px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb;" />
                                    </div>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('main_banner_background_image_hero')">
                                    <input type="file" accept="image/*" @change="onFileSelect($event, 'heroBg')" />
                                    <div v-if="heroBgPreview" class="preview-image mt-3">
                                        <img :src="heroBgPreview" alt="Hero Background Preview" style="max-height: 100px; object-fit: contain; border-radius: 8px; border: 1px solid #e5e7eb;" />
                                    </div>
                                </el-form-item>
                            </el-col>
                        </el-row>


                        <el-divider content-position="left" class="mt-4">{{ $t('customized_color_palettes_and_style') }}</el-divider>
                        
                        <!-- 1. Color Palettes Selection -->
                        <div class="palette-picker-container mb-4">
                            <h4 class="mb-2 text-muted" style="font-weight: 600; font-size: 0.95rem;">{{ $t('professional_ready_made_palettes_one') }}</h4>
                            <div class="palettes-grid">
                                <div v-for="(p, idx) in colorPalettes" :key="idx" class="palette-card" @click="applyPalette(p)">
                                    <div class="palette-info">
                                        <span class="palette-name">{{ p.name }}</span>
                                    </div>
                                    <div class="palette-colors">
                                        <span class="color-stripe" :style="{ backgroundColor: p.primary }" :title="$t('basic')"></span>
                                        <span class="color-stripe" :style="{ backgroundColor: p.secondary }" :title="$t('secondary')"></span>
                                        <span class="color-stripe" :style="{ backgroundColor: p.accent }" :title="$t('distinguished')"></span>
                                        <span class="color-stripe" :style="{ backgroundColor: p.navbar_bg }" :title="$t('navbar')"></span>
                                        <span class="color-stripe" :style="{ backgroundColor: p.footer_bg }" :title="$t('the_footer')"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Dual Column: Editors vs. Live Preview Mockup -->
                        <el-row :gutter="24">
                            <!-- Left: The Color Customizer Tool -->
                            <el-col :xs="24" :lg="15">
                                <el-tabs type="border-card" class="color-tabs">
                                    
                                    <!-- TAB 1: General Brand Colors -->
                                    <el-tab-pane :label="$t('identity_and_brand_colors')">
                                        <div class="custom-color-item">
                                            <span class="color-label">{{ $t('primary_color') }}</span>
                                            <div class="color-control-wrapper">
                                                <el-color-picker v-model="form.theme_primary_color" />
                                                <el-input v-model="form.theme_primary_color" placeholder="var(--mobile-primary)" />
                                            </div>
                                            <div class="swatches-wrapper">
                                                <span v-for="c in primaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_primary_color = c"></span>
                                            </div>
                                        </div>

                                        <el-row :gutter="20">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('primary_light') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_primary_light_color" />
                                                        <el-input v-model="form.theme_primary_light_color" placeholder="var(--mobile-primary-light, var(--mobile-primary))" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryLightPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_primary_light_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('primary_dark') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_primary_dark_color" />
                                                        <el-input v-model="form.theme_primary_dark_color" placeholder="var(--mobile-primary-dark)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryDarkPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_primary_dark_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>

                                        <el-row :gutter="20" class="mt-3">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('secondary_color') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_secondary_color" />
                                                        <el-input v-model="form.theme_secondary_color" placeholder="#10b981" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in secondaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_secondary_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('secondary_light') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_secondary_light_color" />
                                                        <el-input v-model="form.theme_secondary_light_color" placeholder="#34d399" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in secondaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_secondary_light_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>

                                        <el-row :gutter="20" class="mt-3">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('accent_color') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_accent_color" />
                                                        <el-input v-model="form.theme_accent_color" placeholder="#f59e0b" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in secondaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_accent_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('accent_light') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_accent_light_color" />
                                                        <el-input v-model="form.theme_accent_light_color" placeholder="#fbbf24" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in secondaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_accent_light_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>
                                    </el-tab-pane>

                                    <!-- TAB 2: Navbar & Page Header -->
                                    <el-tab-pane :label="$t('menus_headers_header_nav')">
                                        <el-row :gutter="20">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('navigation_bar_background_navbar_bg') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_navbar_bg_color" />
                                                        <el-input v-model="form.theme_navbar_bg_color" placeholder="var(--mobile-primary-dark)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_navbar_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('navigation_bar_texts_and_buttons') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_navbar_text_color" />
                                                        <el-input v-model="form.theme_navbar_text_color" placeholder="#ffffff" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_navbar_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>

                                        <el-row :gutter="20" class="mt-3">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('scrolled_navbar_bg') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_navbar_scrolled_bg_color" />
                                                        <el-input v-model="form.theme_navbar_scrolled_bg_color" placeholder="var(--mobile-primary-dark)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_navbar_scrolled_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('scrolled_navbar_text') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_navbar_scrolled_text_color" />
                                                        <el-input v-model="form.theme_navbar_scrolled_text_color" placeholder="#ffffff" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_navbar_scrolled_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>

                                        <el-divider class="my-3" />

                                        <!-- Navbar Transparency -->
                                        <el-row :gutter="20">
                                            <el-col :span="24">
                                                <el-form-item :label="$t('navbar_transparency')">
                                                    <el-slider
                                                        v-model="form.theme_navbar_transparency"
                                                        :min="0"
                                                        :max="100"
                                                        :step="5"
                                                        show-input
                                                        :marks="{ 0: '0%', 50: '50%', 100: '100%' }"
                                                    />
                                                    <small class="text-muted">{{ $t('a_value_of_0_means') }}</small>
                                                </el-form-item>
                                            </el-col>
                                        </el-row>

                                        <el-divider class="my-3" />

                                        <!-- Page Header Colors -->
                                        <el-row :gutter="20">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('page_header_bg_background') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_page_header_bg_color" />
                                                        <el-input v-model="form.theme_page_header_bg_color" placeholder="linear-gradient(135deg, var(--mobile-primary-dark), #5a6b7a)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in gradientPresets" :key="c" class="swatch-circle" :style="{ background: c }" @click="form.theme_page_header_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('page_header_text_and_title') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_page_header_text_color" />
                                                        <el-input v-model="form.theme_page_header_text_color" placeholder="#ffffff" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_page_header_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>
                                    </el-tab-pane>

                                    <!-- TAB 3: Footer Colors -->
                                    <el-tab-pane :label="$t('footer')">
                                        <el-row :gutter="20">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('website_footer_background_footer_bg') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_footer_bg_color" />
                                                        <el-input v-model="form.theme_footer_bg_color" placeholder="var(--mobile-primary-dark)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryDarkPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_footer_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('website_footer_texts_and_links') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_footer_text_color" />
                                                        <el-input v-model="form.theme_footer_text_color" placeholder="#f8f9fa" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_footer_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>
                                    </el-tab-pane>

                                    <!-- TAB 4: Buttons & Cart Colors -->
                                    <el-tab-pane :label="$t('buttons_cart')">
                                        <!-- Hero Buttons -->
                                        <el-divider content-position="left">{{ $t('hero_buttons') }}</el-divider>
                                        <el-row :gutter="20">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('home_button_background_hero_primary_bg') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_hero_btn_bg_color" />
                                                        <el-input v-model="form.theme_hero_btn_bg_color" placeholder="linear-gradient(135deg, var(--mobile-primary), var(--mobile-primary-light, var(--mobile-primary)))" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in gradientPresets" :key="c" class="swatch-circle" :style="{ background: c }" @click="form.theme_hero_btn_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('hero_primary_text') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_hero_btn_text_color" />
                                                        <el-input v-model="form.theme_hero_btn_text_color" placeholder="#ffffff" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_hero_btn_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>

                                        <el-row :gutter="20" class="mt-3">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('secondary_button_background_hero_secondary') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_hero_btn_secondary_bg_color" />
                                                        <el-input v-model="form.theme_hero_btn_secondary_bg_color" placeholder="rgba(255, 255, 255, 0.1)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_hero_btn_secondary_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('hero_secondary_text') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_hero_btn_secondary_text_color" />
                                                        <el-input v-model="form.theme_hero_btn_secondary_text_color" placeholder="var(--mobile-primary)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_hero_btn_secondary_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>

                                        <!-- Cart Buttons -->
                                        <el-divider content-position="left" class="mt-4">{{ $t('cart_buttons') }}</el-divider>
                                        <el-row :gutter="20">
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('cart_bg_button_background') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_cart_btn_bg_color" />
                                                        <el-input v-model="form.theme_cart_btn_bg_color" placeholder="var(--mobile-primary)" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in primaryPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_cart_btn_bg_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                            <el-col :span="12">
                                                <div class="custom-color-item">
                                                    <span class="color-label">{{ $t('cart_text') }}</span>
                                                    <div class="color-control-wrapper">
                                                        <el-color-picker v-model="form.theme_cart_btn_text_color" />
                                                        <el-input v-model="form.theme_cart_btn_text_color" placeholder="#ffffff" />
                                                    </div>
                                                    <div class="swatches-wrapper">
                                                        <span v-for="c in textPresets" :key="c" class="swatch-circle" :style="{ backgroundColor: c }" @click="form.theme_cart_btn_text_color = c"></span>
                                                    </div>
                                                </div>
                                            </el-col>
                                        </el-row>
                                    </el-tab-pane>

                                </el-tabs>
                            </el-col>

                            <!-- Right: The Live Storefront Mockup Preview -->
                            <el-col :xs="24" :lg="9">
                                <div class="mockup-preview-card">
                                    <div class="mockup-header">
                                        <span class="mockup-dot red"></span>
                                        <span class="mockup-dot yellow"></span>
                                        <span class="mockup-dot green"></span>
                                        <span class="mockup-title">{{ $t('live_storefront_preview') }}</span>
                                    </div>
                                    
                                    <!-- Navbar Mockup -->
                                    <div class="mockup-sub-title">{{ $t('navigation_bar_default') }}</div>
                                    <div class="mock-nav" :style="{ backgroundColor: form.theme_navbar_bg_color || 'var(--mobile-primary-dark)', color: form.theme_navbar_text_color || '#ffffff' }">
                                        <span class="mock-logo-text" :style="{ color: form.theme_navbar_text_color || '#ffffff' }">{{ $t('it_s_time_for_takadum') }}</span>
                                        <div class="mock-nav-links">
                                            <span class="mock-nav-link active" :style="{ color: form.theme_navbar_text_color || '#ffffff' }">{{ $t('home') }}</span>
                                            <span class="mock-nav-link" :style="{ color: form.theme_navbar_text_color || '#ffffff', opacity: 0.7 }">{{ $t('products') }}</span>
                                        </div>
                                    </div>

                                    <div class="mockup-sub-title mt-2">{{ $t('navigation_bar_on_scroll') }}</div>
                                    <div class="mock-nav mock-nav-scrolled" :style="{ backgroundColor: form.theme_navbar_scrolled_bg_color || form.theme_navbar_bg_color || 'var(--mobile-primary-dark)', color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff' }">
                                        <span class="mock-logo-text" :style="{ color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff' }">{{ $t('it_s_time_for_takadum') }}</span>
                                        <div class="mock-nav-links">
                                            <span class="mock-nav-link active" :style="{ color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff' }">{{ $t('home') }}</span>
                                            <span class="mock-nav-link" :style="{ color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff', opacity: 0.7 }">{{ $t('products') }}</span>
                                        </div>
                                    </div>

                                    <!-- Hero Banner Mockup -->
                                    <div class="mockup-sub-title mt-2">{{ $t('main_banner_hero_section') }}</div>
                                    <div class="mock-hero" :style="{ background: 'linear-gradient(135deg, rgba(13,27,42,0.85), rgba(22,42,69,0.9))', padding: '12px 14px', borderRadius: '4px', textAlign: 'center' }">
                                        <div class="mock-hero-title" style="font-size: 0.75rem; color: #fff; font-weight: 700; margin-bottom: 6px;">{{ $t('original_spare_parts_with_real_warranty') }}</div>
                                        <div class="mock-hero-buttons" style="display: flex; gap: 6px; justify-content: center;">
                                            <button class="mock-btn mock-hero-btn-primary" :style="{ background: form.theme_hero_btn_bg_color || form.theme_primary_color || 'var(--mobile-primary)', color: form.theme_hero_btn_text_color || '#ffffff', border: 'none', padding: '4px 10px', fontSize: '0.65rem', borderRadius: form.theme_border_radius, fontWeight: 'bold' }">
                                                {{ $t('browse_products') }}
                                            </button>
                                            <button class="mock-btn mock-hero-btn-secondary" :style="{ background: form.theme_hero_btn_secondary_bg_color || 'rgba(255, 255, 255, 0.1)', color: form.theme_hero_btn_secondary_text_color || '#ffffff', border: '1px solid ' + (form.theme_hero_btn_secondary_text_color || 'rgba(255,255,255,0.4)'), padding: '4px 10px', fontSize: '0.65rem', borderRadius: form.theme_border_radius, fontWeight: 'bold' }">
                                                {{ $t('contact_us') }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Page Header Mockup -->
                                    <div class="mock-page-header" :style="{ background: form.theme_page_header_bg_color || 'linear-gradient(135deg, var(--mobile-primary-dark), #5a6b7a)', color: form.theme_page_header_text_color || '#ffffff' }">
                                        <div class="mock-page-title" :style="{ color: form.theme_page_header_text_color || '#ffffff' }">{{ $t('spare_parts_department') }}</div>
                                        <div class="mock-breadcrumb" :style="{ color: form.theme_page_header_text_color || '#ffffff', opacity: 0.8 }">{{ $t('home_product_classification') }}</div>
                                    </div>

                                    <!-- Body & Product Card Mockup -->
                                    <div class="mock-body">
                                        <div class="mock-product-card" :style="{ borderRadius: form.theme_border_radius }">
                                            <div class="mock-product-image">
                                                <span class="mock-badge" :style="{ backgroundColor: form.theme_accent_color || '#f59e0b', color: '#ffffff' }">{{ $t('new') }}</span>
                                            </div>
                                            <div class="mock-product-details">
                                                <div class="mock-product-name">{{ $t('front_brake_pads_set') }}</div>
                                                <div class="mock-product-price" :style="{ color: form.theme_primary_color || 'var(--mobile-primary)' }">{{ $t('240_sar') }}</div>
                                                <button class="mock-btn" :style="{ backgroundColor: form.theme_cart_btn_bg_color || form.theme_primary_color || 'var(--mobile-primary)', color: form.theme_cart_btn_text_color || '#ffffff', borderRadius: form.theme_border_radius }">
                                                    {{ $t('add_to_cart') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer Mockup -->
                                    <div class="mock-footer" :style="{ backgroundColor: form.theme_footer_bg_color || 'var(--mobile-primary-dark)', color: form.theme_footer_text_color || '#f8f9fa' }">
                                        <div class="mock-footer-content" :style="{ color: form.theme_footer_text_color || '#f8f9fa' }">
                                            <span>{{ $t('2026_awan_takadum_all_rights_reserved') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </el-col>
                        </el-row>

                        <el-divider content-position="left" class="mt-4">{{ $t('lines_and_structural_patterns') }}</el-divider>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('default_site_font_font_family')">
                                    <el-select v-model="form.theme_font_family" :placeholder="$t('choose_font')" style="width: 100%;">
                                        <el-option :label="$t('tajawal_balanced_and_elegant_line')" value="Tajawal" />
                                        <el-option :label="$t('cairo_modern_and_legible_font')" value="Cairo" />
                                        <el-option :label="$t('readex_pro_modern_geometric_font')" value="Readex Pro" />
                                        <el-option :label="$t('el_messiri_artistic_and_decorative')" value="El Messiri" />
                                        <el-option :label="$t('almarai_soft_and_smooth_line')" value="Almarai" />
                                        <el-option :label="$t('outfit_modern_latin_terminology')" value="Outfit" />
                                        <el-option :label="$t('inter_default_clean_latin_font')" value="Inter" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('edges_of_items_and_buttons_border_radius')">
                                    <el-select v-model="form.theme_border_radius" :placeholder="$t('choose_the_shape_of_the_edges')" style="width: 100%;">
                                        <el-option :label="$t('very_sharp_sharp_0px')" value="0px" />
                                        <el-option :label="$t('rounded_8px')" value="8px" />
                                        <el-option :label="$t('extra_rounded_14px')" value="14px" />
                                        <el-option :label="$t('fully_oval_pill_30px')" value="30px" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-divider content-position="left" class="mt-4">{{ $t('customize_the_main_banner_hero_section') }}</el-divider>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('banner_text_alignment')">
                                    <el-radio-group v-model="form.theme_hero_align">
                                        <el-radio-button value="right">{{ $t('right_for_arabic') }}</el-radio-button>
                                        <el-radio-button value="center">{{ $t('medium_balanced') }}</el-radio-button>
                                        <el-radio-button value="left">{{ $t('left_for_english') }}</el-radio-button>
                                    </el-radio-group>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('overlay_opacity')">
                                    <el-slider v-model="form.theme_hero_overlay_opacity" :min="0.1" :max="0.9" :step="0.05" show-input style="width: 90%; margin: 0 auto;" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-divider content-position="left" class="mt-4">{{ $t('customize_site_footer') }}</el-divider>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="24">
                                <el-form-item :label="$t('footer_layout_formatting')">
                                    <el-radio-group v-model="form.theme_footer_layout">
                                        <el-radio value="multicolumn">{{ $t('multiple_columns_quick_links_contact') }}</el-radio>
                                        <el-radio value="simple">{{ $t('simple_simplified_description_with_medium') }}</el-radio>
                                        <el-radio value="modern">{{ $t('modern_and_simplified_social_media') }}</el-radio>
                                    </el-radio-group>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-divider content-position="left" class="mt-4">{{ $t('custom_css_codes_custom_stylesheet') }}</el-divider>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="24">
                                <el-form-item :label="$t('customize_design_via_code_custom_css')">
                                    <el-input
                                        type="textarea"
                                        :rows="6"
                                        v-model="form.theme_custom_css"
                                        :placeholder="$t('add_custom_css_codes_here')"
                                        style="font-family: monospace;"
                                    />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </el-tab-pane>
            </el-tabs>

            <div class="form-actions">
                <el-button type="primary" :loading="submitting" @click="submitSettings">
                    {{ $t('save_settings') }}
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
const generalLang = ref('ar');
const aboutLang = ref('ar');
const visionLang = ref('ar');
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
    site_name: '',
    site_name_en: '',
    site_tagline: '',
    site_tagline_en: '',
    site_description: '',
    site_description_en: '',
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
    system_notifications: true,
    about_title: '',
    about_title_en: '',
    about_description: '',
    about_description_en: '',
    about_story: '',
    about_story_en: '',
    about_values: '',
    about_values_en: '',
    about_services: '',
    about_services_en: '',
    about_value_1_title: '',
    about_value_1_title_en: '',
    about_value_1_desc: '',
    about_value_1_desc_en: '',
    about_value_2_title: '',
    about_value_2_title_en: '',
    about_value_2_desc: '',
    about_value_2_desc_en: '',
    about_value_3_title: '',
    about_value_3_title_en: '',
    about_value_3_desc: '',
    about_value_3_desc_en: '',
    about_value_4_title: '',
    about_value_4_title_en: '',
    about_value_4_desc: '',
    about_value_4_desc_en: '',
    about_value_5_title: '',
    about_value_5_title_en: '',
    about_value_5_desc: '',
    about_value_5_desc_en: '',
    about_years: 0,
    about_projects: 0,
    about_customers: 0,
    about_partners: 0,
    vision_title: '',
    vision_title_en: '',
    vision_description: '',
    vision_description_en: '',
    vision_feature_1_title: '',
    vision_feature_1_title_en: '',
    vision_feature_1_description: '',
    vision_feature_1_description_en: '',
    vision_feature_2_title: '',
    vision_feature_2_title_en: '',
    vision_feature_2_description: '',
    vision_feature_2_description_en: '',
    vision_feature_3_title: '',
    vision_feature_3_title_en: '',
    vision_feature_3_description: '',
    vision_feature_3_description_en: '',
    theme_primary_color: '',
    theme_primary_light_color: '',
    theme_primary_dark_color: '',
    theme_secondary_color: '',
    theme_secondary_light_color: '',
    theme_accent_color: '',
    theme_accent_light_color: '',
    theme_font_family: 'Cairo',
    theme_border_radius: '14px',
    theme_hero_align: 'center',
    theme_hero_overlay_opacity: '0.5',
    theme_footer_layout: 'multicolumn',
    theme_custom_css: '',
    theme_navbar_bg_color: '',
    theme_navbar_text_color: '',
    theme_navbar_scrolled_bg_color: '',
    theme_navbar_scrolled_text_color: '',
    theme_navbar_transparency: 25,
    theme_hero_btn_bg_color: '',
    theme_hero_btn_text_color: '',
    theme_hero_btn_secondary_bg_color: '',
    theme_hero_btn_secondary_text_color: '',
    theme_cart_btn_bg_color: '',
    theme_cart_btn_text_color: '',
    theme_footer_bg_color: '',
    theme_footer_text_color: '',
    theme_page_header_bg_color: '',
    theme_page_header_text_color: ''
});

const colorPalettes = [
    {
        name: window.t('luxurious_classic_green'),
        primary: '#1E3A0F',
        primary_light: '#2D5016',
        primary_dark: '#0F1F08',
        secondary: '#10b981',
        secondary_light: '#34d399',
        accent: '#f59e0b',
        accent_light: '#fbbf24',
        navbar_bg: '#1E3A0F',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#0F1F08',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #1E3A0F, #2D5016)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#1E3A0F',
        cart_btn_bg: '#1E3A0F',
        cart_btn_text: '#ffffff',
        footer_bg: '#0F1F08',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #0F1F08, #2D5016)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('modern_royal_blue'),
        primary: '#1e3a8a',
        primary_light: '#3b82f6',
        primary_dark: '#1e1b4b',
        secondary: '#06b6d4',
        secondary_light: '#67e8f9',
        accent: '#f59e0b',
        accent_light: '#fbbf24',
        navbar_bg: '#1e3a8a',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#1e3a8a',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #1e3a8a, #3b82f6)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#1e3a8a',
        cart_btn_bg: '#1e3a8a',
        cart_btn_text: '#ffffff',
        footer_bg: '#1e1b4b',
        footer_text: '#e2e8f0',
        page_header_bg: 'linear-gradient(135deg, #1e3a8a, #3b82f6)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('luxurious_gold_and_black'),
        primary: '#171717',
        primary_light: '#262626',
        primary_dark: '#0a0a0a',
        secondary: '#d4af37',
        secondary_light: '#f3e5ab',
        accent: '#d4af37',
        accent_light: '#f3e5ab',
        navbar_bg: '#171717',
        navbar_text: '#d4af37',
        navbar_scrolled_bg: '#171717',
        navbar_scrolled_text: '#d4af37',
        hero_btn_bg: 'linear-gradient(135deg, #d4af37, #f3e5ab)',
        hero_btn_text: '#171717',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#d4af37',
        cart_btn_bg: '#171717',
        cart_btn_text: '#d4af37',
        footer_bg: '#0a0a0a',
        footer_text: '#f5f5f5',
        page_header_bg: 'linear-gradient(135deg, #171717, #262626)',
        page_header_text: '#d4af37'
    },
    {
        name: window.t('elegant_burgundy_and_gold'),
        primary: '#7f1d1d',
        primary_light: '#b91c1c',
        primary_dark: '#450a0a',
        secondary: '#f59e0b',
        secondary_light: '#fbbf24',
        accent: '#f59e0b',
        accent_light: '#fbbf24',
        navbar_bg: '#7f1d1d',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#7f1d1d',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #7f1d1d, #b91c1c)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#7f1d1d',
        cart_btn_bg: '#7f1d1d',
        cart_btn_text: '#ffffff',
        footer_bg: '#450a0a',
        footer_text: '#f9fafb',
        page_header_bg: 'linear-gradient(135deg, #7f1d1d, #b91c1c)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('bold_purple_and_pink'),
        primary: '#4c1d95',
        primary_light: '#6d28d9',
        primary_dark: '#2e1065',
        secondary: '#ec4899',
        secondary_light: '#f472b6',
        accent: '#ec4899',
        accent_light: '#f472b6',
        navbar_bg: '#4c1d95',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#4c1d95',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #4c1d95, #6d28d9)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#4c1d95',
        cart_btn_bg: '#4c1d95',
        cart_btn_text: '#ffffff',
        footer_bg: '#2e1065',
        footer_text: '#f9fafb',
        page_header_bg: 'linear-gradient(135deg, #4c1d95, #6d28d9)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('modern_technology'),
        primary: '#0ea5e9',
        primary_light: '#38bdf8',
        primary_dark: '#0369a1',
        secondary: '#10b981',
        secondary_light: '#34d399',
        accent: '#f59e0b',
        accent_light: '#fbbf24',
        navbar_bg: '#0ea5e9',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#0ea5e9',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #0ea5e9, #38bdf8)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#0ea5e9',
        cart_btn_bg: '#0ea5e9',
        cart_btn_text: '#ffffff',
        footer_bg: '#0369a1',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #0ea5e9, #38bdf8)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('nature_calm'),
        primary: '#059669',
        primary_light: '#10b981',
        primary_dark: '#065f46',
        secondary: '#84cc16',
        secondary_light: '#a3e635',
        accent: '#f97316',
        accent_light: '#fb923c',
        navbar_bg: '#059669',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#059669',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #059669, #10b981)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#059669',
        cart_btn_bg: '#059669',
        cart_btn_text: '#ffffff',
        footer_bg: '#065f46',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #059669, #10b981)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('elegant_gray'),
        primary: '#374151',
        primary_light: '#4b5563',
        primary_dark: '#1f2937',
        secondary: '#6b7280',
        secondary_light: '#9ca3af',
        accent: '#3b82f6',
        accent_light: '#60a5fa',
        navbar_bg: '#374151',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#374151',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #374151, #4b5563)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#374151',
        cart_btn_bg: '#374151',
        cart_btn_text: '#ffffff',
        footer_bg: '#1f2937',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #374151, #4b5563)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('warm_orange'),
        primary: '#ea580c',
        primary_light: '#f97316',
        primary_dark: '#c2410c',
        secondary: '#fbbf24',
        secondary_light: '#fcd34d',
        accent: '#dc2626',
        accent_light: '#ef4444',
        navbar_bg: '#ea580c',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#ea580c',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #ea580c, #f97316)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#ea580c',
        cart_btn_bg: '#ea580c',
        cart_btn_text: '#ffffff',
        footer_bg: '#c2410c',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #ea580c, #f97316)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('cool_turquoise'),
        primary: '#0891b2',
        primary_light: '#06b6d4',
        primary_dark: '#0e7490',
        secondary: '#14b8a6',
        secondary_light: '#2dd4bf',
        accent: '#8b5cf6',
        accent_light: '#a78bfa',
        navbar_bg: '#0891b2',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#0891b2',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #0891b2, #06b6d4)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#0891b2',
        cart_btn_bg: '#0891b2',
        cart_btn_text: '#ffffff',
        footer_bg: '#0e7490',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #0891b2, #06b6d4)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('soft_pink'),
        primary: '#db2777',
        primary_light: '#ec4899',
        primary_dark: '#be185d',
        secondary: '#f472b6',
        secondary_light: '#f9a8d4',
        accent: '#8b5cf6',
        accent_light: '#a78bfa',
        navbar_bg: '#db2777',
        navbar_text: '#ffffff',
        navbar_scrolled_bg: '#db2777',
        navbar_scrolled_text: '#ffffff',
        hero_btn_bg: 'linear-gradient(135deg, #db2777, #ec4899)',
        hero_btn_text: '#ffffff',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#db2777',
        cart_btn_bg: '#db2777',
        cart_btn_text: '#ffffff',
        footer_bg: '#be185d',
        footer_text: '#f8f9fa',
        page_header_bg: 'linear-gradient(135deg, #db2777, #ec4899)',
        page_header_text: '#ffffff'
    },
    {
        name: window.t('black_gold'),
        primary: '#000000',
        primary_light: '#1a1a1a',
        primary_dark: '#000000',
        secondary: '#d4af37',
        secondary_light: '#f3e5ab',
        accent: '#d4af37',
        accent_light: '#f3e5ab',
        navbar_bg: '#000000',
        navbar_text: '#d4af37',
        navbar_scrolled_bg: '#000000',
        navbar_scrolled_text: '#d4af37',
        hero_btn_bg: 'linear-gradient(135deg, #d4af37, #f3e5ab)',
        hero_btn_text: '#000000',
        hero_btn_secondary_bg: 'rgba(255, 255, 255, 0.1)',
        hero_btn_secondary_text: '#d4af37',
        cart_btn_bg: '#000000',
        cart_btn_text: '#d4af37',
        footer_bg: '#000000',
        footer_text: '#f5f5f5',
        page_header_bg: 'linear-gradient(135deg, #000000, #1a1a1a)',
        page_header_text: '#d4af37'
    }
];

const applyPalette = (palette) => {
    form.theme_primary_color = palette.primary;
    form.theme_primary_light_color = palette.primary_light;
    form.theme_primary_dark_color = palette.primary_dark;
    form.theme_secondary_color = palette.secondary;
    form.theme_secondary_light_color = palette.secondary_light;
    form.theme_accent_color = palette.accent;
    form.theme_accent_light_color = palette.accent_light;
    form.theme_navbar_bg_color = palette.navbar_bg;
    form.theme_navbar_text_color = palette.navbar_text;
    form.theme_navbar_scrolled_bg_color = palette.navbar_scrolled_bg || palette.navbar_bg;
    form.theme_navbar_scrolled_text_color = palette.navbar_scrolled_text || palette.navbar_text;
    form.theme_hero_btn_bg_color = palette.hero_btn_bg || palette.primary;
    form.theme_hero_btn_text_color = palette.hero_btn_text || '#ffffff';
    form.theme_hero_btn_secondary_bg_color = palette.hero_btn_secondary_bg || 'rgba(255, 255, 255, 0.1)';
    form.theme_hero_btn_secondary_text_color = palette.hero_btn_secondary_text || palette.primary;
    form.theme_cart_btn_bg_color = palette.cart_btn_bg || palette.primary;
    form.theme_cart_btn_text_color = palette.cart_btn_text || '#ffffff';
    form.theme_footer_bg_color = palette.footer_bg;
    form.theme_footer_text_color = palette.footer_text;
    form.theme_page_header_bg_color = palette.page_header_bg;
    form.theme_page_header_text_color = palette.page_header_text;
};

const primaryPresets = ['var(--mobile-primary)', '#1e3a8a', '#171717', '#7f1d1d', '#4c1d95', '#0f172a', '#0284c7'];
const primaryLightPresets = ['var(--mobile-primary-light, var(--mobile-primary))', '#3b82f6', '#262626', '#b91c1c', '#6d28d9', '#334155', '#38bdf8'];
const primaryDarkPresets = ['var(--mobile-primary-dark)', '#1e1b4b', '#0a0a0a', '#450a0a', '#2e1065', '#020617', '#0369a1'];
const secondaryPresets = ['#10b981', '#06b6d4', '#d4af37', '#ec4899', '#f97316', '#84cc16', '#64748b'];
const textPresets = ['#ffffff', '#f8f9fa', '#f3e5ab', '#e2e8f0', '#d4af37', '#1e293b', '#0f172a'];
const gradientPresets = [
    'linear-gradient(135deg, var(--mobile-primary-dark), #5a6b7a)',
    'linear-gradient(135deg, #1e3a8a, #3b82f6)',
    'linear-gradient(135deg, #171717, #525252)',
    'linear-gradient(135deg, #7f1d1d, #f59e0b)',
    'linear-gradient(135deg, #4c1d95, #ec4899)',
    'var(--mobile-primary)',
    '#1e3a8a'
];

const logoFile = ref(null);
const faviconFile = ref(null);
const ogImageFile = ref(null);
const heroBgFile = ref(null);
const logoPreview = ref('');
const faviconPreview = ref('');
const ogImagePreview = ref('');
const heroBgPreview = ref('');

const currencies = [
    { value: 'USD', label: window.t('us_dollar_usd')},
    { value: 'EUR', label: window.t('euro_eur')},
    { value: 'SAR', label: window.t('saudi_riyal_sar')},
    { value: 'SYP', label: window.t('syrian_pound_syp')},
    { value: 'AED', label: window.t('emirati_dirham_aed')},
    { value: 'EGP', label: window.t('egyptian_pound_egp')}
];

const languages = [
    { value: 'ar', label: window.t('arabic')},
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
    form.site_name_en = settings.site_name_en ?? '';
    form.site_tagline = settings.site_tagline ?? '';
    form.site_tagline_en = settings.site_tagline_en ?? '';
    form.site_description = settings.site_description ?? '';
    form.site_description_en = settings.site_description_en ?? '';
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
    form.about_title = settings.about_title || '';
    form.about_title_en = settings.about_title_en || '';
    form.about_description = settings.about_description || '';
    form.about_description_en = settings.about_description_en || '';
    form.about_story = settings.about_story || '';
    form.about_story_en = settings.about_story_en || '';
    form.about_values = settings.about_values || '';
    form.about_values_en = settings.about_values_en || '';
    form.about_services = settings.about_services || '';
    form.about_services_en = settings.about_services_en || '';
    form.about_value_1_title = settings.about_value_1_title || '';
    form.about_value_1_title_en = settings.about_value_1_title_en || '';
    form.about_value_1_desc = settings.about_value_1_desc || '';
    form.about_value_1_desc_en = settings.about_value_1_desc_en || '';
    form.about_value_2_title = settings.about_value_2_title || '';
    form.about_value_2_title_en = settings.about_value_2_title_en || '';
    form.about_value_2_desc = settings.about_value_2_desc || '';
    form.about_value_2_desc_en = settings.about_value_2_desc_en || '';
    form.about_value_3_title = settings.about_value_3_title || '';
    form.about_value_3_title_en = settings.about_value_3_title_en || '';
    form.about_value_3_desc = settings.about_value_3_desc || '';
    form.about_value_3_desc_en = settings.about_value_3_desc_en || '';
    form.about_value_4_title = settings.about_value_4_title || '';
    form.about_value_4_title_en = settings.about_value_4_title_en || '';
    form.about_value_4_desc = settings.about_value_4_desc || '';
    form.about_value_4_desc_en = settings.about_value_4_desc_en || '';
    form.about_value_5_title = settings.about_value_5_title || '';
    form.about_value_5_title_en = settings.about_value_5_title_en || '';
    form.about_value_5_desc = settings.about_value_5_desc || '';
    form.about_value_5_desc_en = settings.about_value_5_desc_en || '';
    form.about_years = parseInt(settings.about_years) || 0;
    form.about_projects = parseInt(settings.about_projects) || 0;
    form.about_customers = parseInt(settings.about_customers) || 0;
    form.about_partners = parseInt(settings.about_partners) || 0;
    form.vision_title = settings.vision_title || '';
    form.vision_title_en = settings.vision_title_en || '';
    form.vision_description = settings.vision_description || '';
    form.vision_description_en = settings.vision_description_en || '';
    form.vision_feature_1_title = settings.vision_feature_1_title || '';
    form.vision_feature_1_title_en = settings.vision_feature_1_title_en || '';
    form.vision_feature_1_description = settings.vision_feature_1_description || '';
    form.vision_feature_1_description_en = settings.vision_feature_1_description_en || '';
    form.vision_feature_2_title = settings.vision_feature_2_title || '';
    form.vision_feature_2_title_en = settings.vision_feature_2_title_en || '';
    form.vision_feature_2_description = settings.vision_feature_2_description || '';
    form.vision_feature_2_description_en = settings.vision_feature_2_description_en || '';
    form.vision_feature_3_title = settings.vision_feature_3_title || '';
    form.vision_feature_3_title_en = settings.vision_feature_3_title_en || '';
    form.vision_feature_3_description = settings.vision_feature_3_description || '';
    form.vision_feature_3_description_en = settings.vision_feature_3_description_en || '';

    form.theme_primary_color = settings.theme_primary_color || '';
    form.theme_primary_light_color = settings.theme_primary_light_color || '';
    form.theme_primary_dark_color = settings.theme_primary_dark_color || '';
    form.theme_secondary_color = settings.theme_secondary_color || '';
    form.theme_secondary_light_color = settings.theme_secondary_light_color || '';
    form.theme_accent_color = settings.theme_accent_color || '';
    form.theme_accent_light_color = settings.theme_accent_light_color || '';
    form.theme_font_family = settings.theme_font_family || 'Cairo';
    form.theme_border_radius = settings.theme_border_radius || '14px';
    form.theme_hero_align = settings.theme_hero_align || 'center';
    form.theme_hero_overlay_opacity = settings.theme_hero_overlay_opacity || '0.5';
    form.theme_footer_layout = settings.theme_footer_layout || 'multicolumn';
    form.theme_custom_css = settings.theme_custom_css || '';
    form.theme_navbar_bg_color = settings.theme_navbar_bg_color || '';
    form.theme_navbar_text_color = settings.theme_navbar_text_color || '';
    form.theme_navbar_scrolled_bg_color = settings.theme_navbar_scrolled_bg_color || '';
    form.theme_navbar_scrolled_text_color = settings.theme_navbar_scrolled_text_color || '';
    form.theme_hero_btn_bg_color = settings.theme_hero_btn_bg_color || '';
    form.theme_hero_btn_text_color = settings.theme_hero_btn_text_color || '';
    form.theme_hero_btn_secondary_bg_color = settings.theme_hero_btn_secondary_bg_color || '';
    form.theme_hero_btn_secondary_text_color = settings.theme_hero_btn_secondary_text_color || '';
    form.theme_cart_btn_bg_color = settings.theme_cart_btn_bg_color || '';
    form.theme_cart_btn_text_color = settings.theme_cart_btn_text_color || '';
    form.theme_footer_bg_color = settings.theme_footer_bg_color || '';
    form.theme_footer_text_color = settings.theme_footer_text_color || '';
    form.theme_page_header_bg_color = settings.theme_page_header_bg_color || '';
    form.theme_page_header_text_color = settings.theme_page_header_text_color || '';

    const getPreviewUrl = (value) => {
        if (!value) return '';
        return value.startsWith('assets/') ? `/${value}` : `/storage/${value}`;
    };

    logoPreview.value = settings.logo ? getPreviewUrl(settings.logo) : getPreviewUrl(settings.site_logo);
    faviconPreview.value = settings.favicon ? getPreviewUrl(settings.favicon) : getPreviewUrl(settings.site_favicon);
    ogImagePreview.value = getPreviewUrl(settings.og_image);
    heroBgPreview.value = getPreviewUrl(settings.hero_bg);
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
        ElMessage.error(window.t('an_error_occurred_while_fetching'));
    }
};

const onFileSelect = (event, field) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    if (!file.type.startsWith('image/')) {
        ElMessage.error(window.t('only_photos_can_be_uploaded'));
        return;
    }

    const maxSizeMB = 3;
    if (file.size / 1024 / 1024 > maxSizeMB) {
        ElMessage.error(window.t('image_size_limit', { maxSize: maxSizeMB }));
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
    } else if (field === 'heroBg') {
        heroBgFile.value = file;
        heroBgPreview.value = previewUrl;
    }
};

const submitSettings = async () => {
    submitting.value = true;

    try {
        const formData = new FormData();

        Object.keys(form).forEach((key) => {
            const value = form[key];
            let finalValue = value === true ? '1' : value === false ? '0' : value ?? '';
            
            // Ensure theme_navbar_transparency is sent as string
            if (key === 'theme_navbar_transparency' && typeof value === 'number') {
                finalValue = String(value);
            }
            
            formData.append(`settings[${key}]`, finalValue);
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
        if (heroBgFile.value) {
            formData.append('hero_bg', heroBgFile.value);
        }

        const response = await settingsStore.save(formData);

        if (response?.data?.success) {
            ElMessage.success(window.t('settings_have_been_saved_successfully'));
            loadSettings(response.data.data.settings || settingsStore.data);
        } else {
            ElMessage.error(response?.data?.message || window.t('failed_to_save_settings'));
        }
    } catch (error) {
        const message = error.response?.data?.message || window.t('failed_to_save_settings');
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

.lang-switch-bar {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
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

/* Palette presets styles */
.palette-picker-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
}
.palettes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    margin-top: 10px;
}
.palette-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.palette-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
    transform: translateY(-2px);
}
.palette-name {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
}
.palette-colors {
    display: flex;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}
.color-stripe {
    flex: 1;
    height: 100%;
}

/* Custom Color Controls styling */
.custom-color-item {
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.color-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
}
.color-control-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}
.color-control-wrapper :deep(.el-color-picker__trigger) {
    border-radius: 8px;
    width: 38px;
    height: 38px;
}
.color-control-wrapper :deep(.el-input__wrapper) {
    border-radius: 8px;
    height: 38px;
}
.swatches-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
}
.swatch-circle {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    cursor: pointer;
    border: 1px solid rgba(0, 0, 0, 0.15);
    transition: transform 0.2s ease;
}
.swatch-circle:hover {
    transform: scale(1.2);
}

/* Live Storefront Mockup Preview styling */
.mockup-preview-card {
    background: #1e293b;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.20);
    border: 1px solid #334155;
    position: sticky;
    top: 20px;
}
.mockup-header {
    background: #0f172a;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    border-bottom: 1px solid #1e293b;
}
.mockup-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.mockup-dot.red { background: #ef4444; }
.mockup-dot.yellow { background: #eab308; }
.mockup-dot.green { background: #22c55e; }
.mockup-title {
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: auto;
    direction: ltr;
}
.mockup-sub-title {
    font-size: 0.65rem;
    color: #94a3b8;
    margin: 6px 12px 2px;
    font-weight: 700;
}
.mock-nav-scrolled {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.mock-nav {
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}
.mock-logo-text {
    font-weight: 800;
    font-size: 0.85rem;
}
.mock-nav-links {
    display: flex;
    gap: 8px;
    font-size: 0.7rem;
}
.mock-nav-link {
    font-weight: 600;
}
.mock-nav-link.active {
    border-bottom: 2px solid;
}
.mock-page-header {
    padding: 12px 14px;
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 2px;
    transition: all 0.3s ease;
}
.mock-page-title {
    font-weight: 700;
    font-size: 0.85rem;
}
.mock-breadcrumb {
    font-size: 0.65rem;
}
.mock-body {
    background: #f1f5f9;
    padding: 14px;
    display: flex;
    justify-content: center;
}
.mock-product-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    width: 160px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.mock-product-image {
    height: 90px;
    background: #cbd5e1;
    position: relative;
}
.mock-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    font-size: 0.55rem;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 10px;
}
.mock-product-details {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.mock-product-name {
    font-size: 0.7rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mock-product-price {
    font-size: 0.75rem;
    font-weight: 800;
}
.mock-btn {
    border: none;
    padding: 5px 0;
    font-size: 0.65rem;
    font-weight: bold;
    cursor: pointer;
    width: 100%;
    transition: all 0.2s ease;
}
.mock-footer {
    padding: 10px;
    text-align: center;
    font-size: 0.6rem;
    transition: all 0.3s ease;
}
.mock-footer-content {
    opacity: 0.8;
}

/* Values Editor */
.values-editor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.value-editor-card {
    border-radius: 12px;
}

.value-editor-card :deep(.el-card__header) {
    padding: 12px 16px;
    font-weight: 700;
    font-size: 0.9rem;
    background: var(--el-color-primary-light-9, #f0f5ff);
    border-bottom: 1px solid var(--el-border-color-light, #e5e7eb);
}

.value-card-title {
    color: var(--el-color-primary, #409eff);
}
</style>
