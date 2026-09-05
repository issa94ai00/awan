<template>
    <div class="categories-form">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>{{ isEdit ? $t('edit_category') : $t('add_a_new_category') }}</span>
                    <el-button @click="goBack">{{ $t('back') }}</el-button>
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
                        <el-form-item :label="$t('category_name_arabic')" prop="name_ar">
                            <el-input v-model="form.name_ar" :placeholder="$t('category_name_in_arabic')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('category_name_english')" prop="name_en">
                            <el-input v-model="form.name_en" placeholder="Category name in English" @input="generateSlug" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="Slug" prop="slug">
                    <el-input v-model="form.slug" placeholder="category-slug" />
                </el-form-item>

                <el-form-item :label="$t('description_arabic')">
                    <el-input
                        v-model="form.description_ar"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('category_description_in_arabic')"
                    />
                </el-form-item>

                <el-form-item :label="$t('description_english')">
                    <el-input
                        v-model="form.description_en"
                        type="textarea"
                        :rows="3"
                        placeholder="Category description in English"
                    />
                </el-form-item>

                <el-form-item :label="$t('category_image')">
                    <el-upload
                        class="image-uploader"
                        :action="uploadUrl"
                        :headers="uploadHeaders"
                        :show-file-list="false"
                        :on-success="handleImageSuccess"
                        :on-error="handleImageError"
                        :before-upload="beforeUpload"
                    >
                        <img v-if="form.image" :src="form.image" class="uploaded-image" />
                        <el-icon v-else class="uploader-icon"><Plus /></el-icon>
                    </el-upload>
                    <el-button
                        v-if="form.image"
                        link
                        type="danger"
                        class="remove-image-btn"
                        @click="removeImage"
                    >
                        {{ $t('delete_the_image') }}
                    </el-button>
                </el-form-item>

                <el-form-item :label="$t('status')">
                    <el-switch
                        v-model="form.is_active"
                        :active-text="$t('active')"
                        :inactive-text="$t('inactive')"
                    />
                </el-form-item>

                <el-form-item :label="$t('ranking')">
                    <el-input-number v-model="form.sort_order" :min="0" />
                </el-form-item>
            </el-form>

            <div class="form-actions">
                <el-button @click="goBack">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ isEdit ? $t('update') : $t('save') }}
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
import { resolveImageUrl, toImagePath } from '@/utils/productImages';

const router = useRouter();
const route = useRoute();
const categoriesStore = useCategoriesStore();

const formRef = ref(null);
const submitting = ref(false);
const uploadUrl = '/api/v1/upload';

const uploadHeaders = computed(() => {
    const token = localStorage.getItem('token');
    return token ? { Authorization: `Bearer ${token}` } : {};
});

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
    name_ar: [{ required: true, message: window.t('required_field'), trigger: 'blur' }],
    name_en: [{ required: true, message: 'This field is required', trigger: 'blur' }],
    slug: [{ required: true, message: window.t('required_field'), trigger: 'blur' }]
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

const MAX_IMAGE_MB = 5;

const beforeUpload = (file) => {
    const isImage = file.type.startsWith('image/');
    // Matched to what the endpoint accepts (max:5120). The old 2MB guard
    // rejected files the server would have taken quite happily.
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

const handleImageSuccess = (response) => {
    const url = response?.data?.full_url || response?.data?.url || response?.url || '';
    if (!url) {
        ElMessage.error(window.t('failed_to_upload_image'));
        return;
    }
    // The field holds a browsable URL while the form is open — the raw relative
    // path the endpoint returns does not resolve in an <img> — and is folded
    // back to a stored path on submit.
    form.value.image = resolveImageUrl(url);
    ElMessage.success(window.t('the_image_has_been_uploaded_successfully'));
};

const removeImage = () => {
    form.value.image = '';
};

const handleImageError = () => {
    ElMessage.error(window.t('failed_to_upload_image'));
};

const submitForm = async () => {
    try {
        await formRef.value.validate();
        submitting.value = true;

        const payload = {
            ...form.value,
            image: form.value.image ? toImagePath(form.value.image) : null,
        };

        if (isEdit.value) {
            await categoriesStore.updateCategory(route.params.id, payload);
            ElMessage.success(window.t('the_category_has_been_updated'));
        } else {
            await categoriesStore.createCategory(payload);
            ElMessage.success(window.t('the_category_has_been_added_successfully'));
        }
        
        router.push('/admin/categories');
    } catch (error) {
        ElMessage.error(categoriesStore.error || window.t('please_check_the_data'));
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
            ElMessage.error(window.t('failed_to_fetch_category_data'));
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

.remove-image-btn {
    margin-inline-start: 12px;
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
