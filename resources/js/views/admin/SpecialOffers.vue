<template>
    <div class="special-offers-page">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('manage_special_offers') }}</h1>
                <p>{{ $t('from_here_you_can_add') }}</p>
            </div>
            <div>
                <el-button type="primary" :icon="Plus" @click="openCreateDialog">
                    {{ $t('add_a_new_offer') }}
                </el-button>
            </div>
        </div>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('list_of_current_offers') }}</h2>
                        <p class="header-note">{{ $t('immediately_modify_the_status_of') }}</p>
                    </div>
                </div>
            </template>

            <el-table
                v-loading="loading"
                :data="offers"
                style="width: 100%"
                stripe
                border
                size="large"
            >
                <el-table-column :label="$t('photo')" width="130" align="center">
                    <template #default="{ row }">
                        <el-image
                            v-if="row.image"
                            :src="`/storage/${row.image}`"
                            fit="cover"
                            style="width: 80px; height: 50px; border-radius: 8px; border: 1px solid #e2e8f0;"
                            :preview-src-list="[`/storage/${row.image}`]"
                            preview-teleported
                        />
                        <div v-else class="image-placeholder-small">{{ $t('there_is_no_picture') }}</div>
                    </template>
                </el-table-column>

                <el-table-column prop="title_ar" :label="$t('title_in_arabic')" min-width="180" show-overflow-tooltip />
                
                <el-table-column :label="$t('opponent')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.discount_percentage" type="danger" size="large" effect="dark">
                            {{ row.discount_percentage }}%
                        </el-tag>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('associated_product')" min-width="180">
                    <template #default="{ row }">
                        <span v-if="row.product">
                            {{ row.product.name_ar }}
                            <small class="d-block text-muted">SKU: {{ row.product.sku }}</small>
                        </span>
                        <span v-else class="text-muted">{{ $t('generic_not_associated_with_a_product') }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('time_period')" width="220" align="center">
                    <template #default="{ row }">
                        <div class="date-range-text">
                            <div><small>{{ $t('from') }}</small> {{ row.start_date ? formatDate(row.start_date) : $t('undefined') }}</div>
                            <div><small>{{ $t('to_me') }}</small> {{ row.end_date ? formatDate(row.end_date) : $t('undefined') }}</div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('display_status')" width="120" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.is_active"
                            active-color="#10b981"
                            inactive-color="#e2e8f0"
                            :loading="row.toggling"
                            @change="toggleActive(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('procedures')" width="150" align="center" fixed="left">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button type="primary" :icon="Edit" size="small" circle @click="openEditDialog(row)" />
                            <el-button type="danger" :icon="Delete" size="small" circle @click="confirmDelete(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Create/Edit Offer Dialog -->
        <el-dialog
            v-model="dialogVisible"
            ::title="$t('isedit_modify_the_special_offer')"
            width="650px"
            destroy-on-close
            class="offer-dialog"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" label-position="top" v-loading="dialogLoading">
                <el-row :gutter="20">
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('address_in_arabic')" prop="title_ar">
                            <el-input v-model="form.title_ar" :placeholder="$t('example_special_discount_on_fast')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('title_in_english_optional')" prop="title_en">
                            <el-input v-model="form.title_en" placeholder="Example: Fast Chargers Sale" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('description_in_arabic')" prop="description_ar">
                    <el-input
                        type="textarea"
                        v-model="form.description_ar"
                        :rows="3"
                        :placeholder="$t('enter_a_detailed_and_attractive')"
                    />
                </el-form-item>

                <el-form-item :label="$t('description_in_english_optional')" prop="description_en">
                    <el-input
                        type="textarea"
                        v-model="form.description_en"
                        :rows="3"
                        placeholder="Enter description in English..."
                    />
                </el-form-item>

                <el-row :gutter="20">
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('discount_percentage')" prop="discount_percentage">
                            <el-input-number
                                v-model="form.discount_percentage"
                                :min="0"
                                :max="100"
                                style="width: 100%"
                                :placeholder="$t('example_20')"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('link_to_a_specific_product_optional')" prop="product_id">
                            <el-select
                                v-model="form.product_id"
                                :placeholder="$t('search_and_choose_a_product')"
                                filterable
                                clearable
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="prod in products"
                                    :key="prod.id"
                                    :label="`${prod.name_ar} (SKU: ${prod.sku})`"
                                    :value="prod.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('start_date_optional')" prop="start_date">
                            <el-date-picker
                                v-model="form.start_date"
                                type="date"
                                :placeholder="$t('choose_the_start_date_of_the_offer')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="12">
                        <el-form-item :label="$t('end_date_optional')" prop="end_date">
                            <el-date-picker
                                v-model="form.end_date"
                                type="date"
                                :placeholder="$t('choose_the_offer_expiry_date')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :md="16">
                        <el-form-item :label="$t('dedicated_link_for_routing_optional')" prop="link">
                            <el-input v-model="form.link" :placeholder="$t('example_categories_chargers')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :md="8">
                        <el-form-item :label="$t('display_status_immediately')" prop="is_active">
                            <el-switch
                                v-model="form.is_active"
                                :active-text="$t('active')"
                                :inactive-text="$t('draft')"
                                style="margin-top: 5px;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('banner_featured_display_image')" required>
                    <input type="file" accept="image/*" @change="onFileSelect" />
                    <p class="text-muted" style="font-size: 0.8rem; margin: 4px 0 0 0;">{{ $t('maximum_size_3_mb_rectangular') }}</p>
                    <div v-if="imagePreview" class="preview-image mt-3">
                        <img :src="imagePreview" alt="Offer Banner Preview" style="max-width: 100%; max-height: 150px; border-radius: 10px; border: 1px solid #cbd5e1;" />
                    </div>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="saveOffer">{{ $t('save_the_offer') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api';

const loading = ref(false);
const dialogLoading = ref(false);
const submitting = ref(false);
const dialogVisible = ref(false);
const isEdit = ref(false);
const editId = ref(null);
const formRef = ref(null);

const offers = ref([]);
const products = ref([]);

const form = reactive({
    title_ar: '',
    title_en: '',
    description_ar: '',
    description_en: '',
    discount_percentage: null,
    product_id: null,
    link: '',
    start_date: null,
    end_date: null,
    is_active: true
});

const selectedFile = ref(null);
const imagePreview = ref('');

const rules = {
    title_ar: [{ required: true, message: window.t('address_in_arabic_is_required'), trigger: 'blur' }],
    description_ar: [{ required: true, message: window.t('description_in_arabic_is_required'), trigger: 'blur' }]
};

const fetchOffers = async () => {
    loading.value = true;
    try {
        const response = await api.get('/admin/special-offers');
        if (response.data?.success) {
            offers.value = response.data.data.map(item => ({
                ...item,
                toggling: false
            }));
        }
    } catch (error) {
        ElMessage.error(window.t('failed_to_load_list_of_special_offers'));
    } finally {
        loading.value = false;
    }
};

const fetchProducts = async () => {
    try {
        const response = await api.get('/admin/products');
        if (response.data?.success) {
            // If it returns standard laravel paginated or simple list
            products.value = response.data.data.data || response.data.data || [];
        }
    } catch (error) {
        console.error('Failed to load products list', error);
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-SY', { year: 'numeric', month: 'numeric', day: 'numeric' });
};

const onFileSelect = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        ElMessage.error(window.t('only_photos_can_be_uploaded'));
        return;
    }

    if (file.size / 1024 / 1024 > 3) {
        ElMessage.error(window.t('maximum_image_size_is_3mb'));
        return;
    }

    selectedFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
};

const toggleActive = async (row) => {
    row.toggling = true;
    try {
        const response = await api.post(`/admin/special-offers/${row.id}/toggle-status`);
        if (response.data?.success) {
            ElMessage.success(response.data.message);
            row.is_active = response.data.data.is_active;
        }
    } catch (error) {
        ElMessage.error(window.t('failed_to_change_offer_activation_status'));
        row.is_active = !row.is_active; // revert
    } finally {
        row.toggling = false;
    }
};

const resetForm = () => {
    form.title_ar = '';
    form.title_en = '';
    form.description_ar = '';
    form.description_en = '';
    form.discount_percentage = null;
    form.product_id = null;
    form.link = '';
    form.start_date = null;
    form.end_date = null;
    form.is_active = true;
    selectedFile.value = null;
    imagePreview.value = '';
    editId.value = null;
    isEdit.value = false;
};

const openCreateDialog = () => {
    resetForm();
    dialogVisible.value = true;
};

const openEditDialog = (row) => {
    resetForm();
    isEdit.value = true;
    editId.value = row.id;
    
    form.title_ar = row.title_ar || '';
    form.title_en = row.title_en || '';
    form.description_ar = row.description_ar || '';
    form.description_en = row.description_en || '';
    form.discount_percentage = row.discount_percentage;
    form.product_id = row.product_id;
    form.link = row.link || '';
    form.start_date = row.start_date || null;
    form.end_date = row.end_date || null;
    form.is_active = !!row.is_active;
    
    imagePreview.value = row.image ? `/storage/${row.image}` : '';
    dialogVisible.value = true;
};

const saveOffer = async () => {
    if (!formRef.value) return;

    await formRef.value.validate(async (valid) => {
        if (!valid) return;

        if (!selectedFile.value && !isEdit.value) {
            ElMessage.error(window.t('please_upload_a_photo_of'));
            return;
        }

        submitting.value = true;
        const formData = new FormData();
        
        formData.append('title_ar', form.title_ar);
        formData.append('title_en', form.title_en || '');
        formData.append('description_ar', form.description_ar);
        formData.append('description_en', form.description_en || '');
        
        if (form.discount_percentage !== null && form.discount_percentage !== undefined) {
            formData.append('discount_percentage', form.discount_percentage);
        }
        if (form.product_id) {
            formData.append('product_id', form.product_id);
        }
        formData.append('link', form.link || '');
        if (form.start_date) {
            formData.append('start_date', form.start_date);
        }
        if (form.end_date) {
            formData.append('end_date', form.end_date);
        }
        formData.append('is_active', form.is_active ? '1' : '0');

        if (selectedFile.value) {
            formData.append('image_file', selectedFile.value);
        }

        try {
            let response;
            if (isEdit.value) {
                // Submit as POST to update route
                response = await api.post(`/admin/special-offers/${editId.value}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
            } else {
                response = await api.post('/admin/special-offers', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
            }

            if (response.data?.success) {
                ElMessage.success(response.data.message || window.t('the_offer_has_been_saved_successfully'));
                dialogVisible.value = false;
                fetchOffers();
            }
        } catch (error) {
            const message = error.response?.data?.message || window.t('failed_to_save_featured_offer');
            ElMessage.error(message);
        } finally {
            submitting.value = false;
        }
    });
};

const confirmDelete = (row) => {
    ElMessageBox.confirm(
        window.t('are_you_sure_you_want'),
        window.t('deletion_warning'),
        {
            confirmButtonText: window.t('yes_delete'),
            cancelButtonText: window.t('cancel'),
            type: 'warning',
            draggable: true
        }
    ).then(async () => {
        try {
            const response = await api.delete(`/admin/special-offers/${row.id}`);
            if (response.data?.success) {
                ElMessage.success(response.data.message || window.t('the_offer_has_been_successfully_deleted'));
                fetchOffers();
            }
        } catch (error) {
            ElMessage.error(window.t('failed_to_delete_featured_view'));
        }
    }).catch(() => {});
};

onMounted(() => {
    fetchOffers();
    fetchProducts();
});
</script>

<style scoped>
.special-offers-page {
    padding: 0;
}

.date-range-text {
    text-align: right;
    font-size: 0.85rem;
    line-height: 1.4;
}

.date-range-text small {
    color: #64748b;
    font-weight: 600;
}

.image-placeholder-small {
    background-color: #f1f5f9;
    color: #94a3b8;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.8rem;
    border: 1px dashed #cbd5e1;
}

.d-block {
    display: block;
}

.mt-3 {
    margin-top: 0.75rem;
}

.mt-4 {
    margin-top: 1rem;
}
</style>
