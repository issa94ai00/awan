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
                    <el-tab-pane :label="$t('basic_data')" name="basic">
                        <el-row :gutter="24">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('product_name_arabic')" prop="name_ar">
                                    <el-input v-model="form.name_ar" :placeholder="$t('product_name_in_arabic')" size="large" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('product_name_english')" prop="name_en">
                                    <el-input v-model="form.name_en" placeholder="Product name in English" size="large" @input="generateSlug" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="24">
                            <el-col :xs="24" :md="8">
                                <el-form-item label="Slug" prop="slug">
                                    <el-input v-model="form.slug" placeholder="product-slug" size="large" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item :label="$t('product_code_sku')" prop="sku">
                                    <el-input v-model="form.sku" placeholder="SKU-001" size="large" />
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
                                <el-form-item :label="$t('currency')">
                                    <el-select v-model="form.currency" size="large" style="width:100%">
                                        <el-option :label="$t('saudi_riyal_sar')" value="SAR" />
                                        <el-option :label="$t('us_dollar_usd')" value="USD" />
                                        <el-option :label="$t('syrian_pound_syp')" value="SYP" />
                                        <el-option :label="$t('iraqi_dinar_iqd')" value="IQD" />
                                        <el-option :label="$t('uae_dirham_aed')" value="AED" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('view_price')">
                                    <el-switch v-model="form.show_price" :active-text="$t('yes')" :inactive-text="$t('no')" size="large" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="24">
                            <el-col :xs="24" :md="6">
                                <el-form-item :label="$t('discounted_price')">
                                    <el-input-number v-model="form.sale_price" :min="0" :precision="2" size="large" style="width:100%" />
                                </el-form-item>
                            </el-col>
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

                        <el-row :gutter="24">
                            <el-col :xs="24" :md="12">
                                <el-form-item :label="$t('sort_order')">
                                    <el-input-number v-model="form.sort_order" :min="0" size="large" style="width:100%" />
                                </el-form-item>
                            </el-col>
                        </el-row>

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
                    </el-tab-pane>

                    <el-tab-pane :label="$t('the_pictures')" name="images">
                        <el-form-item :label="$t('main_image')">
                            <div class="image-upload-wrapper">
                                <el-upload
                                    ref="mainImageUpload"
                                    class="main-image-uploader"
                                    :action="uploadUrl"
                                    :data="{ slug: form.slug || 'product' }"
                                    :show-file-list="false"
                                    :on-success="handleMainImageSuccess"
                                    :before-upload="beforeUpload"
                                    :headers="uploadHeaders"
                                    name="file"
                                    accept="image/*"
                                >
                                    <div v-if="form.image_main" class="main-image-preview">
                                        <el-image :src="form.image_main" fit="cover" style="width:100%;height:100%" />
                                        <div class="image-overlay">
                                            <el-icon :size="24"><EditPen /></el-icon>
                                            <span>{{ $t('change_the_picture') }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="upload-placeholder">
                                        <el-icon :size="40"><Plus /></el-icon>
                                        <span>{{ $t('upload_the_main_image') }}</span>
                                        <span class="upload-hint">jpg/png/gif, max 2MB</span>
                                    </div>
                                </el-upload>
                                <el-button
                                    v-if="form.image_main"
                                    type="danger"
                                    size="small"
                                    plain
                                    @click="removeMainImage"
                                >
                                    <el-icon><Delete /></el-icon> {{ $t('delete_the_image') }}
                                </el-button>
                            </div>
                        </el-form-item>

                        <el-divider />

                        <el-form-item :label="$t('photo_gallery')">
                            <el-upload
                                ref="galleryUpload"
                                v-model:file-list="galleryFiles"
                                class="gallery-uploader"
                                :action="uploadUrl"
                                :data="galleryUploadData"
                                list-type="picture-card"
                                :on-success="handleGallerySuccess"
                                :on-remove="handleGalleryRemove"
                                :on-preview="handleGalleryPreview"
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
                            </el-upload>
                        </el-form-item>

                        <el-dialog v-model="previewDialogVisible" :title="$t('image_preview')" width="600px">
                            <el-image :src="previewImageUrl" fit="contain" style="width:100%;max-height:500px" />
                        </el-dialog>
                    </el-tab-pane>

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

                <div class="form-actions">
                    <el-button size="large" @click="goBack">{{ $t('cancel') }}</el-button>
                    <el-button size="large" type="primary" :loading="submitting" @click="submitForm">
                        {{ isEdit ? $t('product_update') : $t('save_the_product') }}
                    </el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { Plus, Delete, ArrowRight, EditPen } from '@element-plus/icons-vue';

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
    currency: 'SAR',
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
    name_en: [{ required: true, message: 'Product name in English is required', trigger: 'blur' }],
    slug: [
        { required: true, message: window.t('slug_required'), trigger: 'blur' },
        { pattern: /^[a-z0-9-]+$/, message: window.t('slug_must_contain_only_letters'), trigger: 'blur' }
    ],
    category_id: [{ required: true, message: window.t('category_required'), trigger: 'change' }],
    price: [{ required: true, message: window.t('price_required'), trigger: 'blur' }],
    stock_quantity: [{ required: true, message: window.t('quantity_of_inventory_required'), trigger: 'blur' }]
};

const categories = computed(() => store.categories);
const isEdit = computed(() => !!route.params.id);
let galleryCounter = 0;
const galleryUploadData = computed(() => ({
    slug: form.slug || 'product' + '-' + (++galleryCounter)
}));

const generateSlug = () => {
    if (form.name_en && !isEdit.value) {
        form.slug = form.name_en
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
    }
};

const beforeUpload = (file) => {
    const isImage = file.type.startsWith('image/');
    const isLt2M = file.size / 1024 / 1024 < 2;

    if (!isImage) {
        ElMessage.error(window.t('only_photos_can_be_uploaded'));
        return false;
    }
    if (!isLt2M) {
        ElMessage.error(window.t('image_size_must_be_less_than_2mb'));
        return false;
    }
    return true;
};

const extractStoragePath = (url) => {
    if (!url) return '';
    // Handle full URLs: http://domain/storage/uploads/file.jpg -> uploads/file.jpg
    if (url.startsWith('http')) {
        const match = url.match(/\/storage\/(.+)$/);
        return match ? match[1] : url;
    }
    // Handle relative: /storage/uploads/file.jpg -> uploads/file.jpg
    return url.replace(/^\/storage\//, '').replace(/^\/+/, '');
};

const normalizeImageUrl = (url) => {
    if (!url) return '';
    const path = extractStoragePath(url);
    return path ? '/storage/' + path : '';
};

const handleMainImageSuccess = (response) => {
    let imageUrl = null;
    if (response?.data?.url) {
        imageUrl = response.data.url;
    } else if (response?.url) {
        imageUrl = response.url;
    }

    if (imageUrl) {
        form.image_main = normalizeImageUrl(imageUrl);
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
    let imageUrl = null;
    if (response?.data?.url) {
        imageUrl = response.data.url;
    } else if (response?.url) {
        imageUrl = response.url;
    }

    if (imageUrl) {
        const finalUrl = normalizeImageUrl(imageUrl);
        const existing = galleryFiles.value.find(f => f.uid === file.uid);
        if (existing) {
            existing.url = finalUrl;
            existing.name = file.name;
        }
        ElMessage.success(window.t('the_image_has_been_uploaded_successfully'));
    } else {
        ElMessage.error(window.t('failed_to_upload_image'));
    }
};

const handleGalleryRemove = (file) => {
    const index = galleryFiles.value.findIndex(img => img.uid === file.uid);
    if (index > -1) {
        galleryFiles.value.splice(index, 1);
    }
};

const handleGalleryPreview = (file) => {
    previewImageUrl.value = file.url;
    previewDialogVisible.value = true;
};

const submitForm = async () => {
    if (!formRef.value) return;
    try {
        await formRef.value.validate();
        submitting.value = true;

        const formData = {
            ...form,
            image_main: form.image_main ? extractStoragePath(form.image_main) : null,
            image_gallery: galleryFiles.value.map(img => extractStoragePath(img.url)),
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
            currency: p.currency || 'SAR',
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

        if (p.image_gallery) {
            const galleryArray = Array.isArray(p.image_gallery)
                ? p.image_gallery
                : (typeof p.image_gallery === 'string' ? JSON.parse(p.image_gallery) : []);
            galleryFiles.value = galleryArray.map((url, index) => ({
                name: `image_${index}`,
                url: typeof url === 'string' ? url : (url.url || url),
                uid: Date.now() + index
            }));
        }
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

.main-image-uploader {
    width: 280px;
    height: 280px;
    border: 2px dashed #dcdfe6;
    border-radius: 12px;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.main-image-uploader:hover {
    border-color: #409eff;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.15);
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

.gallery-uploader {
    width: 100%;
}

.gallery-add-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    color: #8c939d;
    font-size: 0.85rem;
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
}
</style>
