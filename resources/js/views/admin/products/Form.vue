<template>
    <div class="products-form">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>{{ isEdit ? 'تعديل المنتج' : 'إضافة منتج جديد' }}</span>
                    <el-button @click="goBack">رجوع</el-button>
                </div>
            </template>

            <el-tabs v-model="activeTab" type="border-card">
                <el-tab-pane label="البيانات الأساسية" name="basic">
                    <el-form
                        ref="formRef"
                        :model="form"
                        :rules="rules"
                        label-width="120px"
                        label-position="top"
                    >
                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="اسم المنتج (عربي)" prop="name_ar">
                                    <el-input v-model="form.name_ar" placeholder="اسم المنتج بالعربية" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="اسم المنتج (إنجليزي)" prop="name_en">
                                    <el-input v-model="form.name_en" placeholder="Product name in English" @input="generateSlug" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="12">
                                <el-form-item label="Slug" prop="slug">
                                    <el-input v-model="form.slug" placeholder="product-slug" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-form-item label="الفئة" prop="category_id">
                                    <el-select v-model="form.category_id" placeholder="اختر الفئة" style="width: 100%">
                                        <el-option
                                            v-for="cat in categories"
                                            :key="cat.id"
                                            :label="cat.name"
                                            :value="cat.id"
                                        />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="8">
                                <el-form-item label="السعر" prop="price">
                                    <el-input-number v-model="form.price" :min="0" :precision="2" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item label="السعر المخفض" prop="sale_price">
                                    <el-input-number v-model="form.sale_price" :min="0" :precision="2" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item label="العملة">
                                    <el-select v-model="form.currency" style="width: 100%">
                                        <el-option label="ريال سعودي" value="SAR" />
                                        <el-option label="دولار أمريكي" value="USD" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <el-col :xs="24" :md="8">
                                <el-form-item label="كمية المخزون" prop="stock_quantity">
                                    <el-input-number v-model="form.stock_quantity" :min="0" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item label="الحد الأدنى" prop="min_stock">
                                    <el-input-number v-model="form.min_stock" :min="0" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                            <el-col :xs="24" :md="8">
                                <el-form-item label="الوزن (كجم)">
                                    <el-input-number v-model="form.weight" :min="0" :precision="2" style="width: 100%" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-form-item label="الوصف (عربي)" prop="description_ar">
                            <el-input
                                v-model="form.description_ar"
                                type="textarea"
                                :rows="4"
                                placeholder="وصف المنتج بالعربية"
                            />
                        </el-form-item>

                        <el-form-item label="الوصف (إنجليزي)">
                            <el-input
                                v-model="form.description_en"
                                type="textarea"
                                :rows="4"
                                placeholder="Product description in English"
                            />
                        </el-form-item>

                        <el-form-item label="الحالة">
                            <el-switch
                                v-model="form.is_active"
                                active-text="نشط"
                                inactive-text="غير نشط"
                            />
                        </el-form-item>

                        <el-form-item label="منتج مميز">
                            <el-switch
                                v-model="form.is_featured"
                                active-text="مميز"
                                inactive-text="غير مميز"
                            />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="الصور" name="images">
                    <el-form label-width="120px" label-position="top">
                        <el-form-item label="الصورة الرئيسية">
                            <el-upload
                                class="image-uploader"
                                :action="uploadUrl"
                                :show-file-list="false"
                                :on-success="handleMainImageSuccess"
                                :before-upload="beforeUpload"
                            >
                                <img v-if="form.image_main" :src="form.image_main" class="uploaded-image" />
                                <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                            </el-upload>
                        </el-form-item>

                        <el-form-item label="معرض الصور">
                            <el-upload
                                v-model:file-list="galleryImages"
                                class="gallery-uploader"
                                :action="uploadUrl"
                                list-type="picture-card"
                                :on-success="handleGallerySuccess"
                                :on-remove="handleGalleryRemove"
                                multiple
                            >
                                <el-icon><Plus /></el-icon>
                            </el-upload>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="SEO" name="seo">
                    <el-form label-width="120px" label-position="top">
                        <el-form-item label="عنوان الصفحة">
                            <el-input v-model="form.seo_title" placeholder="SEO Title" />
                        </el-form-item>
                        <el-form-item label="الوصف الميتا">
                            <el-input
                                v-model="form.seo_description"
                                type="textarea"
                                :rows="3"
                                placeholder="Meta Description"
                            />
                        </el-form-item>
                        <el-form-item label="الكلمات المفتاحية">
                            <el-input v-model="form.seo_keywords" placeholder="keyword1, keyword2, keyword3" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>
            </el-tabs>

            <div class="form-actions">
                <el-button @click="goBack">إلغاء</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ isEdit ? 'تحديث' : 'حفظ' }}
                </el-button>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { Plus } from '@element-plus/icons-vue';

const router = useRouter();
const route = useRoute();
const productsStore = useProductsStore();

const formRef = ref(null);
const activeTab = ref('basic');
const submitting = ref(false);
const uploadUrl = '/api/v1/upload';
const galleryImages = ref([]);

const form = ref({
    name_ar: '',
    name_en: '',
    slug: '',
    category_id: '',
    price: 0,
    sale_price: 0,
    currency: 'SAR',
    stock_quantity: 0,
    min_stock: 10,
    weight: 0,
    description_ar: '',
    description_en: '',
    is_active: true,
    is_featured: false,
    image_main: '',
    seo_title: '',
    seo_description: '',
    seo_keywords: ''
});

const rules = {
    name_ar: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'blur' }],
    name_en: [{ required: true, message: 'This field is required', trigger: 'blur' }],
    slug: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'blur' }],
    category_id: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'change' }],
    price: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'blur' }],
    stock_quantity: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'blur' }]
};

const categories = ref([
    { id: 1, name: 'إلكترونيات' },
    { id: 2, name: 'ملابس' },
    { id: 3, name: 'أثاث' }
]);

const isEdit = computed(() => !!route.params.id);
const loading = computed(() => productsStore.loading);

const generateSlug = () => {
    if (form.value.name_en) {
        form.value.slug = form.value.name_en
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
    }
};

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

const handleMainImageSuccess = (response) => {
    form.value.image_main = response.data.url;
    ElMessage.success('تم رفع الصورة بنجاح');
};

const handleGallerySuccess = (response) => {
    ElMessage.success('تم رفع الصورة بنجاح');
};

const handleGalleryRemove = (file) => {
    ElMessage.success('تم حذف الصورة');
};

const submitForm = async () => {
    try {
        await formRef.value.validate();
        submitting.value = true;

        if (isEdit.value) {
            await productsStore.updateProduct(route.params.id, form.value);
            ElMessage.success('تم تحديث المنتج بنجاح');
        } else {
            await productsStore.createProduct(form.value);
            ElMessage.success('تم إضافة المنتج بنجاح');
        }
        
        router.push('/admin/products');
    } catch (error) {
        ElMessage.error(productsStore.error || 'يرجى التحقق من البيانات');
    } finally {
        submitting.value = false;
    }
};

const goBack = () => {
    router.push('/admin/products');
};

onMounted(async () => {
    if (isEdit.value) {
        try {
            await productsStore.fetchProduct(route.params.id);
            if (productsStore.currentProduct) {
                form.value = { ...form.value, ...productsStore.currentProduct };
            }
        } catch (error) {
            ElMessage.error('فشل في جلب بيانات المنتج');
        }
    }
});
</script>

<style scoped>
.products-form {
    padding: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.image-uploader {
    width: 200px;
    height: 200px;
    border: 2px dashed #dcdfe6;
    border-radius: 8px;
    cursor: pointer;
    overflow: hidden;
    transition: border-color 0.3s ease;
}

.image-uploader:hover {
    border-color: #409eff;
}

.uploaded-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

.gallery-uploader {
    width: 100%;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}
</style>
