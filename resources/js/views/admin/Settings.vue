<template>
    <div class="settings-page" :class="{ 'has-savebar': isDirty }">
        <header class="settings-top">
            <div>
                <h1>{{ $t('system_settings') }}</h1>
                <p>{{ $t('full_control_over_site_settings') }}</p>
            </div>
            <span class="save-state" :class="{ dirty: isDirty }">
                <el-icon><component :is="isDirty ? EditPen : CircleCheck" /></el-icon>
                {{ isDirty ? $t('settings_unsaved_short') : $t('settings_all_saved') }}
            </span>
        </header>

        <div class="settings-layout" v-loading="initialLoading">
            <!-- Section navigation -->
            <nav class="settings-nav" :aria-label="$t('system_settings')">
                <template v-for="group in sectionGroups" :key="group.key">
                    <div class="nav-group-label">{{ group.label }}</div>
                    <button
                        v-for="section in group.sections"
                        :key="section.key"
                        type="button"
                        class="nav-item"
                        :class="{ active: activeSection === section.key }"
                        :aria-current="activeSection === section.key ? 'page' : undefined"
                        @click="activeSection = section.key"
                    >
                        <span class="nav-icon"><el-icon><component :is="section.icon" /></el-icon></span>
                        <span class="nav-text">
                            <strong>{{ section.label }}</strong>
                            <small>{{ section.hint }}</small>
                        </span>
                        <span v-if="sectionErrors[section.key]" class="nav-badge error" :title="$t('settings_section_has_errors')">!</span>
                        <span v-else-if="dirtySections.has(section.key)" class="nav-badge dirty" :title="$t('settings_unsaved_short')"></span>
                    </button>
                </template>
            </nav>

            <div class="settings-content">
                <header class="section-head">
                    <span class="section-head-icon"><el-icon><component :is="currentSection.icon" /></el-icon></span>
                    <div>
                        <h2>{{ currentSection.label }}</h2>
                        <p>{{ currentSection.hint }}</p>
                    </div>
                </header>

                <el-alert
                    v-if="serverErrors.length"
                    type="error"
                    show-icon
                    :closable="true"
                    class="errors-alert"
                    :title="$t('settings_fix_errors')"
                    @close="serverErrors = []"
                >
                    <ul class="error-list">
                        <li v-for="item in serverErrors" :key="item.field">
                            <button type="button" class="error-link" @click="activeSection = item.section">{{ item.message }}</button>
                        </li>
                    </ul>
                </el-alert>
                <section v-show="activeSection === 'general'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="generalLang" size="small">
                                <el-radio-button value="ar">{{ $t('arabic') }}</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="generalLang === 'ar'">
                            <el-row :gutter="20">
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('site_name')">
                                        <el-input v-model="form.site_name" :placeholder="$t('site_fallback_name')" />
                                    </el-form-item>
                                </el-col>

                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('tagline')">
                                        <el-input v-model="form.site_tagline" placeholder="نبني معاً غد سورية الأجمل" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-form-item :label="$t('site_description')">
                                <el-input
                                    type="textarea"
                                    :rows="4"
                                    v-model="form.site_description"
                                    :placeholder="$t('site_short_description_placeholder')"
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

                        <div class="toggle-list">
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('display_the_site_name_in_the_logo') }}</strong>
                                    <small>{{ $t('settings_show_site_name_hint') }}</small>
                                </span>
                                <el-switch v-model="form.show_site_name" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('view_product_prices') }}</strong>
                                    <small>{{ $t('settings_show_prices_hint') }}</small>
                                </span>
                                <el-switch v-model="form.show_product_price" />
                            </label>
                        </div>
                    </el-form>
                </section>



                <section v-show="activeSection === 'localization'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <el-alert
                            :title="$t('base_currency_settings_hint')"
                            type="info"
                            :closable="false"
                            show-icon
                            class="mb-4"
                        />
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('base_currency')">
                                    <el-select
                                        v-model="form.default_currency"
                                        :placeholder="$t('select_currency')"
                                        :loading="currenciesLoading"
                                        filterable
                                        class="w-full"
                                    >
                                        <el-option
                                            v-for="item in currencyOptions"
                                            :key="item.value"
                                            :label="item.label"
                                            :value="item.value"
                                        />
                                    </el-select>
                                    <p class="field-hint">
                                        {{ $t('base_currency_help') }}
                                        <router-link to="/admin/currencies">{{ $t('manage_currencies') }}</router-link>
                                    </p>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('default_language')">
                                    <el-select v-model="form.default_language" :placeholder="$t('choose_language')" class="w-full">
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
                            <el-select v-model="form.timezone" :placeholder="$t('choose_the_time_zone')" filterable class="w-full">
                                <el-option
                                    v-for="item in timezones"
                                    :key="item.value"
                                    :label="item.label"
                                    :value="item.value"
                                />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeSection === 'contact'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="contactLang" size="small">
                                <el-radio-button value="ar">{{ $t('arabic') }}</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('phone')">
                                    <el-input v-model.trim="form.contact_phone" placeholder="00963962889577" dir="ltr" :prefix-icon="Phone" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('whatsapp_number')">
                                    <el-input v-model.trim="form.contact_whatsapp" placeholder="00963962889577" dir="ltr" :prefix-icon="ChatDotRound" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('email')" :error="fieldError('contact_email') || emailError">
                                    <el-input v-model.trim="form.contact_email" type="email" placeholder="awaanaltakadom@gmail.com" dir="ltr" :prefix-icon="Message" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <template v-if="contactLang === 'ar'">
                                    <el-form-item :label="$t('working_hours')">
                                        <el-input v-model="form.working_hours" placeholder="السبت الى الخميس 08:00-22:00" />
                                    </el-form-item>
                                </template>
                                <template v-else>
                                    <el-form-item label="Working Hours">
                                        <el-input v-model="form.working_hours_en" placeholder="Saturday - Thursday 08:00-22:00" />
                                    </el-form-item>
                                </template>
                            </el-col>
                        </el-row>

                        <!-- Branches: one card per address, in the order the site
                             lists them. The old single address box held all of
                             them as lines of one text; on save they are still
                             written back to it, for whatever reads `address`. -->
                        <div class="branches-head">
                            <div>
                                <h3 class="sub-head">{{ $t('settings_branches') }}</h3>
                                <p class="branches-hint">{{ $t('settings_branches_hint') }}</p>
                            </div>
                            <el-button type="primary" plain :icon="Plus" @click="addBranch">{{ $t('settings_branch_add') }}</el-button>
                        </div>

                        <div v-if="!branches.length" class="branches-empty">
                            <el-icon><Location /></el-icon>
                            <span>{{ $t('settings_branches_empty') }}</span>
                        </div>

                        <div v-for="(branch, index) in branches" :key="branch.uid" class="branch-card" :class="{ 'is-main': branch.is_main }">
                            <div class="branch-card-head">
                                <span class="branch-index">{{ index + 1 }}</span>
                                <strong class="branch-title">
                                    {{ (contactLang === 'en' ? branch.name_en : branch.name_ar) || branch.name_ar || $t('settings_branch_untitled') }}
                                </strong>
                                <el-tag v-if="branch.is_main" size="small" type="success" effect="light">{{ $t('settings_branch_main') }}</el-tag>
                                <div class="branch-tools">
                                    <el-button
                                        v-if="!branch.is_main"
                                        size="small"
                                        text
                                        @click="setMainBranch(index)"
                                    >{{ $t('settings_branch_make_main') }}</el-button>
                                    <el-button size="small" text :icon="ArrowUp" :disabled="index === 0" :aria-label="$t('settings_branch_up')" @click="moveBranch(index, -1)" />
                                    <el-button size="small" text :icon="ArrowDown" :disabled="index === branches.length - 1" :aria-label="$t('settings_branch_down')" @click="moveBranch(index, 1)" />
                                    <el-button size="small" text type="danger" :icon="Delete" :aria-label="$t('delete')" @click="removeBranch(index)" />
                                </div>
                            </div>

                            <el-row :gutter="16">
                                <el-col :xs="24" :md="10">
                                    <el-form-item v-if="contactLang === 'ar'" :label="$t('settings_branch_name')">
                                        <el-input v-model="branch.name_ar" maxlength="120" placeholder="المركز الرئيسي" />
                                    </el-form-item>
                                    <el-form-item v-else label="Branch name">
                                        <el-input v-model="branch.name_en" maxlength="120" placeholder="Head office" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="14">
                                    <el-form-item v-if="contactLang === 'ar'" :label="$t('address')">
                                        <el-input v-model="branch.address_ar" maxlength="300" placeholder="ريف دمشق - منطقة معربا" />
                                    </el-form-item>
                                    <el-form-item v-else label="Address">
                                        <el-input v-model="branch.address_en" maxlength="300" placeholder="Rural Damascus - Maaraba" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="10">
                                    <el-form-item :label="$t('settings_branch_phone')">
                                        <el-input v-model.trim="branch.phone" maxlength="40" dir="ltr" placeholder="00963962889577" :prefix-icon="Phone" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="14">
                                    <el-form-item :label="$t('settings_branch_map')" :error="urlError(branch.map_url)">
                                        <el-input v-model.trim="branch.map_url" maxlength="500" dir="ltr" placeholder="https://maps.app.goo.gl/…" :prefix-icon="Location" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>
                    </el-form>
                </section>

                <section v-show="activeSection === 'social'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <div class="social-grid">
                            <el-form-item
                                v-for="item in socialFields"
                                :key="item.key"
                                :label="item.label"
                                :error="fieldError(item.key) || urlError(form[item.key])"
                            >
                                <el-input v-model.trim="form[item.key]" :placeholder="item.placeholder" dir="ltr" clearable>
                                    <template #prefix><i :class="item.icon" class="social-icon" :style="{ color: item.color }"></i></template>
                                    <template #append>
                                        <el-button
                                            :icon="TopRight"
                                            :disabled="!isUrl(form[item.key])"
                                            :title="$t('settings_open_link')"
                                            @click="openLink(form[item.key])"
                                        />
                                    </template>
                                </el-input>
                            </el-form-item>
                        </div>
                    </el-form>
                </section>

                <section v-show="activeSection === 'seo'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="seoLang" size="small">
                                <el-radio-button value="ar">{{ $t('arabic') }}</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="seoLang === 'ar'">
                            <el-row :gutter="20">
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('site_title_meta_title')">
                                        <el-input v-model="form.meta_title" placeholder="أوان التقدم - مستلزمات البناء والمواد الإنشائية" />
                                        <LengthMeter :value="form.meta_title" :ideal="60" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('keywords')">
                                        <el-input v-model="form.meta_keywords" placeholder="مواد بناء, مضخات مياه, خلاطات حمامات, أكسسوارات صحية, كلادينج, قواطع جبسية" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-form-item :label="$t('site_description_meta_description')">
                                <el-input type="textarea" :rows="3" v-model="form.meta_description" :placeholder="$t('site_seo_description_placeholder')" />
                                <LengthMeter :value="form.meta_description" :ideal="160" />
                            </el-form-item>
                        </template>

                        <template v-else>
                            <el-row :gutter="20">
                                <el-col :xs="24" :md="12">
                                    <el-form-item label="Meta Title">
                                        <el-input v-model="form.meta_title_en" placeholder="Awaan Al-Takadom - Building Materials" dir="ltr" />
                                        <LengthMeter :value="form.meta_title_en" :ideal="60" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="12">
                                    <el-form-item label="Meta Keywords">
                                        <el-input v-model="form.meta_keywords_en" placeholder="building materials, water pumps, bathroom mixers, sanitary accessories, cladding, gypsum partitions" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-form-item label="Meta Description">
                                <el-input type="textarea" :rows="3" v-model="form.meta_description_en" placeholder="Brief site description for search engines..." dir="ltr" />
                                <LengthMeter :value="form.meta_description_en" :ideal="160" />
                            </el-form-item>
                        </template>

                        <div class="serp-preview" :dir="seoLang === 'ar' ? 'rtl' : 'ltr'">
                            <span class="serp-caption">{{ $t('settings_search_preview') }}</span>
                            <div class="serp-site">
                                <span class="serp-favicon">
                                    <img v-if="faviconPreview" :src="faviconPreview" alt="" />
                                </span>
                                <span>
                                    <strong>{{ serpSiteName }}</strong>
                                    <small dir="ltr">{{ siteHost }}</small>
                                </span>
                            </div>
                            <div class="serp-title">{{ serpTitle }}</div>
                            <div class="serp-desc">{{ serpDescription }}</div>
                        </div>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('open_graph_image')">
                                    <ImageDropzone
                                        :preview="ogImagePreview"
                                        :label="$t('open_graph_image')"
                                        :hint="$t('settings_og_hint')"
                                        :pending-name="pendingFiles.ogImage"
                                        @select="(file) => onFileSelect(file, 'ogImage')"
                                    />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="Google Analytics ID" :error="fieldError('google_analytics') || analyticsError">
                                    <el-input v-model.trim="form.google_analytics" placeholder="G-XXXXXXXXXX" dir="ltr" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </section>

                <section v-show="activeSection === 'notifications'" class="settings-section">
                    <div class="toggle-list">
                        <label v-for="item in notificationToggles" :key="item.key" class="toggle-row">
                            <span class="toggle-icon"><el-icon><component :is="item.icon" /></el-icon></span>
                            <span>
                                <strong>{{ item.label }}</strong>
                                <small>{{ item.hint }}</small>
                            </span>
                            <el-switch v-model="form[item.key]" />
                        </label>
                    </div>
                </section>

                <section v-show="activeSection === 'about'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="aboutLang" size="small">
                                <el-radio-button value="ar">{{ $t('arabic') }}</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="aboutLang === 'ar'">
                            <el-form-item :label="$t('page_title')">
                                <el-input v-model="form.about_title" :placeholder="$t('about')" />
                            </el-form-item>

                            <el-form-item :label="$t('page_description')">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.about_description"
                                    :placeholder="$t('site_description_default')"
                                />
                            </el-form-item>

                            <el-form-item :label="$t('our_story')">
                                <el-input
                                    type="textarea"
                                    :rows="5"
                                    v-model="form.about_story"
                                    placeholder="في عالم متسارع يتطلب البناء فيه الجمع بين القوة والجمال، ولدت أوان التقدم. لم نكن نريد مجرد توريد مواد بناء، بل أردنا تغيير الطريقة التي تُبنى بها المشاريع."
                                />
                            </el-form-item>

                            <el-divider content-position="left">{{ $t('values') }}</el-divider>
                            <div class="values-editor-grid">
                                <el-card v-for="i in 5" :key="'ar-val-'+i" class="value-editor-card" shadow="hover">
                                    <template #header>
                                        <span class="value-card-title">القيمة {{ i }}</span>
                                    </template>
                                    <el-form-item :label="$t('settings_value_title')">
                                        <el-input v-model="form[`about_value_${i}_title`]" :placeholder="`عنوان القيمة ${i}`" />
                                    </el-form-item>
                                    <el-form-item :label="$t('description')">
                                        <el-input type="textarea" :rows="2" v-model="form[`about_value_${i}_desc`]" :placeholder="`وصف القيمة ${i}`" />
                                    </el-form-item>
                                </el-card>
                            </div>

                            <el-form-item :label="$t('what_we_offer')">
                                <el-input
                                    type="textarea"
                                    :rows="4"
                                    v-model="form.about_services"
                                    placeholder="أدوات صحية وعصرية: تشكيلة راقية من أطقم الحمامات والخلاطات...&#13;&#10;أنظمة إضاءة ذكية: حلول إنارة داخلية وخارجية متطورة...&#13;&#10;سيراميك وبورسلان فاخر: أرضيات وجدران بألوان ونقشات عصرية..."
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
                                    placeholder="In a fast-paced world where construction requires a combination of strength and beauty, Awan Progress was born. We didn't just want to supply building materials, we wanted to change the way projects are built."
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
                                    placeholder="Modern Sanitary Ware: A refined selection of bathroom fixtures...&#13;&#10;Smart Lighting Systems: Advanced indoor and outdoor lighting...&#13;&#10;Premium Ceramics & Porcelain: Floors and walls with modern patterns..."
                                />
                            </el-form-item>
                        </template>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('years_of_experience')">
                                    <el-input-number v-model="form.about_years" :min="0" class="w-full" controls-position="right" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('completed_projects')">
                                    <el-input-number v-model="form.about_projects" :min="0" class="w-full" controls-position="right" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('happy_customers')">
                                    <el-input-number v-model="form.about_customers" :min="0" class="w-full" controls-position="right" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('trusted_partners')">
                                    <el-input-number v-model="form.about_partners" :min="0" class="w-full" controls-position="right" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </section>

                <section v-show="activeSection === 'vision'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <div class="lang-switch-bar">
                            <el-radio-group v-model="visionLang" size="small">
                                <el-radio-button value="ar">{{ $t('arabic') }}</el-radio-button>
                                <el-radio-button value="en">English</el-radio-button>
                            </el-radio-group>
                        </div>

                        <template v-if="visionLang === 'ar'">
                            <el-form-item :label="$t('page_title')">
                                <el-input v-model="form.vision_title" :placeholder="$t('nav_vision')" />
                            </el-form-item>

                            <el-form-item :label="$t('page_description')">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_description"
                                    placeholder="نسعى لأن نكون الخيار الأول في سوق مستلزمات البناء في سورية والمنطقة، من خلال تقديم منتجات عالمية بمعايير جودة عالية وخدمة لا مثيل لها."
                                />
                            </el-form-item>

                            <el-divider content-position="left">{{ $t('the_first_advantage') }}</el-divider>
                            <el-form-item :label="$t('title_of_the_first_feature')">
                                <el-input v-model="form.vision_feature_1_title" :placeholder="$t('global_quality')" />
                            </el-form-item>
                            <el-form-item :label="$t('description_of_the_first_feature')">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_1_description"
                                    placeholder="نعمل مع أكبر الموردين العالميين لتقديم مستلزمات بناء تلبي أعلى معايير الجودة الدولية. كل منتج نقدمه يخضع لعمليات فحص ورقابة صارمة لضمان التميز."
                                />
                            </el-form-item>

                            <el-divider content-position="left">{{ $t('the_second_advantage') }}</el-divider>
                            <el-form-item :label="$t('title_of_the_second_feature')">
                                <el-input v-model="form.vision_feature_2_title" :placeholder="$t('modern_design')" />
                            </el-form-item>
                            <el-form-item :label="$t('description_of_the_second_feature')">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_2_description"
                                    placeholder="نواكب أحدث صرحات التصميم المعماري والديكور الداخلي لنقدم لكم منتجات تجمع بين الجمال والوظيفية. نؤمن بأن التصميم الجيد يبدأ باختيار المواد المناسبة."
                                />
                            </el-form-item>

                            <el-divider content-position="left">{{ $t('the_third_advantage') }}</el-divider>
                            <el-form-item :label="$t('title_of_the_third_feature')">
                                <el-input v-model="form.vision_feature_3_title" :placeholder="$t('trusted_partnership')" />
                            </el-form-item>
                            <el-form-item :label="$t('description_of_the_third_feature')">
                                <el-input
                                    type="textarea"
                                    :rows="3"
                                    v-model="form.vision_feature_3_description"
                                    placeholder="نبني مع شركائنا علاقات استراتيجية طويلة الأمد ترتكز على الثقة والشفافية والمنفعة المشتركة. نرى أنفسنا شريكاً حقيقياً في نجاح مشاريعكم الإنشائية."
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
                                    placeholder="We aspire to be the first choice in the building materials market in Syria and the region, by providing global products with high quality standards and unparalleled service."
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
                                    placeholder="We work with the world's largest suppliers to provide building materials that meet the highest international quality standards. Every product we offer undergoes strict inspection and quality control processes."
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
                                    placeholder="We keep pace with the latest architectural and interior design trends to offer you products that combine beauty and functionality. We believe that good design starts with choosing the right materials."
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
                                    placeholder="We build long-term strategic relationships with our partners based on trust, transparency, and mutual benefit. We see ourselves as a true partner in the success of your construction projects."
                                />
                            </el-form-item>
                        </template>
                    </el-form>
                </section>

                <section v-show="activeSection === 'design'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <h3 class="sub-head">{{ $t('basic_pictures_on_the_site') }}</h3>
                        <div class="image-grid">
                            <el-form-item :label="$t('site_logo')">
                                <ImageDropzone
                                    :preview="logoPreview"
                                    :label="$t('site_logo')"
                                    shape="square"
                                    transparent-bg
                                    :pending-name="pendingFiles.logo"
                                    @select="(file) => onFileSelect(file, 'logo')"
                                />
                            </el-form-item>
                            <el-form-item :label="$t('website_icon_favicon')">
                                <ImageDropzone
                                    :preview="faviconPreview"
                                    :label="$t('website_icon_favicon')"
                                    :hint="$t('settings_favicon_hint')"
                                    shape="square"
                                    transparent-bg
                                    :pending-name="pendingFiles.favicon"
                                    @select="(file) => onFileSelect(file, 'favicon')"
                                />
                            </el-form-item>
                            <el-form-item :label="$t('main_banner_background_image_hero')">
                                <ImageDropzone
                                    :preview="heroBgPreview"
                                    :label="$t('main_banner_background_image_hero')"
                                    :hint="$t('settings_hero_hint')"
                                    :pending-name="pendingFiles.heroBg"
                                    @select="(file) => onFileSelect(file, 'heroBg')"
                                />
                            </el-form-item>
                        </div>

                        <h3 class="sub-head">{{ $t('customized_color_palettes_and_style') }}</h3>
                        
                        <!-- 1. Color Palettes Selection -->
                        <div class="palette-picker-container mb-4">
                            <p class="sub-hint">{{ $t('professional_ready_made_palettes_one') }}</p>
                            <div class="palettes-grid">
                                <button
                                    v-for="(p, idx) in colorPalettes"
                                    :key="idx"
                                    type="button"
                                    class="palette-card"
                                    :class="{ selected: isPaletteActive(p) }"
                                    @click="applyPalette(p)"
                                >
                                    <el-icon v-if="isPaletteActive(p)" class="palette-check"><CircleCheck /></el-icon>
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
                                </button>
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
                                                <el-input v-model="form.theme_primary_color" placeholder="#1e3a8a" />
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
                                                        <el-input v-model="form.theme_primary_light_color" placeholder="#3b82f6" />
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
                                                        <el-input v-model="form.theme_primary_dark_color" placeholder="#1e1b4b" />
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
                                                        <el-input v-model="form.theme_secondary_color" placeholder="#06b6d4" />
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
                                                        <el-input v-model="form.theme_secondary_light_color" placeholder="#67e8f9" />
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
                                                        <el-input v-model="form.theme_navbar_bg_color" placeholder="#1e3a8a" />
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
                                                        <el-input v-model="form.theme_navbar_scrolled_bg_color" placeholder="#1e3a8a" />
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
                                                        <el-input v-model="form.theme_page_header_bg_color" placeholder="linear-gradient(135deg, #1e3a8a, #3b82f6)" />
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
                                                        <el-input v-model="form.theme_footer_bg_color" placeholder="#1e1b4b" />
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
                                                        <el-input v-model="form.theme_hero_btn_bg_color" placeholder="linear-gradient(135deg, #1e3a8a, #3b82f6)" />
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
                                                        <el-input v-model="form.theme_hero_btn_secondary_text_color" placeholder="#1e3a8a" />
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
                                                        <el-input v-model="form.theme_cart_btn_bg_color" placeholder="#1e3a8a" />
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
                                    <div class="mock-nav" :style="{ backgroundColor: form.theme_navbar_bg_color || '#1e3a8a', color: form.theme_navbar_text_color || '#ffffff' }">
                                        <span class="mock-logo-text" :style="{ color: form.theme_navbar_text_color || '#ffffff' }">{{ previewSiteName }}</span>
                                        <div class="mock-nav-links">
                                            <span class="mock-nav-link active" :style="{ color: form.theme_navbar_text_color || '#ffffff' }">{{ $t('home') }}</span>
                                            <span class="mock-nav-link" :style="{ color: form.theme_navbar_text_color || '#ffffff', opacity: 0.7 }">{{ $t('products') }}</span>
                                        </div>
                                    </div>

                                    <div class="mockup-sub-title mt-2">{{ $t('navigation_bar_on_scroll') }}</div>
                                    <div class="mock-nav mock-nav-scrolled" :style="{ backgroundColor: form.theme_navbar_scrolled_bg_color || form.theme_navbar_bg_color || '#1e3a8a', color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff' }">
                                        <span class="mock-logo-text" :style="{ color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff' }">{{ previewSiteName }}</span>
                                        <div class="mock-nav-links">
                                            <span class="mock-nav-link active" :style="{ color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff' }">{{ $t('home') }}</span>
                                            <span class="mock-nav-link" :style="{ color: form.theme_navbar_scrolled_text_color || form.theme_navbar_text_color || '#ffffff', opacity: 0.7 }">{{ $t('products') }}</span>
                                        </div>
                                    </div>

                                    <!-- Hero Banner Mockup -->
                                    <div class="mockup-sub-title mt-2">{{ $t('main_banner_hero_section') }}</div>
                                    <div class="mock-hero" :style="mockHeroStyle">
                                        <div class="mock-hero-title">{{ previewTagline }}</div>
                                        <div class="mock-hero-buttons" :style="{ justifyContent: heroJustify }">
                                            <button class="mock-btn mock-hero-btn-primary" :style="{ background: form.theme_hero_btn_bg_color || form.theme_primary_color || '#1e3a8a', color: form.theme_hero_btn_text_color || '#ffffff', border: 'none', padding: '4px 10px', fontSize: '0.65rem', borderRadius: form.theme_border_radius, fontWeight: 'bold' }">
                                                {{ $t('browse_products') }}
                                            </button>
                                            <button class="mock-btn mock-hero-btn-secondary" :style="{ background: form.theme_hero_btn_secondary_bg_color || 'rgba(255, 255, 255, 0.1)', color: form.theme_hero_btn_secondary_text_color || '#ffffff', border: '1px solid ' + (form.theme_hero_btn_secondary_text_color || 'rgba(255,255,255,0.4)'), padding: '4px 10px', fontSize: '0.65rem', borderRadius: form.theme_border_radius, fontWeight: 'bold' }">
                                                {{ $t('contact_us') }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Page Header Mockup -->
                                    <div class="mock-page-header" :style="{ background: form.theme_page_header_bg_color || 'linear-gradient(135deg, #1e3a8a, #3b82f6)', color: form.theme_page_header_text_color || '#ffffff' }">
                                        <div class="mock-page-title" :style="{ color: form.theme_page_header_text_color || '#ffffff' }">{{ $t('settings_preview_category') }}</div>
                                        <div class="mock-breadcrumb" :style="{ color: form.theme_page_header_text_color || '#ffffff', opacity: 0.8 }">{{ $t('settings_preview_breadcrumb') }}</div>
                                    </div>

                                    <!-- Body & Product Card Mockup -->
                                    <div class="mock-body">
                                        <div class="mock-product-card" :style="{ borderRadius: form.theme_border_radius }">
                                            <div class="mock-product-image">
                                                <span class="mock-badge" :style="{ backgroundColor: form.theme_accent_color || '#f59e0b', color: '#ffffff' }">{{ $t('new') }}</span>
                                            </div>
                                            <div class="mock-product-details">
                                                <div class="mock-product-name">{{ $t('settings_preview_product') }}</div>
                                                <div class="mock-product-price" :style="{ color: form.theme_primary_color || '#1e3a8a' }">{{ previewPrice }}</div>
                                                <button class="mock-btn" :style="{ backgroundColor: form.theme_cart_btn_bg_color || form.theme_primary_color || '#1e3a8a', color: form.theme_cart_btn_text_color || '#ffffff', borderRadius: form.theme_border_radius }">
                                                    {{ $t('add_to_cart') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer Mockup -->
                                    <div class="mock-footer" :style="{ backgroundColor: form.theme_footer_bg_color || '#1e1b4b', color: form.theme_footer_text_color || '#e2e8f0' }">
                                        <div class="mock-footer-content" :style="{ color: form.theme_footer_text_color || '#f8f9fa' }">
                                            <span>© {{ new Date().getFullYear() }} {{ previewSiteName }}</span>
                                        </div>
                                    </div>
                                </div>
                            </el-col>
                        </el-row>

                        <h3 class="sub-head">{{ $t('lines_and_structural_patterns') }}</h3>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('default_site_font_font_family')">
                                    <el-select v-model="form.theme_font_family" :placeholder="$t('choose_font')" class="w-full">
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
                                    <el-select v-model="form.theme_border_radius" :placeholder="$t('choose_the_shape_of_the_edges')" class="w-full">
                                        <el-option :label="$t('very_sharp_sharp_0px')" value="0px" />
                                        <el-option :label="$t('rounded_8px')" value="8px" />
                                        <el-option :label="$t('extra_rounded_14px')" value="14px" />
                                        <el-option :label="$t('fully_oval_pill_30px')" value="30px" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <h3 class="sub-head">{{ $t('customize_the_main_banner_hero_section') }}</h3>
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
                                    <el-slider v-model="form.theme_hero_overlay_opacity" :min="0.1" :max="0.9" :step="0.05" show-input />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <h3 class="sub-head">{{ $t('customize_site_footer') }}</h3>
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

                        <h3 class="sub-head">{{ $t('custom_css_codes_custom_stylesheet') }}</h3>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="24">
                                <el-form-item :label="$t('customize_design_via_code_custom_css')">
                                    <el-input
                                        type="textarea"
                                        :rows="6"
                                        v-model="form.theme_custom_css"
                                        :placeholder="$t('add_custom_css_codes_here')"
                                        class="code-input"
                                        dir="ltr"
                                    />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                </section>

                <section v-show="activeSection === 'printing'" class="settings-section">
                    <el-form :model="form" label-position="top" @submit.prevent>
                        <el-alert
                            :title="$t('print_settings_hint') || 'إعدادات وخيارات الطباعة وتصميم المستندات الرسمية'"
                            type="info"
                            :closable="false"
                            show-icon
                            class="mb-4"
                        />

                        <!-- 1. Document Format & Header Layout -->
                        <h3 class="sub-head">{{ $t('print_header_and_layout') || 'نمط الترويسة وتخطيط المستند' }}</h3>
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="8">
                                <el-form-item :label="$t('header_style') || 'نمط الترويسة'">
                                    <el-select v-model="form.print_header_style" class="w-full">
                                        <el-option value="official" :label="$t('header_official') || 'ترويسة رسمية كاملة (شعار + بيانات المؤسسة)'" />
                                        <el-option value="banner" :label="$t('header_banner') || 'بانر جرافيكي علوي عريض'" />
                                        <el-option value="compact" :label="$t('header_compact') || 'ترويسة مدمجة مختصرة'" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item :label="$t('theme') || 'السمة اللونية'">
                                    <el-select v-model="form.print_theme" class="w-full">
                                        <el-option value="navy" :label="$t('theme_navy') || 'أزرق كحلي رسمي'" />
                                        <el-option value="emerald" :label="$t('theme_emerald') || 'أخضر زمردي'" />
                                        <el-option value="charcoal" :label="$t('theme_charcoal') || 'رمادي فحمي عصري'" />
                                        <el-option value="indigo" :label="$t('theme_indigo') || 'نيلي داكن'" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item :label="$t('density') || 'كثافة المسافات والجدول'">
                                    <el-select v-model="form.print_density" class="w-full">
                                        <el-option value="standard" :label="$t('density_standard') || 'قياسي ومتوازن'" />
                                        <el-option value="compact" :label="$t('density_compact') || 'مدمج (توفير الورق لكشوفات البنود)'" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('watermark') || 'العلامة المائية الافتراضية'">
                                    <el-select v-model="form.print_watermark" class="w-full">
                                        <el-option value="" :label="$t('watermark_none') || 'بدون علامة مائية'" />
                                        <el-option value="draft" :label="$t('watermark_draft') || 'مسودة غير معتمدة (DRAFT)'" />
                                        <el-option value="approved" :label="$t('watermark_approved') || 'معتمد رسمياً (APPROVED)'" />
                                        <el-option value="paid" :label="$t('watermark_paid') || 'مدفوع بالكامل (PAID)'" />
                                        <el-option value="official" :label="$t('watermark_official') || 'وثيقة رسمية (OFFICIAL)'" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <!-- 2. Official Seal, Signature & Authorized Person -->
                        <h3 class="sub-head mt-4">{{ $t('stamp_and_signature') || 'الأختام والتواقيع والاعتماد الرسمي' }}</h3>
                        <div class="image-grid mb-4">
                            <el-form-item :label="$t('stamp_image') || 'ختم المؤسسة الرسمي (شفاف PNG)'">
                                <ImageDropzone
                                    :preview="stampImagePreview"
                                    :label="$t('stamp_image') || 'ختم المؤسسة'"
                                    shape="square"
                                    transparent-bg
                                    :pending-name="pendingFiles.stampImage"
                                    @select="(file) => onFileSelect(file, 'stampImage')"
                                />
                            </el-form-item>
                            <el-form-item :label="$t('signature_image') || 'توقيع المفوض الرقمي (شفاف PNG)'">
                                <ImageDropzone
                                    :preview="signatureImagePreview"
                                    :label="$t('signature_image') || 'توقيع المفوض'"
                                    shape="square"
                                    transparent-bg
                                    :pending-name="pendingFiles.signatureImage"
                                    @select="(file) => onFileSelect(file, 'signatureImage')"
                                />
                            </el-form-item>
                        </div>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('print_authorized_person') || 'اسم الشخص المفوض بالتوقيع'">
                                    <el-input v-model="form.print_authorized_person" placeholder="م. محمد الأحمد / المدير العام" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('print_authorized_title') || 'الصفة أو المسمى الوظيفي'">
                                    <el-input v-model="form.print_authorized_title" placeholder="المدير المالي والتنفيذي" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <!-- 3. Header Banner & Cover Page Images -->
                        <h3 class="sub-head mt-4">{{ $t('header_banner_and_cover') || 'بانر الترويسة وغلاف A4 المخصص' }}</h3>
                        <div class="image-grid mb-4">
                            <el-form-item :label="$t('header_banner') || 'بانر الترويسة العلوي المطبوع'">
                                <ImageDropzone
                                    :preview="headerBannerPreview"
                                    :label="$t('header_banner') || 'بانر الترويسة'"
                                    shape="wide"
                                    :pending-name="pendingFiles.headerBanner"
                                    @select="(file) => onFileSelect(file, 'headerBanner')"
                                />
                            </el-form-item>
                            <el-form-item :label="$t('cover_image') || 'صورة غلاف المستند الرسمي (A4)'">
                                <ImageDropzone
                                    :preview="coverImagePreview"
                                    :label="$t('cover_image') || 'صورة الغلاف A4'"
                                    shape="wide"
                                    :pending-name="pendingFiles.coverImage"
                                    @select="(file) => onFileSelect(file, 'coverImage')"
                                />
                            </el-form-item>
                        </div>

                        <!-- 4. Default Visible Elements -->
                        <h3 class="sub-head mt-4">{{ $t('default_print_elements') || 'عناصر الطباعة الافتراضية' }}</h3>
                        <div class="toggle-list mb-4">
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_cover_page') || 'إرفاق صفحة الغلاف A4 افتراضياً' }}</strong>
                                    <small>{{ $t('show_cover_page_hint') || 'إظهار صفحة غلاف متكاملة قبل تفاصيل الفاتورة أو أمر البيع' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_cover" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_logo') || 'إظهار شعار المؤسسة' }}</strong>
                                    <small>{{ $t('show_logo_hint') || 'طباعة شعار الشركة الرسمي في أعلى الوثيقة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_logo" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_contacts') || 'إظهار بيانات التواصل والعناوين' }}</strong>
                                    <small>{{ $t('show_contacts_hint') || 'أرقام الهواتف، البريد الإلكتروني، وعنوان المركز الرئيسي في الترويسة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_contacts" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_qr_code') || 'رمز الاستجابة السريعة (QR Code)' }}</strong>
                                    <small>{{ $t('show_qr_hint') || 'طباعة رمز التحقق الرقمي المعتمد في زاوية الوثيقة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_qr" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_customer_info') || 'بيانات العميل ومعلومات الشحن' }}</strong>
                                    <small>{{ $t('show_customer_info_hint') || 'اسم العميل، العنوان، رقم الاتصال، وبيانات الفوترة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_customer_info" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_product_images') || 'صور المنتجات المصغرة' }}</strong>
                                    <small>{{ $t('show_product_images_hint') || 'إظهار صورة مصغرة لكل بند في جدول الأصناف المطبوعة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_images" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_item_sku') || 'رمز المنتج / الباركود (SKU)' }}</strong>
                                    <small>{{ $t('show_item_sku_hint') || 'إظهار رمز الصنف في سطر تفاصيل البند' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_sku" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_payment_details') || 'تفاصيل الدفع والمتبقي' }}</strong>
                                    <small>{{ $t('show_payment_details_hint') || 'جدول طريقة السداد والمبلغ المدفوع والمتبقي' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_payment_details" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_notes') || 'الملاحظات والتعليمات' }}</strong>
                                    <small>{{ $t('show_notes_hint') || 'إظهار قسم الملاحظات والشروط الملحقة بالوثيقة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_notes" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_signatures') || 'الأختام والتواقيع الرسمية' }}</strong>
                                    <small>{{ $t('show_signatures_hint') || 'إظهار مساحة وتواقيع المستلم والمحاسب والختم المعتمد' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_signatures" />
                            </label>
                            <label class="toggle-row">
                                <span>
                                    <strong>{{ $t('show_footer') || 'تذييل الصفحة وأرقام الصفحات' }}</strong>
                                    <small>{{ $t('show_footer_hint') || 'طباعة الشريط السفلي الرسمي ورقم الصفحة وتاريخ الطباعة' }}</small>
                                </span>
                                <el-switch v-model="form.print_show_footer" />
                            </label>
                        </div>

                        <!-- 5. Default Terms and Guarantees -->
                        <h3 class="sub-head mt-4">{{ $t('print_terms_label') || 'الشروط والأحكام وفترة الضمان الثابتة' }}</h3>
                        <el-form-item :label="$t('print_terms_hint') || 'النص المطبوع أسفل الفواتير وعروض الأسعار وأوامر البيع بشكل افتراضي'">
                            <el-input
                                type="textarea"
                                :rows="4"
                                v-model="form.print_terms"
                                placeholder="مثال: البضاعة المباعة تخضع للضمان الفني لمدة سنة... الدفع خلال 30 يوم من تاريخ الاستلام..."
                            />
                        </el-form-item>
                    </el-form>
                </section>
            </div>
        </div>

        <!-- Sticky save bar: visible whenever something differs from what is saved. -->
        <transition name="savebar">
            <div v-if="isDirty" class="savebar" role="region" :aria-label="$t('settings_unsaved_short')">
                <div class="savebar-text">
                    <span class="savebar-dot"></span>
                    <span>
                        <strong>{{ $t('settings_unsaved_title') }}</strong>
                        <small>{{ $t('settings_unsaved_sections', { sections: dirtySectionLabels }) }}</small>
                    </span>
                </div>
                <div class="savebar-actions">
                    <el-button :disabled="submitting" @click="discardChanges">{{ $t('profile_discard') }}</el-button>
                    <el-button type="primary" :loading="submitting" @click="submitSettings">
                        {{ $t('save_settings') }}
                        <kbd class="kbd">{{ saveShortcutLabel }}</kbd>
                    </el-button>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import {
    Setting, Money, Phone, Share, Search, Bell, OfficeBuilding, Aim, Brush,
    Message, ChatDotRound, EditPen, CircleCheck, TopRight, Iphone, Monitor, Printer,
    Plus, Delete, ArrowUp, ArrowDown, Location
} from '@element-plus/icons-vue';
import { useSettingsStore } from '@/stores/settings';
import { useCurrency } from '@/Composables/useCurrency';
import ImageDropzone from '@/components/admin/settings/ImageDropzone.vue';
import LengthMeter from '@/components/admin/settings/LengthMeter.vue';
// Aliased: `baseCurrencyCode` is already the name of this screen's own ref.
import { baseCurrencyCode as resolveBaseCurrency } from '@/utils/currency';
import { ElMessage, ElMessageBox } from 'element-plus';
import currenciesApi from '@/api/currencies';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const { formatMoney } = useCurrency();

const settingsStore = useSettingsStore();
const initialLoading = ref(true);

/* ---- Sections ---- */

const sectionGroups = computed(() => [
    {
        key: 'store',
        label: t('settings_group_store'),
        sections: [
            { key: 'general', label: t('general'), hint: t('settings_hint_general'), icon: Setting },
            { key: 'localization', label: t('currency_and_language'), hint: t('settings_hint_localization'), icon: Money },
        ],
    },
    {
        key: 'contact',
        label: t('settings_group_contact'),
        sections: [
            { key: 'contact', label: t('communication'), hint: t('settings_hint_contact'), icon: Phone },
            { key: 'social', label: t('communication_means'), hint: t('settings_hint_social'), icon: Share },
        ],
    },
    {
        key: 'content',
        label: t('settings_group_content'),
        sections: [
            { key: 'about', label: t('who_are_we'), hint: t('settings_hint_about'), icon: OfficeBuilding },
            { key: 'vision', label: t('identity_and_vision'), hint: t('settings_hint_vision'), icon: Aim },
            { key: 'seo', label: 'SEO', hint: t('settings_hint_seo'), icon: Search },
        ],
    },
    {
        key: 'system',
        label: t('settings_group_system'),
        sections: [
            { key: 'design', label: t('design'), hint: t('settings_hint_design'), icon: Brush },
            { key: 'printing', label: t('print_settings') || 'إعدادات وخيارات الطباعة', hint: t('print_settings_hint') || 'الترويسة الافتراضية، التنسيق، الألوان، الأختام والتوقيعات الرسمية', icon: Printer },
            { key: 'notifications', label: t('notifications'), hint: t('settings_hint_notifications'), icon: Bell },
        ],
    },
]);

const allSections = computed(() => sectionGroups.value.flatMap((group) => group.sections));
const sectionKeys = ['general', 'localization', 'contact', 'social', 'about', 'vision', 'seo', 'design', 'printing', 'notifications'];

// Kept in the URL so a reload or a shared link lands on the same section.
const activeSection = ref(sectionKeys.includes(route.query.section) ? route.query.section : 'general');
watch(activeSection, (section) => {
    router.replace({ query: { ...route.query, section } });
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

const currentSection = computed(() => allSections.value.find((item) => item.key === activeSection.value) || allSections.value[0]);

const SOCIAL_KEYS = ['facebook', 'instagram', 'twitter', 'youtube', 'linkedin'];

/** Which section a setting (or pending upload) lives in. */
const sectionFor = (key) => {
    if (/^(site_|show_)/.test(key)) return 'general';
    if (['default_currency', 'default_language', 'timezone'].includes(key)) return 'localization';
    if (/^(contact_|address|working_hours)/.test(key)) return 'contact';
    if (SOCIAL_KEYS.includes(key)) return 'social';
    if (/^meta_|^google_analytics$|^ogImage$/.test(key)) return 'seo';
    if (/_notifications$/.test(key)) return 'notifications';
    if (/^about_/.test(key)) return 'about';
    if (/^vision_/.test(key)) return 'vision';
    if (/^print_|^stamp_image$|^signature_image$|^header_banner$|^cover_image$|^stampImage$|^signatureImage$|^headerBanner$|^coverImage$/.test(key)) return 'printing';
    return 'design';
};
const generalLang = ref('ar');
const aboutLang = ref('ar');
const visionLang = ref('ar');
const seoLang = ref('ar');
const contactLang = ref('ar');
const submitting = ref(false);
const currenciesLoading = ref(false);
const managedCurrencies = ref([]);
const baseCurrencyCode = ref(resolveBaseCurrency());
const initialBaseCurrency = ref(baseCurrencyCode.value);

const form = reactive({
    site_name: '',
    site_name_en: '',
    site_tagline: '',
    site_tagline_en: '',
    site_description: '',
    site_description_en: '',
    show_site_name: true,
    show_product_price: true,
    default_currency: resolveBaseCurrency(),
    default_language: 'ar',
    timezone: 'Asia/Riyadh',
    contact_phone: '',
    contact_whatsapp: '',
    contact_email: '',
    address: '',
    address_en: '',
    // JSON list of branches; edited through `branches` below.
    contact_branches: '',
    working_hours: '',
    working_hours_en: '',
    facebook: '',
    instagram: '',
    twitter: '',
    youtube: '',
    linkedin: '',
    meta_title: '',
    meta_title_en: '',
    meta_description: '',
    meta_description_en: '',
    meta_keywords: '',
    meta_keywords_en: '',
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
    theme_page_header_text_color: '',
    // Printing options & defaults
    print_header_style: 'official',
    print_theme: 'navy',
    print_density: 'standard',
    print_watermark: '',
    print_show_cover: false,
    print_show_qr: true,
    print_show_logo: true,
    print_show_contacts: true,
    print_show_images: true,
    print_show_sku: true,
    print_show_customer_info: true,
    print_show_payment_details: true,
    print_show_notes: true,
    print_show_signatures: true,
    print_show_footer: true,
    print_authorized_person: '',
    print_authorized_title: '',
    print_terms: ''
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

const primaryPresets = ['#1e3a8a', '#171717', '#7f1d1d', '#4c1d95', '#0f172a', '#0284c7', '#166534'];
const primaryLightPresets = ['#3b82f6', '#262626', '#b91c1c', '#6d28d9', '#334155', '#38bdf8', '#22c55e'];
const primaryDarkPresets = ['#1e1b4b', '#0a0a0a', '#450a0a', '#2e1065', '#020617', '#0369a1', '#14532d'];
const secondaryPresets = ['#06b6d4', '#10b981', '#d4af37', '#ec4899', '#f97316', '#84cc16', '#64748b'];
const textPresets = ['#ffffff', '#e2e8f0', '#f8f9fa', '#f3e5ab', '#d4af37', '#1e293b', '#0f172a'];
const gradientPresets = [
    'linear-gradient(135deg, #1e3a8a, #3b82f6)',
    'linear-gradient(135deg, #171717, #525252)',
    'linear-gradient(135deg, #7f1d1d, #f59e0b)',
    'linear-gradient(135deg, #4c1d95, #ec4899)',
    'linear-gradient(135deg, #1e1b4b, #1e3a8a)',
    '#1e3a8a',
    '#06b6d4'
];

const logoFile = ref(null);
const faviconFile = ref(null);
const ogImageFile = ref(null);
const heroBgFile = ref(null);
const logoPreview = ref('');
const faviconPreview = ref('');
const ogImagePreview = ref('');
const heroBgPreview = ref('');

const stampImageFile = ref(null);
const stampImagePreview = ref('');
const signatureImageFile = ref(null);
const signatureImagePreview = ref('');
const headerBannerFile = ref(null);
const headerBannerPreview = ref('');
const coverImageFile = ref(null);
const coverImagePreview = ref('');

// Names of images chosen but not uploaded yet, shown on each dropzone.
const pendingFiles = reactive({
    logo: '',
    favicon: '',
    ogImage: '',
    heroBg: '',
    stampImage: '',
    signatureImage: '',
    headerBanner: '',
    coverImage: '',
});
const objectUrls = [];

const currencyOptions = computed(() => {
    const locale = window.systemData?.locale || 'ar';
    return managedCurrencies.value.map((c) => {
        const name = locale === 'en'
            ? (c.name_en || c.name || c.code)
            : (c.name_ar || c.name || c.code);
        const baseTag = c.is_base || c.code === baseCurrencyCode.value
            ? ` — ${window.t('base_currency_tag')}`
            : '';
        return {
            value: c.code,
            label: `${name} (${c.code})${baseTag}`,
        };
    });
});

const languages = [
    { value: 'ar', label: window.t('arabic')},
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'Français' }
];

const timezones = [
    { value: 'Asia/Damascus', label: 'Asia/Damascus' },
    { value: 'Asia/Beirut', label: 'Asia/Beirut' },
    { value: 'Asia/Baghdad', label: 'Asia/Baghdad' },
    { value: 'Asia/Riyadh', label: 'Asia/Riyadh' },
    { value: 'Asia/Dubai', label: 'Asia/Dubai' },
    { value: 'Asia/Amman', label: 'Asia/Amman' },
    { value: 'Africa/Cairo', label: 'Africa/Cairo' },
    { value: 'Europe/Istanbul', label: 'Europe/Istanbul' },
    { value: 'Europe/Paris', label: 'Europe/Paris' },
    { value: 'UTC', label: 'UTC' }
];

const applyCurrencyPayload = (payload = {}) => {
    const list = payload.currencies || payload.list || [];
    if (Array.isArray(list) && list.length) {
        managedCurrencies.value = list;
    }
    if (payload.base_currency || payload.base) {
        baseCurrencyCode.value = payload.base_currency || payload.base;
        initialBaseCurrency.value = baseCurrencyCode.value;
    }
};

const loadManagedCurrencies = async () => {
    currenciesLoading.value = true;
    try {
        // Prefer the boot payload, then refresh from the API so new currencies appear.
        const boot = window.systemData?.currencies;
        if (boot?.list?.length) {
            applyCurrencyPayload({ currencies: boot.list, base: boot.base });
        }

        const res = await currenciesApi.publicList();
        applyCurrencyPayload(res.data?.data || {});
    } catch (e) {
        if (!managedCurrencies.value.length) {
            managedCurrencies.value = [
                { code: baseCurrencyCode.value, name_ar: baseCurrencyCode.value, name_en: baseCurrencyCode.value, is_base: true },
            ];
        }
    } finally {
        currenciesLoading.value = false;
    }
};

const normalizeBoolean = (value) => {
    return value === '1' || value === 1 || value === true || value === 'true';
};

const loadSettings = (settings) => {
    if (!settings || Object.keys(settings).length === 0) {
        takeSnapshot();
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
    form.default_currency = settings.default_currency || baseCurrencyCode.value || resolveBaseCurrency();
    form.default_language = settings.default_language || 'ar';
    form.timezone = settings.timezone || 'Asia/Riyadh';
    initialBaseCurrency.value = form.default_currency;
    baseCurrencyCode.value = form.default_currency;
    form.contact_phone = settings.contact_phone || '';
    form.contact_whatsapp = settings.contact_whatsapp || '';
    form.contact_email = settings.contact_email || '';
    form.address = settings.address || settings.contact_address || '';
    form.address_en = settings.address_en || '';
    branches.value = parseBranches(settings.contact_branches) ?? seedBranches(form.address, form.address_en);
    form.working_hours = settings.working_hours || '';
    form.working_hours_en = settings.working_hours_en || '';
    form.facebook = settings.facebook || settings.contact_facebook || '';
    form.instagram = settings.instagram || settings.contact_instagram || '';
    form.twitter = settings.twitter || settings.contact_twitter || '';
    form.youtube = settings.youtube || settings.contact_youtube || '';
    form.linkedin = settings.linkedin || settings.contact_linkedin || '';
    form.meta_title = settings.meta_title || '';
    form.meta_title_en = settings.meta_title_en || '';
    form.meta_description = settings.meta_description || '';
    form.meta_description_en = settings.meta_description_en || '';
    form.meta_keywords = settings.meta_keywords || '';
    form.meta_keywords_en = settings.meta_keywords_en || '';
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

    // Print options
    form.print_header_style = settings.print_header_style || 'official';
    form.print_theme = settings.print_theme || 'navy';
    form.print_density = settings.print_density || 'standard';
    form.print_watermark = settings.print_watermark || '';
    form.print_show_cover = normalizeBoolean(settings.print_show_cover ?? '0');
    form.print_show_qr = normalizeBoolean(settings.print_show_qr ?? '1');
    form.print_show_logo = normalizeBoolean(settings.print_show_logo ?? '1');
    form.print_show_contacts = normalizeBoolean(settings.print_show_contacts ?? '1');
    form.print_show_images = normalizeBoolean(settings.print_show_images ?? '1');
    form.print_show_sku = normalizeBoolean(settings.print_show_sku ?? '1');
    form.print_show_customer_info = normalizeBoolean(settings.print_show_customer_info ?? '1');
    form.print_show_payment_details = normalizeBoolean(settings.print_show_payment_details ?? '1');
    form.print_show_notes = normalizeBoolean(settings.print_show_notes ?? '1');
    form.print_show_signatures = normalizeBoolean(settings.print_show_signatures ?? '1');
    form.print_show_footer = normalizeBoolean(settings.print_show_footer ?? '1');
    form.print_authorized_person = settings.print_authorized_person || '';
    form.print_authorized_title = settings.print_authorized_title || '';
    form.print_terms = settings.print_terms || '';

    const getPreviewUrl = (value) => {
        if (!value) return '';
        if (value.startsWith('/') || value.startsWith('http://') || value.startsWith('https://')) return value;
        return value.startsWith('assets/') ? `/${value}` : `/storage/${value}`;
    };

    logoPreview.value = settings.logo ? getPreviewUrl(settings.logo) : getPreviewUrl(settings.site_logo);
    faviconPreview.value = settings.favicon ? getPreviewUrl(settings.favicon) : getPreviewUrl(settings.site_favicon);
    ogImagePreview.value = getPreviewUrl(settings.og_image);
    heroBgPreview.value = getPreviewUrl(settings.hero_bg);

    stampImagePreview.value = getPreviewUrl(settings.stamp_image);
    signatureImagePreview.value = getPreviewUrl(settings.signature_image);
    headerBannerPreview.value = getPreviewUrl(settings.header_banner);
    coverImagePreview.value = getPreviewUrl(settings.cover_image);

    logoFile.value = null;
    faviconFile.value = null;
    ogImageFile.value = null;
    heroBgFile.value = null;
    stampImageFile.value = null;
    signatureImageFile.value = null;
    headerBannerFile.value = null;
    coverImageFile.value = null;
    Object.keys(pendingFiles).forEach((key) => { pendingFiles[key] = ''; });
    takeSnapshot();
};

/* ---- Unsaved-change tracking ---- */

/* ------------------------------------------------------------------ *
 * Branches (contact section)
 * ------------------------------------------------------------------ */
let branchUid = 0;
const newBranch = (fields = {}) => ({
    uid: ++branchUid,
    name_ar: '', name_en: '', address_ar: '', address_en: '', phone: '', map_url: '', is_main: false,
    ...fields,
});

const branches = ref([]);

/** The stored list, or null when there is none yet. */
const parseBranches = (raw) => {
    if (!raw) return null;
    try {
        const list = JSON.parse(raw);
        return Array.isArray(list) ? list.map((item) => newBranch(item)) : null;
    } catch {
        return null;
    }
};

/**
 * First visit after the change: the one address text held every branch as a
 * line, "Name - address". Each line becomes a branch, the first one main, for
 * the admin to check and save.
 */
const seedBranches = (address, addressEn) => {
    const lines = (text) => String(text || '').split(/\r?\n/).map((line) => line.replace(/\s*-\s*$/, '').trim()).filter(Boolean);
    const split = (line) => {
        const at = line.indexOf(' - ');
        return at > 0 ? [line.slice(0, at).trim(), line.slice(at + 3).trim()] : ['', line];
    };
    const ar = lines(address);
    const en = lines(addressEn);
    return Array.from({ length: Math.max(ar.length, en.length) }, (_, index) => {
        const [nameAr, addressAr] = split(ar[index] || '');
        const [nameEn, addressEnPart] = split(en[index] || '');
        return newBranch({ name_ar: nameAr, address_ar: addressAr, name_en: nameEn, address_en: addressEnPart, is_main: index === 0 });
    });
};

// Kept in the form as text, so the save, the unsaved-changes bar and the
// section badge treat it like any other setting. Synchronous, so loading
// settles before the snapshot is taken.
watch(branches, (list) => {
    const clean = list
        .map(({ uid, ...branch }) => ({
            ...branch,
            name_ar: branch.name_ar.trim(),
            name_en: branch.name_en.trim(),
            address_ar: branch.address_ar.trim(),
            address_en: branch.address_en.trim(),
        }))
        .filter((branch) => branch.name_ar || branch.address_ar || branch.name_en || branch.address_en);
    form.contact_branches = clean.length ? JSON.stringify(clean) : '';
}, { deep: true, flush: 'sync' });

const addBranch = () => {
    branches.value.push(newBranch({ is_main: !branches.value.length }));
};

const removeBranch = (index) => {
    const [removed] = branches.value.splice(index, 1);
    if (removed?.is_main && branches.value.length) branches.value[0].is_main = true;
};

const moveBranch = (index, step) => {
    const target = index + step;
    if (target < 0 || target >= branches.value.length) return;
    const list = branches.value;
    [list[index], list[target]] = [list[target], list[index]];
};

const setMainBranch = (index) => {
    branches.value.forEach((branch, i) => { branch.is_main = i === index; });
};

/** The single-text address the rest of the system still reads, one branch a line. */
const branchesAsText = (lang) => branches.value
    .map((branch) => {
        const name = (lang === 'en' ? branch.name_en : branch.name_ar).trim();
        const address = (lang === 'en' ? branch.address_en : branch.address_ar).trim();
        return [name, address].filter(Boolean).join(' - ');
    })
    .filter(Boolean)
    .join('\n');

// What the form looked like when it was last loaded or saved.
const snapshot = ref({});
const takeSnapshot = () => {
    snapshot.value = JSON.parse(JSON.stringify(form));
};

const changedKeys = computed(() => {
    const keys = Object.keys(form).filter((key) => String(form[key] ?? '') !== String(snapshot.value[key] ?? ''));
    Object.entries(pendingFiles).forEach(([key, name]) => { if (name) keys.push(key); });
    return keys;
});

const isDirty = computed(() => !initialLoading.value && changedKeys.value.length > 0);
const dirtySections = computed(() => new Set(changedKeys.value.map(sectionFor)));
const dirtySectionLabels = computed(() => allSections.value
    .filter((section) => dirtySections.value.has(section.key))
    .map((section) => section.label)
    .join('، '));

const discardChanges = () => {
    loadSettings(settingsStore.data);
    serverErrors.value = [];
};

onBeforeRouteLeave(async () => {
    if (!isDirty.value) return true;
    try {
        await ElMessageBox.confirm(t('profile_leave_confirm'), t('profile_unsaved_changes'), {
            type: 'warning',
            confirmButtonText: t('profile_discard'),
            cancelButtonText: t('cancel'),
        });
        return true;
    } catch {
        return false;
    }
});

const onBeforeUnload = (event) => {
    if (isDirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || '');
const saveShortcutLabel = isMac ? '⌘S' : 'Ctrl+S';

const onKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        if (isDirty.value && !submitting.value) submitSettings();
    }
};

/* ---- Validation ---- */

const serverErrors = ref([]);
const fieldError = (key) => serverErrors.value.find((item) => item.field === key)?.message || '';
const sectionErrors = computed(() => serverErrors.value.reduce((acc, item) => ({ ...acc, [item.section]: true }), {}));

const isUrl = (value) => /^https?:\/\/[^\s.]+\.[^\s]+$/i.test(String(value || '').trim());
const urlError = (value) => (value && !isUrl(value) ? t('settings_invalid_url') : '');
const emailError = computed(() => (
    form.contact_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.contact_email) ? t('profile_email_invalid') : ''
));
const analyticsError = computed(() => (
    form.google_analytics && !/^(G|UA|GT|AW)-[A-Z0-9-]+$/i.test(form.google_analytics) ? t('settings_invalid_analytics') : ''
));

/** Problems the browser can spot, keyed like server errors so they share the UI. */
const clientErrors = () => {
    const errors = [];
    if (emailError.value) errors.push({ field: 'contact_email', section: 'contact', message: `${t('email')}: ${emailError.value}` });
    SOCIAL_KEYS.forEach((key) => {
        const error = urlError(form[key]);
        if (error) errors.push({ field: key, section: 'social', message: `${socialFields.value.find((f) => f.key === key)?.label}: ${error}` });
    });
    if (analyticsError.value) errors.push({ field: 'google_analytics', section: 'seo', message: `Google Analytics: ${analyticsError.value}` });
    branches.value.forEach((branch, index) => {
        const error = urlError(branch.map_url);
        if (error) errors.push({ field: 'contact_branches', section: 'contact', message: `${t('settings_branch_map')} (${index + 1}): ${error}` });
    });
    return errors;
};

const openLink = (url) => {
    if (isUrl(url)) window.open(url, '_blank', 'noopener');
};

/* ---- Section data ---- */

const socialFields = computed(() => [
    { key: 'facebook', label: t('facebook'), icon: 'fab fa-facebook-f', color: '#1877f2', placeholder: 'https://www.facebook.com/…' },
    { key: 'instagram', label: t('instagram'), icon: 'fab fa-instagram', color: '#e1306c', placeholder: 'https://www.instagram.com/…' },
    { key: 'twitter', label: t('twitter_x'), icon: 'fab fa-twitter', color: '#0f172a', placeholder: 'https://x.com/…' },
    { key: 'youtube', label: t('youtube'), icon: 'fab fa-youtube', color: '#ff0000', placeholder: 'https://youtube.com/@…' },
    { key: 'linkedin', label: t('linkedin'), icon: 'fab fa-linkedin-in', color: '#0a66c2', placeholder: 'https://linkedin.com/company/…' },
]);

const notificationToggles = computed(() => [
    { key: 'email_notifications', label: t('email_notifications'), hint: t('settings_notify_email_hint'), icon: Message },
    { key: 'sms_notifications', label: t('sms_notifications'), hint: t('settings_notify_sms_hint'), icon: ChatDotRound },
    { key: 'push_notifications', label: t('push_notifications'), hint: t('settings_notify_push_hint'), icon: Iphone },
    { key: 'system_notifications', label: t('internal_system_alerts'), hint: t('settings_notify_system_hint'), icon: Monitor },
]);

/* ---- Search-result preview ---- */

const siteHost = typeof window !== 'undefined' ? window.location.host : '';
const truncate = (text, max) => (text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text);
const serpSiteName = computed(() => (seoLang.value === 'en' ? form.site_name_en || form.site_name : form.site_name || form.site_name_en) || siteHost);
const serpTitle = computed(() => truncate(
    (seoLang.value === 'en' ? form.meta_title_en || form.site_name_en : form.meta_title || form.site_name) || t('settings_serp_title_placeholder'),
    60,
));
const serpDescription = computed(() => truncate(
    (seoLang.value === 'en' ? form.meta_description_en || form.site_description_en : form.meta_description || form.site_description) || t('settings_serp_desc_placeholder'),
    160,
));

/* ---- Storefront preview ---- */

const previewSiteName = computed(() => (locale.value === 'en' ? form.site_name_en || form.site_name : form.site_name || form.site_name_en) || t('site_fallback_name'));
const previewTagline = computed(() => (locale.value === 'en' ? form.site_tagline_en || form.site_tagline : form.site_tagline || form.site_tagline_en) || t('settings_preview_tagline'));
const previewPrice = computed(() => formatMoney(125000));
const heroJustify = computed(() => ({ right: 'flex-end', left: 'flex-start' }[form.theme_hero_align] || 'center'));
const mockHeroStyle = computed(() => {
    const overlay = Number(form.theme_hero_overlay_opacity) || 0.5;
    const shade = `linear-gradient(rgba(13,27,42,${overlay}), rgba(13,27,42,${overlay}))`;
    return {
        background: heroBgPreview.value ? `${shade}, url("${heroBgPreview.value}") center / cover` : `${shade}, linear-gradient(135deg, #0d1b2a, #162a45)`,
        textAlign: form.theme_hero_align || 'center',
    };
});

const isPaletteActive = (palette) => (
    String(form.theme_primary_color).toLowerCase() === palette.primary.toLowerCase()
    && String(form.theme_navbar_bg_color).toLowerCase() === palette.navbar_bg.toLowerCase()
    && String(form.theme_footer_bg_color).toLowerCase() === palette.footer_bg.toLowerCase()
);

watch(
    () => settingsStore.data,
    (settings) => {
        loadSettings(settings);
    },
    { immediate: true }
);

const fetchSettings = async () => {
    try {
        await Promise.all([settingsStore.fetch(), loadManagedCurrencies()]);
        if (settingsStore.currencies?.length) {
            applyCurrencyPayload({
                currencies: settingsStore.currencies,
                base_currency: settingsStore.baseCurrency,
            });
        }
    } catch (error) {
        ElMessage.error(window.t('an_error_occurred_while_fetching'));
    } finally {
        initialLoading.value = false;
    }
};

// Type and size are checked by ImageDropzone before it emits.
const onFileSelect = (file, field) => {
    if (!file) {
        return;
    }

    const previewUrl = URL.createObjectURL(file);
    objectUrls.push(previewUrl);
    pendingFiles[field] = file.name;

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
    } else if (field === 'stampImage') {
        stampImageFile.value = file;
        stampImagePreview.value = previewUrl;
    } else if (field === 'signatureImage') {
        signatureImageFile.value = file;
        signatureImagePreview.value = previewUrl;
    } else if (field === 'headerBanner') {
        headerBannerFile.value = file;
        headerBannerPreview.value = previewUrl;
    } else if (field === 'coverImage') {
        coverImageFile.value = file;
        coverImagePreview.value = previewUrl;
    }
};

const submitSettings = async () => {
    const localErrors = clientErrors();
    if (localErrors.length) {
        serverErrors.value = localErrors;
        activeSection.value = localErrors[0].section;
        ElMessage.error(t('settings_fix_errors'));
        return;
    }
    serverErrors.value = [];

    if (form.default_currency && form.default_currency !== initialBaseCurrency.value) {
        try {
            const confirmMsg = String(window.t('base_currency_change_confirm'))
                .replace('{code}', form.default_currency);
            await ElMessageBox.confirm(
                confirmMsg,
                window.t('base_currency_change_title'),
                {
                    type: 'warning',
                    confirmButtonText: t('settings_continue'),
                    cancelButtonText: window.t('cancel'),
                },
            );
        } catch {
            form.default_currency = initialBaseCurrency.value;
            return;
        }
    }

    submitting.value = true;

    try {
        const formData = new FormData();

        // The legacy address text follows the branches it was replaced by.
        if (branches.value.length) {
            form.address = branchesAsText('ar');
            form.address_en = branchesAsText('en');
        }

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
        if (stampImageFile.value) {
            formData.append('stamp_image', stampImageFile.value);
        }
        if (signatureImageFile.value) {
            formData.append('signature_image', signatureImageFile.value);
        }
        if (headerBannerFile.value) {
            formData.append('header_banner', headerBannerFile.value);
        }
        if (coverImageFile.value) {
            formData.append('cover_image', coverImageFile.value);
        }

        const response = await settingsStore.save(formData);

        if (response?.data?.success) {
            ElMessage.success(window.t('settings_have_been_saved_successfully'));
            const payload = response.data.data || {};
            applyCurrencyPayload(payload);
            loadSettings(payload.settings || settingsStore.data);

            if (window.systemData) {
                window.systemData.settings = {
                    ...(window.systemData.settings || {}),
                    default_currency: payload.base_currency || form.default_currency,
                };
                window.systemData.currencies = {
                    base: payload.base_currency || form.default_currency,
                    list: payload.currencies || managedCurrencies.value,
                };
            }
        } else {
            ElMessage.error(response?.data?.message || window.t('failed_to_save_settings'));
        }
    } catch (error) {
        // Laravel reports `settings.facebook`-style keys; point each at its field and section.
        const errors = error.response?.data?.errors || {};
        serverErrors.value = Object.entries(errors).map(([key, messages]) => {
            const field = key.replace(/^settings\./, '').replace(/^og_image$/, 'ogImage').replace(/^hero_bg$/, 'heroBg');
            return { field, section: sectionFor(field), message: Array.isArray(messages) ? messages[0] : messages };
        });
        if (serverErrors.value.length) {
            activeSection.value = serverErrors.value[0].section;
        }
        const message = error.response?.data?.message || window.t('failed_to_save_settings');
        ElMessage.error(message);
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    window.addEventListener('keydown', onKeydown);
    fetchSettings();
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    window.removeEventListener('keydown', onKeydown);
    objectUrls.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<style scoped>



.text-muted {
    color: #6b7280;
    font-size: 0.95rem;
}

.field-hint {
    margin: 0.4rem 0 0;
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.5;
}

.field-hint a {
    color: var(--el-color-primary);
}

.mb-4 {
    margin-bottom: 1rem;
}

.lang-switch-bar {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
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
    position: relative;
    font-family: inherit;
    text-align: start;
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
    top: 96px;
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

/* ================================================================
 * Layout
 * ================================================================ */
.settings-page {
    --st-accent: #0d9488;
    --st-border: var(--border-color, #e5e7eb);
    --st-text: var(--text-dark, #1e293b);
    --st-muted: var(--text-muted, #64748b);
    max-width: 1320px;
    margin: 0 auto;
}

.settings-page.has-savebar {
    padding-bottom: 88px;
}

.settings-top {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}

.settings-top h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--st-text);
}

.settings-top p {
    margin: 0.25rem 0 0;
    color: var(--st-muted);
    font-size: 0.9rem;
}

.save-state {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #047857;
    background: rgba(16, 185, 129, 0.1);
}

.save-state.dirty {
    color: #b45309;
    background: rgba(245, 158, 11, 0.12);
}

.settings-layout {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 1.25rem;
    align-items: start;
}

/* ---- Section nav ---- */
.settings-nav {
    position: sticky;
    top: 96px;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.75rem;
    border-radius: 16px;
    background: #fff;
    border: 1px solid var(--st-border);
}

.nav-group-label {
    padding: 0.8rem 0.6rem 0.35rem;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.nav-group-label:first-child {
    padding-top: 0.2rem;
}

.nav-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    width: 100%;
    padding: 0.55rem 0.6rem;
    border: 1px solid transparent;
    border-radius: 11px;
    background: transparent;
    font-family: inherit;
    text-align: start;
    cursor: pointer;
    transition: background 0.18s ease, border-color 0.18s ease;
}

.nav-item:hover {
    background: #f8fafc;
}

.nav-item:focus-visible {
    outline: 2px solid var(--st-accent);
    outline-offset: 1px;
}

.nav-item.active {
    background: rgba(13, 148, 136, 0.08);
    border-color: rgba(13, 148, 136, 0.25);
}

.nav-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    background: #f1f5f9;
    transition: color 0.18s ease, background 0.18s ease;
}

.nav-item.active .nav-icon {
    color: #fff;
    background: var(--st-accent);
}

.nav-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.nav-text strong {
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--st-text);
}

.nav-text small {
    font-size: 0.72rem;
    color: var(--st-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-badge {
    flex-shrink: 0;
}

.nav-badge.dirty {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
}

.nav-badge.error {
    width: 18px;
    height: 18px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
    color: #fff;
    background: #ef4444;
}

/* ---- Content ---- */
.settings-content {
    min-width: 0;
    padding: 1.4rem 1.6rem 1.6rem;
    border-radius: 16px;
    background: #fff;
    border: 1px solid var(--st-border);
}

.section-head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding-bottom: 1.1rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid var(--st-border);
}

.section-head-icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: var(--st-accent);
    background: rgba(13, 148, 136, 0.1);
}

.section-head h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--st-text);
}

.section-head p {
    margin: 0.15rem 0 0;
    font-size: 0.83rem;
    color: var(--st-muted);
}

.settings-section :deep(.el-form-item__label) {
    font-weight: 600;
    color: var(--st-text);
}

.lang-switch-bar {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 1.1rem;
}

.w-full {
    width: 100%;
}

.sub-head {
    margin: 1.75rem 0 0.9rem;
    padding-top: 1.25rem;
    border-top: 1px dashed var(--st-border);
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--st-text);
}

/* ── Branches ── */
.branches-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    margin-top: 1.75rem;
    padding-top: 1.25rem;
    border-top: 1px dashed var(--st-border);
}

.branches-head .sub-head {
    margin: 0;
    padding: 0;
    border: none;
}

.branches-hint {
    margin: 0.3rem 0 0;
    font-size: 0.82rem;
    color: var(--st-muted);
}

.branches-empty {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-top: 1rem;
    padding: 1.1rem 1rem;
    border: 1px dashed var(--st-border);
    border-radius: 12px;
    color: var(--st-muted);
    font-size: 0.88rem;
}

.branch-card {
    margin-top: 1rem;
    padding: 0.9rem 1rem 0.2rem;
    border: 1px solid var(--st-border);
    border-radius: 14px;
}

.branch-card.is-main {
    border-color: color-mix(in srgb, var(--st-accent) 45%, var(--st-border));
    box-shadow: inset 3px 0 0 var(--st-accent);
}

[dir='rtl'] .branch-card.is-main {
    box-shadow: inset -3px 0 0 var(--st-accent);
}

.branch-card-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 0.6rem;
    margin-bottom: 0.75rem;
}

.branch-index {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--st-accent) 12%, transparent);
    color: var(--st-accent);
    font-size: 0.75rem;
    font-weight: 800;
}

.branch-title {
    font-size: 0.92rem;
    color: var(--st-text);
}

.branch-tools {
    display: flex;
    align-items: center;
    gap: 0.1rem;
    margin-inline-start: auto;
}

.settings-section > .el-form > .sub-head:first-child {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

.sub-hint {
    margin: 0 0 0.75rem;
    font-size: 0.83rem;
    color: var(--st-muted);
}

.code-input :deep(textarea) {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.82rem;
    background: #0f172a;
    color: #e2e8f0;
}

/* ---- Errors ---- */
.errors-alert {
    margin-bottom: 1.25rem;
}

.error-list {
    margin: 0.35rem 0 0;
    padding-inline-start: 1.1rem;
}

.error-link {
    padding: 0;
    border: none;
    background: none;
    font: inherit;
    color: inherit;
    text-decoration: underline;
    cursor: pointer;
}

/* ---- Toggles ---- */
.toggle-list {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--st-border);
    border-radius: 14px;
    overflow: hidden;
    margin-top: 0.5rem;
}

.toggle-row {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.9rem 1rem;
    cursor: pointer;
    transition: background 0.15s ease;
}

.toggle-row + .toggle-row {
    border-top: 1px solid var(--st-border);
}

.toggle-row:hover {
    background: #f8fafc;
}

.toggle-row > span:not(.toggle-icon) {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.toggle-row strong {
    font-size: 0.88rem;
    color: var(--st-text);
}

.toggle-row small {
    font-size: 0.76rem;
    color: var(--st-muted);
}

.toggle-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--st-accent);
    background: rgba(13, 148, 136, 0.1);
}

/* ---- Social ---- */
.social-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 1.25rem;
}

.social-icon {
    width: 16px;
    text-align: center;
    font-size: 0.95rem;
}

/* ---- SEO preview ---- */
.serp-preview {
    margin: 0.5rem 0 1.5rem;
    padding: 1rem 1.1rem;
    border-radius: 14px;
    border: 1px solid var(--st-border);
    background: #fff;
    box-shadow: 0 6px 18px -14px rgba(15, 23, 42, 0.4);
    font-family: Arial, sans-serif;
}

.serp-caption {
    display: block;
    margin-bottom: 0.6rem;
    font-family: inherit;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.serp-site {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.serp-favicon {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #f1f3f4;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.serp-favicon img {
    width: 18px;
    height: 18px;
    object-fit: contain;
}

.serp-site strong {
    display: block;
    font-size: 0.85rem;
    font-weight: 400;
    color: #202124;
}

.serp-site small {
    display: block;
    font-size: 0.75rem;
    color: #4d5156;
}

.serp-title {
    margin-top: 0.45rem;
    font-size: 1.15rem;
    line-height: 1.3;
    color: #1a0dab;
}

.serp-desc {
    margin-top: 0.2rem;
    font-size: 0.85rem;
    line-height: 1.55;
    color: #4d5156;
}

/* ---- Images ---- */
.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0 1.25rem;
}

/* ---- Palettes ---- */
.palette-card.selected {
    border-color: var(--st-accent);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.18);
}

.palette-check {
    position: absolute;
    top: 8px;
    inset-inline-end: 8px;
    color: var(--st-accent);
    font-size: 1rem;
}

.mock-hero {
    padding: 14px;
    border-radius: 4px;
}

.mock-hero-title {
    margin-bottom: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fff;
}

.mock-hero-buttons {
    display: flex;
    gap: 6px;
}

.mock-hero-buttons .mock-btn {
    padding: 4px 10px;
    font-size: 0.65rem;
    font-weight: 700;
    width: auto;
}

/* ---- Save bar ---- */
.savebar {
    position: fixed;
    bottom: 16px;
    inset-inline: calc(268px + 24px) 24px;
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.9rem 0.75rem 1.1rem;
    border-radius: 16px;
    color: #e2e8f0;
    background: #0f172a;
    box-shadow: 0 18px 40px -12px rgba(15, 23, 42, 0.55);
}

:global(.admin-main-wrapper.sidebar-collapsed) .savebar {
    inset-inline-start: calc(72px + 24px);
}

.savebar-text {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.savebar-text > span:last-child {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.savebar-text strong {
    font-size: 0.9rem;
    color: #fff;
}

.savebar-text small {
    font-size: 0.75rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.savebar-dot {
    flex-shrink: 0;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
}

.savebar-actions {
    flex-shrink: 0;
    display: flex;
    gap: 0.5rem;
}

.savebar-actions .el-button {
    margin: 0;
}

.savebar-actions .el-button:not(.el-button--primary) {
    --el-button-bg-color: transparent;
    --el-button-border-color: rgba(255, 255, 255, 0.2);
    --el-button-text-color: #e2e8f0;
    --el-button-hover-bg-color: rgba(255, 255, 255, 0.08);
    --el-button-hover-border-color: rgba(255, 255, 255, 0.35);
    --el-button-hover-text-color: #fff;
}

.kbd {
    margin-inline-start: 0.5rem;
    padding: 0.05rem 0.35rem;
    border-radius: 5px;
    background: rgba(255, 255, 255, 0.2);
    font-family: inherit;
    font-size: 0.68rem;
}

.savebar-enter-active,
.savebar-leave-active {
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.savebar-enter-from,
.savebar-leave-to {
    transform: translateY(20px);
    opacity: 0;
}

/* ---- Responsive ---- */
@media (max-width: 1100px) {
    .settings-layout {
        grid-template-columns: 1fr;
    }

    /* The section list becomes a scrollable row of chips. */
    .settings-nav {
        position: static;
        flex-direction: row;
        overflow-x: auto;
        gap: 0.4rem;
        padding: 0.5rem;
        scrollbar-width: thin;
    }

    .nav-group-label {
        display: none;
    }

    .nav-item {
        flex-shrink: 0;
        width: auto;
    }

    .nav-text small {
        display: none;
    }
}

@media (max-width: 992px) {
    .savebar {
        inset-inline: 12px;
    }
}

@media (max-width: 768px) {
    .settings-content {
        padding: 1rem;
    }

    .social-grid {
        grid-template-columns: 1fr;
    }

    .savebar {
        flex-direction: column;
        align-items: stretch;
        bottom: 8px;
        inset-inline: 8px;
    }

    .savebar-actions .el-button {
        flex: 1;
    }

    .kbd {
        display: none;
    }
}
</style>
