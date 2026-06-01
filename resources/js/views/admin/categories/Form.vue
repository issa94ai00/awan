<template>
    <div class="categories-form">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>{{ isEdit ? 'تعديل الفئة' : 'إضافة فئة جديدة' }}</span>
                    <el-button @click="goBack">رجوع</el-button>
                </div>
            </template>

            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-width="120px"
                label-position="top"
            >
                <el-row :gutter="20">
                    <el-col :xs="24" :md="12">
                        <el-form-item label="اسم الفئة (عربي)" prop="name_ar">
                            <el-input v-model="form.name_ar" placeholder="اسم الفئة بالعربية" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item label="اسم الفئة (إنجليزي)" prop="name_en">
                            <el-input v-model="form.name_en" placeholder="Category name in English" @input="generateSlug" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="Slug" prop="slug">
                    <el-input v-model="form.slug" placeholder="category-slug" />
                </el-form-item>

                <el-form-item label="الوصف (عربي)">
                    <el-input
                        v-model="form.description_ar"
                        type="textarea"
                        :rows="3"
                        placeholder="وصف الفئة بالعربية"
                    />
                </el-form-item>

                <el-form-item label="الوصف (إنجليزي)">
                    <el-input
                        v-model="form.description_en"
                        type="textarea"
                        :rows="3"
                        placeholder="Category description in English"
                    />
                </el-form-item>

                <el-form-item label="صورة الفئة">
                    <el-upload
                        class="image-uploader"
                        :action="uploadUrl"
                        :show-file-list="false"
                        :on-success="handleImageSuccess"
                        :before-upload="beforeUpload"
                    >
                        <img v-if="form.image" :src="form.image" class="uploaded-image" />
                        <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                    </el-upload>
                </el-form-item>

                <el-form-item label="الحالة">
                    <el-switch
                        v-model="form.is_active"
                        active-text="نشط"
                        inactive-text="غير نشط"
                    />
                </el-form-item>

                <el-form-item label="الترتيب">
                    <el-input-number v-model="form.sort_order" :min="0" />
                </el-form-item>
            </el-form>

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
import { useCategoriesStore } from '@/stores/categories';
import { Plus } from '@element-plus/icons-vue';

const router = useRouter();
const route = useRoute();
const categoriesStore = useCategoriesStore();

const formRef = ref(null);
const submitting = ref(false);
const uploadUrl = '/api/v1/upload';

const form = ref({
    name_ar: '',
    name_en: '',
    slug: '',
    description_ar: '',
    description_en: '',
    image: '',
    is_active: true,
    sort_order: 0
});

const rules = {
    name_ar: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'blur' }],
    name_en: [{ required: true, message: 'This field is required', trigger: 'blur' }],
    slug: [{ required: true, message: 'هذا الحقل مطلوب', trigger: 'blur' }]
};

const isEdit = computed(() => !!route.params.id);
const loading = computed(() => categoriesStore.loading);

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

const handleImageSuccess = (response) => {
    form.value.image = response.data.url;
    ElMessage.success('تم رفع الصورة بنجاح');
};

const submitForm = async () => {
    try {
        await formRef.value.validate();
        submitting.value = true;

        if (isEdit.value) {
            await categoriesStore.updateCategory(route.params.id, form.value);
            ElMessage.success('تم تحديث الفئة بنجاح');
        } else {
            await categoriesStore.createCategory(form.value);
            ElMessage.success('تم إضافة الفئة بنجاح');
        }
        
        router.push('/admin/categories');
    } catch (error) {
        ElMessage.error(categoriesStore.error || 'يرجى التحقق من البيانات');
    } finally {
        submitting.value = false;
    }
};

const goBack = () => {
    router.push('/admin/categories');
};

onMounted(async () => {
    if (isEdit.value) {
        try {
            await categoriesStore.fetchCategory(route.params.id);
            if (categoriesStore.currentCategory) {
                form.value = { ...form.value, ...categoriesStore.currentCategory };
            }
        } catch (error) {
            ElMessage.error('فشل في جلب بيانات الفئة');
        }
    }
});
</script>

<style scoped>
.categories-form {
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

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}
</style>
