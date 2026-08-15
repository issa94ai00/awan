<template>
    <div class="purchases-page purchases-suppliers">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-user-tie text-primary"
            :title="$t('suppliers')"
            :subtitle="$t('the_supplier_interface_is_designed')"
        >
            <template #actions>
                <el-input 
                    v-model="searchQuery" 
                    :placeholder="$t('search_by_name_email_or_phone')" 
                    clearable 
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> {{ $t('new_supplier') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Metrics cards row -->
        <AdminStatGrid>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box blue-grad">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ store.suppliers.length }}</h3>
                        <p>{{ $t('total_suppliers') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box green-grad">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ activeSuppliersCount }}</h3>
                        <p>{{ $t('active_suppliers') }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Table Panel -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('suppliers_list') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="filteredSuppliers.length" 
                    :data="filteredSuppliers" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="name" :label="$t('name')" width="220">
                        <template #default="{ row }">
                            <div class="supplier-name-cell" style="cursor: pointer;" @click="openDetailDrawer(row.id)">
                                <i class="fas fa-user-tie text-primary mr-1"></i>
                                <strong class="name-link">{{ row.name }}</strong>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="company" :label="$t('company')" width="180">
                        <template #default="{ row }">
                            <span>{{ row.company || '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="email" :label="$t('email')" width="220" />
                    <el-table-column prop="phone" :label="$t('phone')" width="160" />
                    <el-table-column :label="$t('status')" width="140" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.status)"></i>
                                {{ getArabicStatus(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <!-- Actions Column -->
                    <el-table-column :label="$t('actions')" width="220" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button size="small" type="info" plain @click="openDetailDrawer(row.id)" :title="$t('view_details')">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" type="warning" plain @click="openEditDrawer(row.id)" :title="$t('edit')">
                                    <i class="fas fa-edit"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain @click="deleteSupplier(row.id)" :title="$t('delete')">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!filteredSuppliers.length" class="empty-state-box">
                    <i class="fas fa-users empty-icon"></i>
                    <p>{{ $t('there_are_no_suppliers_matching') }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> {{ $t('add_first_supplier') }}
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            :title="$t('supplier_profile')"
            size="50%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedSupplier" class="drawer-detail-content">
                <el-row :gutter="20">
                    <el-col :span="24">
                        <!-- Profile Card -->
                        <div class="supplier-profile-header mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-md); display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--accent-blue-light); display: flex; align-items: center; justify-content: center; color: var(--accent-blue); font-size: 2rem;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-dark);">{{ selectedSupplier.name }}</h3>
                                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.9rem;">{{ selectedSupplier.company || ('company_not_set') }}</p>
                            </div>
                        </div>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <!-- Left: Details list -->
                    <el-col :span="14">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-info-circle text-muted mr-1"></i> {{ $t('contact_and_address_details') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('email_label') }}</span>
                                    <strong>{{ selectedSupplier.email || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">{{ $t('phone_label') }}</span>
                                    <strong>{{ selectedSupplier.phone || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">{{ $t('address_label') }}</span>
                                    <strong>{{ selectedSupplier.address || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedSupplier.city || selectedSupplier.country">
                                    <span class="lbl">{{ $t('region_and_country_label') }}</span>
                                    <strong>{{ [selectedSupplier.city, selectedSupplier.state, selectedSupplier.country].filter(Boolean).join(', ') }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <!-- Connected Orders -->
                        <el-card shadow="never">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-file-invoice text-muted mr-1"></i> {{ $t('linked_purchase_orders') }}</span>
                            </template>
                            <el-table :data="connectedOrders" style="width: 100%" stripe size="small">
                                <el-table-column prop="order_number" :label="$t('order_number')" />
                                <el-table-column prop="total" :label="$t('grand_total')">
                                    <template #default="{ row }">${{ parseFloat(row.total || 0).toFixed(2) }}</template>
                                </el-table-column>
                                <el-table-column prop="status" :label="$t('status')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="statusTagType(row.status)" size="small">
                                            {{ getArabicStatus(row.status) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div v-if="!connectedOrders.length" class="empty-state" style="padding: 1.5rem 0;">{{ $t('no_linked_purchase_orders') }}</div>
                        </el-card>
                    </el-col>

                    <!-- Right: Financial / Balance cards -->
                    <el-col :span="10">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-wallet text-muted mr-1"></i> {{ $t('financial_account_and_credit') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('current_balance_label') }}</span>
                                    <strong style="color: var(--danger-dark);">${{ parseFloat(selectedSupplier.balance || 0).toFixed(2) }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">{{ $t('available_credit_label') }}</span>
                                    <strong>${{ parseFloat(selectedSupplier.credit_limit || 0).toFixed(2) }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedSupplier.tax_number">
                                    <span class="lbl">{{ $t('tax_number_label') }}</span>
                                    <strong>{{ selectedSupplier.tax_number }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedSupplier.lead_time_days">
                                    <span class="lbl">{{ $t('estimated_lead_time_label') }}</span>
                                    <strong>{{ selectedSupplier.lead_time_days }} {{ ('day_suffix') }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <!-- Notes Card -->
                        <el-card v-if="selectedSupplier.notes" shadow="never">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> {{ $t('internal_comments') }}</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedSupplier.notes }}</p>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>

        <!-- Form Drawer (Create / Edit) -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? ('edit_supplier') : ('register_supplier')"
            size="50%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('supplier_full_name')" required>
                            <el-input v-model="form.name" :placeholder="$t('enter_supplier_name')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('company_or_organisation_name')">
                            <el-input v-model="form.company" :placeholder="$t('enter_company_name')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="12">
                        <el-form-item :label="$t('phone_number')" required>
                            <el-input v-model="form.phone" :placeholder="$t('enter_phone_number')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('email')">
                            <el-input v-model="form.email" :placeholder="$t('enter_email')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="24">
                        <el-form-item :label="$t('physical_address')">
                            <el-input v-model="form.address" :placeholder="$t('street_building_placeholder')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="8">
                        <el-form-item :label="$t('city')">
                            <el-input v-model="form.city" :placeholder="$t('city')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('region_state')">
                            <el-input v-model="form.state" :placeholder="$t('state')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('country')">
                            <el-input v-model="form.country" :placeholder="$t('country')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="8">
                        <el-form-item :label="$t('credit_limit_usd')">
                            <el-input v-model="form.credit_limit" type="number" placeholder="0.00" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('tax_number')">
                            <el-input v-model="form.tax_number" :placeholder="$t('enter_tax_number')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('lead_time_days')">
                            <el-input v-model="form.lead_time_days" type="number" :placeholder="$t('example_five_days')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="12">
                        <el-form-item :label="$t('activity_status')" required>
                            <el-select v-model="form.status" style="width: 100%">
                                <el-option :label="$t('active')" value="active" />
                                <el-option :label="$t('inactive')" value="inactive" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('opening_balance_usd')">
                            <el-input v-model="form.balance" type="number" placeholder="0.00" :disabled="isEditMode" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('supplier_notes')" class="mt-3">
                    <el-input v-model="form.notes" type="textarea" :rows="3" :placeholder="$t('supplier_notes_placeholder')" />
                </el-form-item>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="formDrawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveSupplier">{{ $t('save_supplier') }}</el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, computed, reactive } from 'vue';
import { useSuppliersStore } from '@/stores/suppliers';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { suppliersApi } from '@/api/suppliers';
import { Search } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const store = useSuppliersStore();
const purchaseOrdersStore = usePurchaseOrdersStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedSupplier = ref(null);

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingSupplierId = ref(null);

const form = reactive({
    name: '',
    company: '',
    phone: '',
    email: '',
    address: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    credit_limit: 0,
    tax_number: '',
    lead_time_days: '',
    balance: 0,
    status: 'active',
    notes: ''
});

const resetForm = () => {
    form.name = '';
    form.company = '';
    form.phone = '';
    form.email = '';
    form.address = '';
    form.city = '';
    form.state = '';
    form.country = '';
    form.postal_code = '';
    form.credit_limit = 0;
    form.tax_number = '';
    form.lead_time_days = '';
    form.balance = 0;
    form.status = 'active';
    form.notes = '';
};

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const val = normalizeStatus(status);
    if (['active', '1', 'true', 'yes'].includes(val)) return 'success';
    return 'info';
};

const statusIconClass = (status) => {
    const val = normalizeStatus(status);
    if (['active', '1', 'true', 'yes'].includes(val)) return 'fa-check-circle';
    return 'fa-times-circle';
};

const getArabicStatus = (status) => {
    const val = normalizeStatus(status);
    if (['active', '1', 'true', 'yes'].includes(val)) return t('active');
    return t('inactive');
};

const filteredSuppliers = computed(() => {
    if (!searchQuery.value.trim()) return store.suppliers;
    const query = searchQuery.value.toLowerCase();
    return store.suppliers.filter((supplier) => {
        return [
            supplier.name,
            supplier.company,
            supplier.email,
            supplier.phone,
            supplier.status
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const activeSuppliersCount = computed(() => {
    return store.suppliers.filter((supplier) => {
        const val = normalizeStatus(supplier.status);
        return ['active', '1', 'true', 'yes'].includes(val);
    }).length;
});

// Related purchase orders to show in detail drawer
const connectedOrders = computed(() => {
    if (!selectedSupplier.value) return [];
    return purchaseOrdersStore.orders.filter(order => order.supplier_id === selectedSupplier.value.id);
});

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await suppliersApi.getById(id);
        selectedSupplier.value = res.data.data;
    } catch (e) {
        ElMessage.error(t('failed_to_load_supplier_profile'));
    } finally {
        loadingDetail.value = false;
    }
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    resetForm();
    formDrawerVisible.value = true;
};

const openEditDrawer = async (id) => {
    isEditMode.value = true;
    editingSupplierId.value = id;
    formDrawerVisible.value = true;
    resetForm();
    submittingForm.value = true;
    try {
        const res = await suppliersApi.getById(id);
        const supplier = res.data.data;
        form.name = supplier.name;
        form.company = supplier.company;
        form.phone = supplier.phone;
        form.email = supplier.email;
        form.address = supplier.address;
        form.city = supplier.city;
        form.state = supplier.state;
        form.country = supplier.country;
        form.postal_code = supplier.postal_code;
        form.credit_limit = supplier.credit_limit;
        form.tax_number = supplier.tax_number;
        form.lead_time_days = supplier.lead_time_days;
        form.balance = supplier.balance;
        form.status = supplier.status;
        form.notes = supplier.notes;
    } catch (e) {
        ElMessage.error(t('failed_to_load_supplier_for_edit'));
        formDrawerVisible.value = false;
    } finally {
        submittingForm.value = false;
    }
};

const saveSupplier = async () => {
    if (!form.name || !form.phone) {
        ElMessage.warning(t('name_and_phone_required'));
        return;
    }
    
    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            await suppliersApi.update(editingSupplierId.value, form);
            ElMessage.success(t('supplier_updated'));
        } else {
            await suppliersApi.create(form);
            ElMessage.success(t('supplier_saved'));
        }
        formDrawerVisible.value = false;
        await store.fetchSuppliers();
    } catch (e) {
        ElMessage.error(t('failed_to_save_supplier'));
    } finally {
        submittingForm.value = false;
    }
};

const deleteSupplier = async (id) => {
    if (confirm(t('confirm_delete_supplier'))) {
        try {
            await suppliersApi.delete(id);
            ElMessage.success(t('supplier_deleted'));
            await store.fetchSuppliers();
        } catch (error) {
            ElMessage.error(t('failed_to_delete_supplier'));
        }
    }
};

onMounted(() => {
    store.fetchSuppliers().catch(() => {});
    purchaseOrdersStore.fetchOrders().catch(() => {});
});
</script>

<style scoped>
.purchases-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    width: min(100%, 280px);
}

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.overview-cards {
    margin-bottom: 2rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card-wrapper:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.stat-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.blue-grad {
    background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-light) 100%);
}

.green-grad {
    background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: 500;
}

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.supplier-name-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.name-link {
    color: var(--accent-blue);
    font-weight: 700;
}

.name-link:hover {
    text-decoration: underline;
}

.status-tag {
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-dot-icon {
    font-size: 0.8rem;
}

.action-btn-group .el-button {
    padding: 0.4rem 0.6rem;
}

.loading-state {
    padding: 2rem;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-state-box p {
    font-weight: 500;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
}

/* Detail Drawer Styles */
.drawer-detail-content {
    padding: 1.5rem;
    font-family: 'Cairo', sans-serif;
}

.card-title-txt {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.info-item .lbl {
    color: var(--text-muted);
}

.info-item strong {
    color: var(--text-dark);
}

.notes-txt-view {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-medium);
    line-height: 1.6;
}

.empty-state {
    text-align: center;
    color: var(--text-light);
    font-size: 0.85rem;
}
</style>
