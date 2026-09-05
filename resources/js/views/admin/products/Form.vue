<template>
    <div class="products-form">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <div class="header-left">
                        <h2 class="page-title">{{ isEdit ? $t('modify_the_product') : $t('add_a_new_product') }}</h2>
                        <el-tag v-if="isEdit" type="info" effect="plain">ID: {{ route.params.id }}</el-tag>
                    </div>
                    <el-button :icon="ArrowRight" @click="goBack">{{ $t('back') }}</el-button>
                </div>
            </template>

            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-position="top"
                class="product-form"
            >
                <el-tabs v-model="activeTab" type="border-card" stretch>
                    <!-- ============ BASIC DATA ============ -->
                    <el-tab-pane :label="$t('basic_data')" name="basic">
                        <!-- 1. Product identity -->
                        <div class="form-section">
                            <div class="section-head">
                                <el-icon><Box /></el-icon>
                                <h3>{{ $t('product_identity') }}</h3>
                            </div>
                            <el-row :gutter="24">
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('product_name_arabic')" prop="name_ar">
                                        <el-input v-model="form.name_ar" :placeholder="$t('product_name_in_arabic')" size="large" @input="syncSlug" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('product_name_english')" prop="name_en">
                                        <el-input v-model="form.name_en" placeholder="Product name in English" size="large" @input="syncSlug" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-row :gutter="24">
                                <el-col :xs="24" :md="8">
                                    <el-form-item prop="slug">
                                        <template #label>
                                            <span class="field-label">
                                                Slug
                                                <el-tooltip :content="$t('slug_is_the_public_url')" placement="top">
                                                    <el-icon class="field-hint-icon"><InfoFilled /></el-icon>
                                                </el-tooltip>
                                                <el-tag size="small" type="info" effect="plain" round>{{ $t('optional') }}</el-tag>
                                            </span>
                                        </template>
                                        <el-input
                                            v-model="form.slug"
                                            :placeholder="slugPlaceholder"
                                            size="large"
                                            @input="slugTouched = true"
                                        >
                                            <template #append>
                                                <el-tooltip :content="$t('regenerate_from_the_name')" placement="top">
                                                    <el-button :icon="RefreshRight" :disabled="!slugSource" @click="regenerateSlug" />
                                                </el-tooltip>
                                            </template>
                                        </el-input>
                                        <span class="form-help">{{ $t('leave_blank_to_generate_automatically') }}</span>
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="8">
                                    <el-form-item prop="sku">
                                        <template #label>
                                            <span class="field-label">
                                                {{ $t('product_code_sku') }}
                                                <el-tag size="small" type="info" effect="plain" round>{{ $t('optional') }}</el-tag>
                                            </span>
                                        </template>
                                        <el-input v-model="form.sku" :placeholder="$t('no_code')" size="large" clearable>
                                            <template #append>
                                                <el-tooltip :content="$t('generate_a_code')" placement="top">
                                                    <el-button :icon="MagicStick" :loading="skuLoading" @click="generateSku" />
                                                </el-tooltip>
                                            </template>
                                        </el-input>
                                        <span class="form-help">{{ $t('sku_is_optional_hint') }}</span>
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="8">
                                    <el-form-item :label="$t('category')" prop="category_id">
                                        <el-select v-model="form.category_id" :placeholder="$t('select_category')" size="large" style="width:100%" filterable>
                                            <el-option
                                                v-for="cat in categories"
                                                :key="cat.id"
                                                :label="cat.name_ar || cat.name"
                                                :value="cat.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-row :gutter="24">
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('brand')">
                                        <el-input v-model="form.brand" placeholder="Brand" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('model')">
                                        <el-input v-model="form.model" placeholder="Model" size="large" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>

                        <!-- 2. Pricing -->
                        <el-divider />
                        <div class="form-section">
                            <div class="section-head">
                                <el-icon><PriceTag /></el-icon>
                                <h3>{{ $t('pricing') }}</h3>
                            </div>
                            <el-row :gutter="24">
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('the_price')" prop="price">
                                        <el-input-number v-model="form.price" :min="0" :precision="2" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('cost_price')">
                                        <el-input-number v-model="form.cost_price" :min="0" :precision="2" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('discounted_price')">
                                        <el-input-number v-model="form.sale_price" :min="0" :precision="2" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('currency')">
                                        <el-select v-model="form.currency" size="large" style="width:100%">
                                            <el-option
                                                v-for="c in currencyOptions"
                                                :key="c.code"
                                                :label="c.label"
                                                :value="c.code"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-row :gutter="24">
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('tax_rate')">
                                        <el-input-number v-model="form.tax_rate" :min="0" :max="100" :precision="2" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('taxable')">
                                        <el-switch v-model="form.taxable" :active-text="$t('yes')" :inactive-text="$t('no')" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('view_price')">
                                        <el-switch v-model="form.show_price" :active-text="$t('yes')" :inactive-text="$t('no')" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('unit_of_measurement')">
                                        <el-select v-model="form.unit" :placeholder="$t('select_unit')" size="large" style="width:100%" allow-create filterable>
                                            <el-option :label="$t('piece')" value="piece" />
                                            <el-option :label="$t('kilogram_kg')" value="kg" />
                                            <el-option :label="$t('meter')" value="meter" />
                                            <el-option :label="$t('box')" value="box" />
                                            <el-option :label="$t('dozen')" value="dozen" />
                                            <el-option :label="$t('package')" value="pack" />
                                            <el-option :label="$t('liter')" value="liter" />
                                            <el-option :label="$t('cylinder')" value="carton" />
                                        </el-select>
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>

                        <!-- 3. Inventory & stock -->
                        <el-divider />
                        <div class="form-section">
                            <div class="section-head">
                                <el-icon><Goods /></el-icon>
                                <h3>{{ $t('inventory_and_stock') }}</h3>
                            </div>
                            <el-alert v-if="isEdit && variants.length > 0" :title="$t('stock_managed_per_variant_help')" type="info" :closable="false" show-icon class="section-alert" />
                            <el-alert v-else :title="$t('base_stock_help')" type="info" :closable="false" show-icon class="section-alert" />
                            <el-row :gutter="24">
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('stock_quantity')" prop="stock_quantity">
                                        <el-input-number v-model="form.stock_quantity" :min="0" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('minimum')">
                                        <el-input-number v-model="form.min_stock" :min="0" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('maximum')">
                                        <el-input-number v-model="form.max_stock" :min="0" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('reorder_point')">
                                        <el-input-number v-model="form.reorder_point" :min="0" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>

                        <!-- 4. Specifications -->
                        <el-divider />
                        <div class="form-section">
                            <div class="section-head">
                                <el-icon><Cpu /></el-icon>
                                <h3>{{ $t('specifications') }}</h3>
                            </div>
                            <el-row :gutter="24">
                                <el-col :xs="24" :md="8">
                                    <el-form-item :label="$t('barcode')">
                                        <el-input v-model="form.barcode" placeholder="Barcode" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="8">
                                    <el-form-item :label="$t('the_color')">
                                        <el-input v-model="form.color" placeholder="Color" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="8">
                                    <el-form-item :label="$t('size_size')">
                                        <el-input v-model="form.size" placeholder="Size" size="large" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-row :gutter="24">
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('weight_kg')">
                                        <el-input-number v-model="form.weight" :min="0" :precision="2" :step="0.1" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('length_cm')">
                                        <el-input-number v-model="form.length" :min="0" :precision="2" :step="0.1" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('width_cm')">
                                        <el-input-number v-model="form.width" :min="0" :precision="2" :step="0.1" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="6">
                                    <el-form-item :label="$t('height_cm')">
                                        <el-input-number v-model="form.height" :min="0" :precision="2" :step="0.1" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-row :gutter="24">
                                <el-col :xs="24" :md="8">
                                    <el-form-item :label="$t('sort_order')">
                                        <el-input-number v-model="form.sort_order" :min="0" size="large" style="width:100%" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>

                        <!-- 5. Descriptions -->
                        <el-divider />
                        <div class="form-section">
                            <div class="section-head">
                                <el-icon><Document /></el-icon>
                                <h3>{{ $t('about_the_product') }}</h3>
                            </div>
                            <el-row :gutter="24">
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('short_description_arabic')">
                                        <el-input v-model="form.short_description_ar" type="textarea" :rows="3" maxlength="500" show-word-limit :placeholder="$t('brief_description_in_arabic')" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :md="12">
                                    <el-form-item :label="$t('short_description_english')">
                                        <el-input v-model="form.short_description_en" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="Short description in English" />
                                    </el-form-item>
                                </el-col>
                            </el-row>

                            <el-form-item :label="$t('description_arabic')" prop="description_ar">
                                <el-input v-model="form.description_ar" type="textarea" :rows="4" :placeholder="$t('product_description_in_arabic')" />
                            </el-form-item>

                            <el-form-item :label="$t('description_english')">
                                <el-input v-model="form.description_en" type="textarea" :rows="4" placeholder="Product description in English" />
                            </el-form-item>
                        </div>

                        <!-- 6. Status -->
                        <el-divider />
                        <div class="form-section">
                            <div class="section-head">
                                <el-icon><CircleCheck /></el-icon>
                                <h3>{{ $t('status') }}</h3>
                            </div>
                            <el-row :gutter="24">
                                <el-col :xs="24" :sm="8">
                                    <el-form-item :label="$t('status')">
                                        <el-switch v-model="form.is_active" :active-text="$t('active')" :inactive-text="$t('inactive')" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :sm="8">
                                    <el-form-item :label="$t('distinctive_product')">
                                        <el-switch v-model="form.is_featured" :active-text="$t('distinct')" :inactive-text="$t('indiscriminate')" size="large" />
                                    </el-form-item>
                                </el-col>
                                <el-col :xs="24" :sm="8">
                                    <el-form-item :label="$t('in_stock')">
                                        <el-switch v-model="form.in_stock" :active-text="$t('available')" :inactive-text="$t('run_out')" size="large" />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>
                    </el-tab-pane>

                    <!-- ============ VARIANTS ============ -->
                    <el-tab-pane name="variants">
                        <template #label>
                            <span class="tab-label">
                                <span>{{ $t('variants') }}</span>
                                <el-tag v-if="isEdit && variants.length" size="small" round type="primary" effect="light" class="tab-tag">
                                    {{ variants.length }}
                                </el-tag>
                            </span>
                        </template>
                        <div v-if="!isEdit" class="variants-create-hint">
                            <el-empty :description="$t('save_product_before_variants')" :image-size="80" />
                        </div>

                        <template v-else>
                            <el-alert :title="$t('variants_help')" type="info" :closable="false" show-icon class="section-alert" />

                            <div class="variants-toolbar">
                                <el-button type="primary" size="large" :icon="Plus" @click="openVariantDialog()">
                                    {{ $t('add_variant') }}
                                </el-button>
                                <span v-if="variants.length" class="variants-count">
                                    {{ $t('variant_count', { count: variants.length }) }}
                                </span>
                            </div>

                            <el-table
                                :data="variants"
                                v-loading="variantsLoading"
                                stripe
                                empty-text="—"
                                class="variants-table"
                            >
                                <el-table-column prop="sku" label="SKU" min-width="150" show-overflow-tooltip>
                                    <template #default="{ row }">
                                        <span class="variant-sku">{{ row.sku || '—' }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="barcode" label="Barcode" min-width="120">
                                    <template #default="{ row }">{{ row.barcode || '—' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('size')" min-width="90">
                                    <template #default="{ row }">{{ row.size || '—' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('the_color')" min-width="110">
                                    <template #default="{ row }">
                                        <span v-if="row.color" class="variant-color-tag">
                                            <span class="color-dot" :style="{ background: row.color }"></span>
                                            {{ row.color }}
                                        </span>
                                        <span v-else>—</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('material')" min-width="100">
                                    <template #default="{ row }">{{ row.material || '—' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('the_price')" min-width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="variant-price">{{ formatMoney(row.price) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('cost_price')" min-width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="variant-cost">{{ row.cost_price != null ? formatMoney(row.cost_price) : '—' }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('stock_quantity')" min-width="110" align="right">
                                    <template #default="{ row }">
                                        <el-tag v-if="(row.stock_quantity ?? 0) <= 0" type="danger" size="small" effect="plain">
                                            {{ row.stock_quantity ?? 0 }}
                                        </el-tag>
                                        <el-tag v-else type="success" size="small" effect="plain">
                                            {{ row.stock_quantity }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('actions')" width="120" align="center" fixed="right">
                                    <template #default="{ row }">
                                        <el-tooltip :content="$t('common.edit')" placement="top">
                                            <el-button size="small" circle plain :icon="EditPen" @click="openVariantDialog(row)" />
                                        </el-tooltip>
                                        <el-tooltip :content="$t('common.delete')" placement="top">
                                            <el-button size="small" circle plain type="danger" :icon="Delete" @click="confirmRemoveVariant(row)" />
                                        </el-tooltip>
                                    </template>
                                </el-table-column>
                                <template #empty>
                                    <el-empty :description="$t('no_variants_yet')" :image-size="70">
                                        <el-button type="primary" size="small" :icon="Plus" @click="openVariantDialog()">
                                            {{ $t('add_variant') }}
                                        </el-button>
                                    </el-empty>
                                </template>
                            </el-table>
                        </template>
                    </el-tab-pane>

                    <!-- ============ IMAGES ============ -->
                    <el-tab-pane :label="$t('the_pictures')" name="images">
                        <el-form-item :label="$t('main_image')">
                            <div class="image-upload-wrapper">
                                <el-upload
                                    ref="mainImageUpload"
                                    v-loading="mainUploading"
                                    class="main-image-uploader"
                                    drag
                                    :action="uploadUrl"
                                    :data="{ slug: form.slug || 'product' }"
                                    :show-file-list="false"
                                    :on-success="handleMainImageSuccess"
                                    :on-error="handleUploadError"
                                    :on-progress="handleMainImageProgress"
                                    :before-upload="beforeUpload"
                                    :headers="uploadHeaders"
                                    name="file"
                                    accept="image/*"
                                >
                                    <div v-if="form.image_main" class="main-image-preview">
                                        <el-image :src="form.image_main" fit="cover" style="width:100%;height:100%">
                                            <template #error>
                                                <div class="image-broken">
                                                    <el-icon :size="28"><PictureFilled /></el-icon>
                                                    <span>{{ $t('image_failed_to_load') }}</span>
                                                </div>
                                            </template>
                                        </el-image>
                                        <div class="image-overlay">
                                            <el-icon :size="24"><EditPen /></el-icon>
                                            <span>{{ $t('change_the_picture') }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="upload-placeholder">
                                        <el-icon :size="40"><Plus /></el-icon>
                                        <span>{{ $t('upload_the_main_image') }}</span>
                                        <span class="upload-hint">{{ $t('drag_a_file_or_click_to_upload') }}</span>
                                        <span class="upload-hint">{{ $t('image_upload_limits_hint') }}</span>
                                    </div>
                                </el-upload>
                                <div class="image-meta">
                                    <el-text v-if="form.image_main" size="small" truncated class="image-path" :title="form.image_main">
                                        {{ mainImageName }}
                                    </el-text>
                                    <el-button
                                        v-if="form.image_main"
                                        type="danger"
                                        size="small"
                                        plain
                                        :icon="Delete"
                                        @click="removeMainImage"
                                    >
                                        {{ $t('delete_the_image') }}
                                    </el-button>
                                </div>
                            </div>
                        </el-form-item>

                        <el-divider />

                        <el-form-item>
                            <template #label>
                                <span class="gallery-label">
                                    {{ $t('photo_gallery') }}
                                    <el-tag v-if="galleryFiles.length" size="small" type="info" round>
                                        {{ galleryFiles.length }}
                                    </el-tag>
                                </span>
                            </template>
                            <div class="gallery-wrapper">
                                <el-upload
                                    ref="galleryUpload"
                                    v-model:file-list="galleryFiles"
                                    class="gallery-uploader"
                                    :action="uploadUrl"
                                    :data="{ slug: form.slug || 'product' }"
                                    list-type="picture-card"
                                    :on-success="handleGallerySuccess"
                                    :on-error="handleGalleryError"
                                    :on-remove="removeGalleryFile"
                                    :headers="uploadHeaders"
                                    :before-upload="beforeUpload"
                                    name="file"
                                    multiple
                                    accept="image/*"
                                >
                                    <div class="gallery-add-btn">
                                        <el-icon :size="28"><Plus /></el-icon>
                                        <span>{{ $t('add_a_photo') }}</span>
                                    </div>

                                    <!-- The default card offers preview + delete only; promoting a
                                         gallery shot to the main image used to mean re-uploading it. -->
                                    <template #file="{ file }">
                                        <div class="gallery-item" :class="{ 'is-main': isMainImage(file) }">
                                            <img v-if="file.url" :src="file.url" class="gallery-thumb" alt="">
                                            <el-progress
                                                v-if="file.status === 'uploading'"
                                                type="circle"
                                                :width="72"
                                                :percentage="Math.round(file.percentage || 0)"
                                            />
                                            <el-tag v-if="isMainImage(file)" class="gallery-main-tag" size="small" type="success" effect="dark">
                                                {{ $t('main_image') }}
                                            </el-tag>
                                            <div v-if="file.status !== 'uploading'" class="gallery-actions">
                                                <el-tooltip :content="$t('image_preview')" placement="top">
                                                    <el-icon @click.stop="handleGalleryPreview(file)"><ZoomIn /></el-icon>
                                                </el-tooltip>
                                                <el-tooltip :content="$t('set_as_main_image')" placement="top">
                                                    <el-icon @click.stop="setAsMainImage(file)"><Star /></el-icon>
                                                </el-tooltip>
                                                <el-tooltip :content="$t('delete_the_image')" placement="top">
                                                    <el-icon @click.stop="removeGalleryFile(file)"><Delete /></el-icon>
                                                </el-tooltip>
                                            </div>
                                        </div>
                                    </template>
                                </el-upload>
                                <el-text size="small" type="info">{{ $t('image_upload_limits_hint') }}</el-text>
                            </div>
                        </el-form-item>

                        <el-dialog v-model="previewDialogVisible" :title="$t('image_preview')" width="600px">
                            <el-image :src="previewImageUrl" fit="contain" style="width:100%;max-height:500px" />
                        </el-dialog>
                    </el-tab-pane>

                    <!-- ============ SEO ============ -->
                    <el-tab-pane :label="$t('search_engine_optimization_seo')" name="seo">
                        <el-alert :title="$t('seo_data_helps_the_product')" type="info" :closable="false" show-icon class="mb-4" />

                        <el-form-item :label="$t('page_title_meta_title')">
                            <el-input v-model="form.seo_title" :placeholder="$t('seo_title_an_attractive_title')" size="large" maxlength="70" show-word-limit />
                        </el-form-item>
                        <el-form-item :label="$t('meta_description')">
                            <el-input v-model="form.seo_description" type="textarea" :rows="3" :placeholder="$t('a_short_description_appears_in')" maxlength="320" show-word-limit />
                        </el-form-item>
                        <el-form-item :label="$t('meta_keywords')">
                            <el-input v-model="form.seo_keywords" placeholder="keyword1, keyword2, keyword3" size="large" />
                            <div class="form-help">{{ $t('separate_keywords_with_a_comma') }}</div>
                        </el-form-item>
                    </el-tab-pane>
                </el-tabs>

                <!-- Variant create / edit dialog -->
                <el-dialog
                    v-model="variantDialogVisible"
                    :title="variantDialogTitle"
                    width="640px"
                    :close-on-click-modal="false"
                    append-to-body
                >
                    <el-form
                        ref="variantFormRef"
                        :model="variantForm"
                        :rules="variantRules"
                        label-position="top"
                    >
                        <el-row :gutter="16">
                            <el-col :xs="24" :sm="12">
                                <el-form-item :label="$t('product_code_sku')" prop="sku">
                                    <el-input v-model="variantForm.sku" placeholder="SKU-001" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :sm="12">
                                <el-form-item :label="$t('barcode')">
                                    <el-input v-model="variantForm.barcode" placeholder="Barcode" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :xs="24" :sm="8">
                                <el-form-item :label="$t('size')">
                                    <el-input v-model="variantForm.size" :placeholder="$t('size')" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :sm="8">
                                <el-form-item :label="$t('the_color')">
                                    <el-input v-model="variantForm.color" :placeholder="$t('the_color')" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :sm="8">
                                <el-form-item :label="$t('material')">
                                    <el-input v-model="variantForm.material" :placeholder="$t('material')" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :xs="24" :sm="8">
                                <el-form-item :label="$t('the_price')" prop="price">
                                    <el-input-number v-model="variantForm.price" :min="0" :precision="5" :step="0.01" style="width:100%" controls-position="right" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :sm="8">
                                <el-form-item :label="$t('cost_price')">
                                    <el-input-number v-model="variantForm.cost_price" :min="0" :precision="5" :step="0.01" style="width:100%" controls-position="right" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :sm="8">
                                <el-form-item :label="$t('stock_quantity')" prop="stock_quantity">
                                    <el-input-number v-model="variantForm.stock_quantity" :min="0" :precision="0" style="width:100%" controls-position="right" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                    <template #footer>
                        <el-button size="large" @click="variantDialogVisible = false">{{ $t('common.cancel') }}</el-button>
                        <el-button size="large" type="primary" :loading="variantSaving" :icon="Check" @click="saveVariant">
                            {{ $t('common.save') }}
                        </el-button>
                    </template>
                </el-dialog>

                <div class="form-actions">
                    <el-button size="large" @click="goBack">{{ $t('cancel') }}</el-button>
                    <el-button size="large" type="primary" :loading="submitting" :disabled="uploadsInProgress" @click="submitForm">
                        {{ isEdit ? $t('product_update') : $t('save_the_product') }}
                    </el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { baseCurrencyCode, currencyOptions as buildCurrencyOptions } from '@/utils/currency';
import { ref, computed, reactive, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { productsApi } from '@/api/products';
import { Box, Cpu, Check, CircleCheck, Document, Delete, EditPen, Goods, InfoFilled, MagicStick, PictureFilled, Plus, PriceTag, ArrowRight, RefreshRight, Star, ZoomIn } from '@element-plus/icons-vue';
import { isSameImage, resolveImageUrl, toImagePath } from '@/utils/productImages';

const router = useRouter();
const route = useRoute();
const store = useProductsStore();

const formRef = ref(null);
const activeTab = ref('basic');
const submitting = ref(false);
const uploadUrl = '/api/v1/upload';
const galleryFiles = ref([]);
const previewDialogVisible = ref(false);
const previewImageUrl = ref('');

const uploadHeaders = reactive({
    'Authorization': `Bearer ${localStorage.getItem('token') || ''}`,
    'Accept': 'application/json'
});

const currencyOptions = computed(() => buildCurrencyOptions());

const form = reactive({
    name_ar: '',
    name_en: '',
    slug: '',
    sku: '',
    barcode: '',
    category_id: '',
    price: 0,
    cost_price: 0,
    sale_price: null,
    currency: baseCurrencyCode(),
    show_price: true,
    tax_rate: 0,
    taxable: true,
    unit: 'piece',
    stock_quantity: 0,
    min_stock: 0,
    max_stock: null,
    reorder_point: 5,
    weight: null,
    length: null,
    width: null,
    height: null,
    color: '',
    size: '',
    sort_order: 0,
    brand: '',
    model: '',
    description_ar: '',
    description_en: '',
    short_description_ar: '',
    short_description_en: '',
    is_active: true,
    is_featured: false,
    in_stock: true,
    image_main: '',
    seo_title: '',
    seo_description: '',
    seo_keywords: ''
});

const rules = {
    name_ar: [{ required: true, message: window.t('product_name_in_arabic_is_required'), trigger: 'blur' }],
    // Only 147 of the 1,804 products carry an English name and only 444 a Latin
    // slug, so requiring either here did not describe the catalogue — it just
    // meant most products could not be saved from this form at all. Both are
    // optional now, and the slug is derived from whichever name exists.
    slug: [
        {
            // Arabic slugs are the norm in this catalogue, so the rule checks
            // for a URL-shaped value rather than for English.
            pattern: /^[\p{L}\p{N}-]+$/u,
            message: window.t('slug_must_not_contain_spaces'),
            trigger: 'blur',
        },
    ],
    category_id: [{ required: true, message: window.t('category_required'), trigger: 'change' }],
    price: [{ required: true, message: window.t('price_required'), trigger: 'blur' }],
    stock_quantity: [{ required: true, message: window.t('quantity_of_inventory_required'), trigger: 'blur' }]
};

const categories = computed(() => store.categories);
const isEdit = computed(() => !!route.params.id);

// ---- Variants ----
const variants = ref([]);
const variantsLoading = ref(false);
const variantDialogVisible = ref(false);
const variantSaving = ref(false);
const variantFormRef = ref(null);
const editingVariantId = ref(null);

const emptyVariantForm = () => ({
    sku: '',
    barcode: '',
    size: '',
    color: '',
    material: '',
    price: 0,
    cost_price: null,
    stock_quantity: 0
});

const variantForm = reactive(emptyVariantForm());

const variantRules = {
    sku: [{ required: true, message: window.t('sku_required'), trigger: 'blur' }],
    price: [{ required: true, message: window.t('price_required'), trigger: 'blur' }],
    stock_quantity: [{ required: true, message: window.t('quantity_of_inventory_required'), trigger: 'blur' }]
};

const variantDialogTitle = computed(() =>
    editingVariantId.value ? window.t('edit_variant') : window.t('add_variant')
);

// Variants are edited through the store (create/update/delete hit the API
// immediately); reflect the store's updated list back into the local table.
const syncVariantsFromStore = () => {
    if (store.currentProduct && Array.isArray(store.currentProduct.variants)) {
        variants.value = store.currentProduct.variants;
    }
};

const suggestVariantSku = () => {
    const base = (form.sku || '').trim() || `variant`;
    const prefix = base.includes('-') ? base.replace(/-\d+$/, '') : base;
    return `${prefix}-${variants.value.length + 1}`;
};

const openVariantDialog = (variant = null) => {
    editingVariantId.value = variant ? variant.id : null;
    Object.assign(variantForm, emptyVariantForm());
    if (variant) {
        Object.assign(variantForm, {
            sku: variant.sku || '',
            barcode: variant.barcode || '',
            size: variant.size || '',
            color: variant.color || '',
            material: variant.material || '',
            price: parseFloat(variant.price) || 0,
            cost_price: variant.cost_price != null ? parseFloat(variant.cost_price) : null,
            stock_quantity: variant.stock_quantity ?? 0
        });
    } else {
        variantForm.sku = suggestVariantSku();
        variantForm.price = form.price || 0;
        variantForm.cost_price = form.cost_price || null;
        variantForm.stock_quantity = form.stock_quantity || 0;
    }
    variantDialogVisible.value = true;
};

const saveVariant = async () => {
    if (!variantFormRef.value) return;
    try {
        await variantFormRef.value.validate();
    } catch {
        return;
    }

    variantSaving.value = true;
    const payload = {
        ...variantForm,
        price: variantForm.price ?? 0,
        stock_quantity: variantForm.stock_quantity ?? 0,
        cost_price: variantForm.cost_price || null
    };

    try {
        if (editingVariantId.value) {
            await store.updateVariant(editingVariantId.value, payload);
            ElMessage.success(window.t('variant_updated_successfully'));
        } else {
            await store.createVariant({ ...payload, product_id: Number(route.params.id) });
            ElMessage.success(window.t('variant_added_successfully'));
        }
        syncVariantsFromStore();
        variantDialogVisible.value = false;
    } catch (error) {
        if (error?.response?.data?.errors) {
            const errs = error.response.data.errors;
            Object.entries(errs).forEach(([field, messages]) => {
                ElMessage.error(`${field}: ${messages[0]}`);
            });
        } else {
            ElMessage.error(error?.response?.data?.message || window.t('failed_to_save_variant'));
        }
    } finally {
        variantSaving.value = false;
    }
};

const confirmRemoveVariant = (variant) => {
    ElMessageBox.confirm(
        window.t('confirm_delete_variant_message'),
        window.t('confirm_delete_variant'),
        {
            confirmButtonText: window.t('common.delete'),
            cancelButtonText: window.t('common.cancel'),
            type: 'warning',
            confirmButtonClass: 'el-button--danger'
        }
    )
        .then(async () => {
            try {
                await store.deleteVariant(variant.id);
                syncVariantsFromStore();
                ElMessage.success(window.t('variant_deleted_successfully'));
            } catch {
                ElMessage.error(window.t('failed_to_delete_variant'));
            }
        })
        .catch(() => {});
};

// ---- End variants ----

/**
 * Slug and SKU, kept out of the admin's way.
 *
 * The old helper derived a slug from `name_en` only, and `name_en` is blank on
 * 1,657 of the 1,804 products — so in practice the required slug field had to
 * be filled in by hand, in Latin, for a catalogue written in Arabic. It also
 * stripped every non-ASCII character, which turns an Arabic name into an empty
 * string. This mirrors the server's ProductIdentifiers::slugify() instead:
 * same rule on both sides, and the script is preserved.
 */
const slugify = (value) => (value || '')
    .toString()
    .trim()
    // Tatweel and the bidi marks a copy-paste drags in are invisible here but
    // would survive into the URL.
    .replace(/[\u0640\u200B-\u200F\u202A-\u202E]/gu, '')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, '-')
    .replace(/^-+|-+$/g, '');

const slugTouched = ref(false);
const skuLoading = ref(false);

const slugSource = computed(() => form.name_ar || form.name_en || '');

const slugPlaceholder = computed(() => slugify(slugSource.value) || 'product-slug');

/**
 * Follow the name while the admin has not written a slug themselves. Editing
 * an existing product never rewrites it: the slug is that product's public
 * URL, and quietly changing it breaks every link already pointing at it.
 */
const syncSlug = () => {
    if (isEdit.value || slugTouched.value) return;
    form.slug = slugify(slugSource.value);
};

const regenerateSlug = () => {
    form.slug = slugify(slugSource.value);
    slugTouched.value = false;
};

const generateSku = async () => {
    skuLoading.value = true;
    try {
        const { data } = await productsApi.nextSku();
        form.sku = data?.data?.sku || '';
    } catch {
        ElMessage.error(window.t('failed_to_generate_a_code'));
    } finally {
        skuLoading.value = false;
    }
};

const MAX_IMAGE_MB = 5;

const beforeUpload = (file) => {
    const isImage = file.type.startsWith('image/');
    // Matched to what the endpoint actually accepts (max:5120). The old 2MB
    // limit rejected files the server would have taken quite happily.
    const isWithinLimit = file.size / 1024 / 1024 < MAX_IMAGE_MB;

    if (!isImage) {
        ElMessage.error(window.t('only_photos_can_be_uploaded'));
        return false;
    }
    if (!isWithinLimit) {
        ElMessage.error(window.t('image_size_must_be_less_than_5mb'));
        return false;
    }
    return true;
};

const readUploadedPath = (response) => response?.data?.full_url || response?.data?.url || response?.url || null;

const mainUploading = ref(false);

const mainImageName = computed(() => {
    const path = toImagePath(form.image_main);
    return path ? path.split('/').pop() : '';
});

// Saving mid-upload used to persist the product without the picture that was
// still in flight, with no hint as to why it had gone missing.
const uploadsInProgress = computed(
    () => mainUploading.value || galleryFiles.value.some(f => f.status === 'uploading')
);

const handleMainImageProgress = () => {
    mainUploading.value = true;
};

const handleUploadError = (error) => {
    mainUploading.value = false;
    ElMessage.error(uploadErrorMessage(error));
};

const uploadErrorMessage = (error) => {
    // el-upload hands back an Error whose message is the raw response body.
    try {
        const body = JSON.parse(error?.message ?? '');
        const firstError = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
        return firstError || body?.message || window.t('failed_to_upload_image');
    } catch {
        return window.t('failed_to_upload_image');
    }
};

const handleMainImageSuccess = (response) => {
    mainUploading.value = false;
    const imageUrl = readUploadedPath(response);

    if (imageUrl) {
        form.image_main = resolveImageUrl(imageUrl);
        ElMessage.success(window.t('the_main_image_has_been'));
    } else {
        ElMessage.error(window.t('failed_to_upload_image'));
    }
};

const removeMainImage = () => {
    form.image_main = '';
    ElMessage.success(window.t('the_main_image_has_been_deleted'));
};

const handleGallerySuccess = (response, file) => {
    const imageUrl = readUploadedPath(response);
    const existing = galleryFiles.value.find(f => f.uid === file.uid);

    if (!imageUrl) {
        // Nothing usable came back, so drop the card rather than leave a
        // blank entry that would be saved as an empty gallery slot.
        removeGalleryFile(file);
        ElMessage.error(window.t('failed_to_upload_image'));
        return;
    }

    if (existing) {
        existing.url = resolveImageUrl(imageUrl);
        existing.name = file.name;
        existing.status = 'success';
    }
    ElMessage.success(window.t('the_image_has_been_uploaded_successfully'));
};

const handleGalleryError = (error, file) => {
    removeGalleryFile(file);
    ElMessage.error(uploadErrorMessage(error));
};

const removeGalleryFile = (file) => {
    const index = galleryFiles.value.findIndex(img => img.uid === file.uid);
    if (index > -1) {
        galleryFiles.value.splice(index, 1);
    }
};

const isMainImage = (file) => isSameImage(file?.url, form.image_main);

const setAsMainImage = (file) => {
    if (!file?.url) return;
    form.image_main = resolveImageUrl(file.url);
    ElMessage.success(window.t('the_main_image_has_been'));
};

const handleGalleryPreview = (file) => {
    previewImageUrl.value = file.url;
    previewDialogVisible.value = true;
};

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 5 });
};

const submitForm = async () => {
    if (!formRef.value) return;

    if (uploadsInProgress.value) {
        ElMessage.warning(window.t('wait_for_images_to_finish_uploading'));
        activeTab.value = 'images';
        return;
    }

    try {
        await formRef.value.validate();
        submitting.value = true;

        const formData = {
            ...form,
            image_main: form.image_main ? toImagePath(form.image_main) : null,
            image_gallery: galleryFiles.value
                .map(img => toImagePath(img.url))
                .filter(Boolean),
            sale_price: form.sale_price || null,
            weight: form.weight || null,
            sort_order: form.sort_order || 0
        };

        if (isEdit.value) {
            await store.updateProduct(route.params.id, formData);
            ElMessage.success(window.t('the_product_has_been_updated'));
        } else {
            await store.createProduct(formData);
            ElMessage.success(window.t('the_product_has_been_added_successfully'));
        }

        router.push('/admin/products');
    } catch (error) {
        if (error?.response?.data?.errors) {
            const errs = error.response.data.errors;
            Object.entries(errs).forEach(([field, messages]) => {
                ElMessage.error(`${field}: ${messages[0]}`);
            });
        } else {
            ElMessage.error(store.error || window.t('please_check_the_data'));
        }
    } finally {
        submitting.value = false;
    }
};

const goBack = () => {
    router.push('/admin/products');
};

const safeParseGallery = (value) => {
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const loadProduct = async () => {
    if (!isEdit.value) return;
    try {
        await store.fetchProduct(route.params.id);
        const p = store.currentProduct;
        if (!p) return;

        Object.assign(form, {
            name_ar: p.name_ar || '',
            name_en: p.name_en || '',
            slug: p.slug || '',
            sku: p.sku || '',
            barcode: p.barcode || '',
            category_id: p.category?.id || p.category_id || '',
            price: p.price ?? 0,
            cost_price: p.cost_price ?? 0,
            sale_price: p.sale_price ?? null,
            currency: p.currency || baseCurrencyCode(),
            show_price: p.show_price ?? true,
            tax_rate: p.tax_rate ?? 0,
            taxable: p.taxable ?? true,
            unit: p.unit || 'piece',
            stock_quantity: p.stock_quantity ?? 0,
            min_stock: p.min_stock ?? 0,
            max_stock: p.max_stock ?? null,
            reorder_point: p.reorder_point ?? 5,
            weight: p.weight ?? null,
            length: p.length ?? null,
            width: p.width ?? null,
            height: p.height ?? null,
            color: p.color || '',
            size: p.size || '',
            sort_order: p.sort_order ?? 0,
            brand: p.brand || '',
            model: p.model || '',
            description_ar: p.description_ar || '',
            description_en: p.description_en || '',
            short_description_ar: p.short_description_ar || '',
            short_description_en: p.short_description_en || '',
            is_active: p.is_active ?? true,
            is_featured: p.is_featured ?? false,
            in_stock: p.in_stock ?? true,
            image_main: p.image_main || '',
            seo_title: p.seo?.title || p.seo_title || '',
            seo_description: p.seo?.description || p.seo_description || '',
            seo_keywords: p.seo?.keywords || p.seo_keywords || ''
        });

        variants.value = Array.isArray(p.variants) ? p.variants : [];

        const galleryArray = Array.isArray(p.image_gallery)
            ? p.image_gallery
            : (typeof p.image_gallery === 'string' ? safeParseGallery(p.image_gallery) : []);

        galleryFiles.value = galleryArray
            .map((entry) => (typeof entry === 'string' ? entry : (entry?.url || '')))
            .filter(Boolean)
            .map((url, index) => ({
                name: toImagePath(url).split('/').pop() || `image_${index + 1}`,
                url,
                // `status` keeps el-upload from re-rendering saved images as
                // pending cards once a new upload starts alongside them.
                status: 'success',
                uid: `saved-${index}`
            }));
    } catch {
        ElMessage.error(window.t('failed_to_fetch_product_data'));
    }
};

onMounted(async () => {
    await store.fetchCategories();
    await loadProduct();
});
</script>

<style scoped>
.products-form {
    padding: 0;
}

.mb-4 {
    margin-bottom: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0;
    color: #1a1a2e;
}

.product-form {
    margin-top: 0.5rem;
}

/* Variants tab label badge */
.tab-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.tab-tag {
    transform: translateY(-1px);
}

/* ---- Basic-data section grouping ---- */
.form-section {
    padding-top: 0.25rem;
}

.section-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1.25rem;
}

.section-head .el-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    color: #4f46e5;
    font-size: 17px;
}

.section-head h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 650;
    color: #1e293b;
}

.section-alert {
    margin-bottom: 1rem;
}

/* Variant color dot */
.variant-color-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.color-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    flex: 0 0 auto;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4);
}

.variant-sku {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #334155;
}

.variant-price {
    font-weight: 700;
    color: #b00e0e;
}

.variant-cost {
    color: #64748b;
}

/* ---- Variants tab ---- */
.variants-create-hint {
    padding: 2rem 0;
}

.variants-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
}

.variants-count {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
}

.variants-table {
    width: 100%;
}

/* ---- Image upload ---- */
.main-image-uploader {
    width: 280px;
    height: 280px;
    border-radius: 12px;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

/* `drag` wraps the slot in el-upload-dragger, which brings its own border and
   padding; the tile below is what should be seen. */
.main-image-uploader :deep(.el-upload),
.main-image-uploader :deep(.el-upload-dragger) {
    width: 100%;
    height: 100%;
    padding: 0;
    border-radius: 12px;
    border: 2px dashed #dcdfe6;
    overflow: hidden;
}

.main-image-uploader :deep(.el-upload-dragger:hover),
.main-image-uploader:hover :deep(.el-upload-dragger) {
    border-color: #409eff;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.15);
}

.image-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 280px;
}

.image-path {
    flex: 1;
    min-width: 0;
    color: #909399;
    direction: ltr;
    text-align: start;
}

.image-broken {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    color: #c0c4cc;
    font-size: 0.75rem;
    background: #fafafa;
}

.main-image-preview {
    width: 100%;
    height: 100%;
    position: relative;
}

.image-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    gap: 8px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.85rem;
}

.main-image-preview:hover .image-overlay {
    opacity: 1;
}

.upload-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #8c939d;
    padding: 20px;
    text-align: center;
}

.upload-placeholder span {
    font-size: 0.9rem;
}

.upload-hint {
    font-size: 0.75rem !important;
    color: #c0c4cc;
}

.image-upload-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.gallery-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}

.gallery-uploader {
    width: 100%;
}

.gallery-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.gallery-add-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    color: #8c939d;
    font-size: 0.85rem;
}

.gallery-item {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f7fa;
    border-radius: 6px;
    overflow: hidden;
}

.gallery-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* The thumbnail is a blob preview while the file is still going up, so the
   progress ring sits on top of it rather than beside it. */
.gallery-item :deep(.el-progress) {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.75);
}

.gallery-item.is-main {
    outline: 2px solid var(--el-color-success);
    outline-offset: -2px;
}

.gallery-main-tag {
    position: absolute;
    top: 6px;
    inset-inline-start: 6px;
}

.gallery-actions {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    opacity: 0;
    transition: opacity 0.25s ease;
}

.gallery-item:hover .gallery-actions {
    opacity: 1;
}

.gallery-actions .el-icon {
    font-size: 18px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.gallery-actions .el-icon:hover {
    transform: scale(1.2);
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.form-help {
    font-size: 0.75rem;
    color: #909399;
    margin-top: 4px;
    display: block;
    line-height: 1.5;
}

.field-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.field-hint-icon {
    color: #b1b3b8;
    cursor: help;
}
</style>