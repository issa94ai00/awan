<template>
    <div class="purchases-page purchases-receipts">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-truck-loading text-primary"
            :title="$t('receipts')"
            :subtitle="$t('clearly_view_receipts_with_live')"
        >
            <template #actions>
                <el-input 
                    v-model="searchQuery" 
                    :placeholder="$t('search_by_receipt_number_or')" 
                    clearable 
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button type="primary" @click="openCreateDrawer" class="create-btn">
                    <i class="fas fa-plus-circle"></i> {{ $t('new_receipt') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Metrics cards row -->
        <AdminStatGrid>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box blue-grad">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ store.receipts.length }}</h3>
                        <p>{{ $t('total_receipts') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="hover" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box green-grad">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ recentCount }}</h3>
                        <p>{{ $t('latest_receipts') }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Table Panel -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('list_of_receipts') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="filteredReceipts.length" 
                    :data="filteredReceipts" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="receipt_number" :label="$t('receipt_number')" width="140">
                        <template #default="{ row }">
                            <span class="receipt-link-txt" @click="openDetailDrawer(row.id)">{{ row.receipt_number }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="supplier.name" :label="$t('supplier')">
                        <template #default="{ row }">
                            <div class="supplier-cell">
                                <i class="fas fa-truck text-muted"></i>
                                <span>{{ row.supplier?.name || '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="receipt_date" :label="$t('the_date')" width="180" align="center" />
                    <el-table-column prop="notes" :label="$t('comments')" min-width="200" show-overflow-tooltip />
                    
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
                                <el-button size="small" type="danger" plain @click="deleteReceipt(row.id)" :title="$t('delete')">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!filteredReceipts.length" class="empty-state-box">
                    <i class="fas fa-receipt empty-icon"></i>
                    <p>{{ $t('there_are_no_receipts_matching') }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus-circle"></i> {{ $t('create_new_receipt') }}
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            :title="$t('goods_receipt_details')"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedReceipt" class="drawer-detail-content">
                <!-- Timeline status step -->
                <div class="timeline-step-tracker mb-4">
                    <div class="visual-progress-timeline">
                        <div class="progress-base-bar"></div>
                        <div class="progress-fill-bar" style="width: 100%;"></div>
                        
                        <div class="timeline-nodes-wrapper">
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-file-signature"></i></div>
                                <span>{{ $t('linked_purchase_order') }}</span>
                            </div>
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-truck-loading"></i></div>
                                <span>{{ $t('document_the_receipt') }}</span>
                            </div>
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-boxes"></i></div>
                                <span>{{ $t('update_the_warehouse') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row :gutter="20">
                    <!-- Left: items table & totals -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-list text-muted mr-1"></i> {{ $t('items_and_quantities_received') }}</span>
                            </template>
                            <el-table :data="selectedReceipt.items || []" style="width: 100%" stripe>
                                <el-table-column prop="product.name_ar" :label="$t('item_product')" />
                                <el-table-column prop="quantity" :label="$t('quantity_received')" width="140" align="center" />
                                <el-table-column prop="unit_price" :label="$t('purchase_price')" width="130">
                                    <template #default="{ row }">${{ parseFloat(row.unit_price || 0).toFixed(2) }}</template>
                                </el-table-column>
                                <el-table-column prop="sale_price" :label="$t('sale_price')" width="130">
                                    <template #default="{ row }">{{ row.sale_price != null ? '$' + parseFloat(row.sale_price).toFixed(2) : '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('grand_total')" width="130">
                                    <template #default="{ row }">${{ (row.quantity * row.unit_price).toFixed(2) }}</template>
                                </el-table-column>
                            </el-table>

                            <div class="financial-summary-block mt-4">
                                <div class="financial-row grand-total">
                                    <span>{{ $t('grand_total_label') }}</span>
                                    <span>${{ parseFloat(selectedReceipt.total || 0).toFixed(2) }}</span>
                                </div>
                            </div>
                        </el-card>

                        <!-- Stock Alert Log -->
                        <div class="alert-success-box mb-4">
                            <i class="fas fa-check-circle text-success"></i>
                            <div>
                                <h4>{{ $t('quantities_booked_into_stock') }}</h4>
                                <ul>
                                    <li v-for="item in selectedReceipt.items" :key="item.id">
                                        {{ item.product?.name_ar }}: <strong>+{{ item.quantity }} {{ ('unit_suffix') }}</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </el-col>

                    <!-- Right: supplier & reference orders info -->
                    <el-col :xs="24" :lg="8">
                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-user-tie text-muted mr-1"></i> {{ $t('supplier_details') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('supplier_name_label') }}</span>
                                    <strong>{{ selectedReceipt.supplier?.name || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedReceipt.supplier?.company">
                                    <span class="lbl">{{ $t('company_label') }}</span>
                                    <strong>{{ selectedReceipt.supplier.company }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedReceipt.supplier?.phone">
                                    <span class="lbl">{{ $t('phone_label') }}</span>
                                    <strong>{{ selectedReceipt.supplier.phone }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-info-circle text-muted mr-1"></i> {{ $t('shipment_details') }}</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">{{ $t('receipt_date_label') }}</span>
                                    <strong>{{ selectedReceipt.receipt_date || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">{{ $t('linked_to_purchase_order_label') }}</span>
                                    <strong v-if="selectedReceipt.purchase_order" style="color: var(--accent-blue);">
                                        #{{ selectedReceipt.purchase_order.order_number }}
                                    </strong>
                                    <strong v-else>{{ $t('direct_receipt') }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card v-if="selectedReceipt.notes" shadow="never">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> {{ $t('notes') }}</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedReceipt.notes }}</p>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>

        <!-- Form Drawer (Create / Edit) -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? ('edit_goods_receipt') : ('create_goods_receipt')"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('supplier')" required>
                            <el-select v-model="form.supplier_id" :placeholder="$t('select_supplier')" style="width: 100%" filterable @change="handleSupplierChange">
                                <el-option
                                    v-for="s in suppliersStore.suppliers"
                                    :key="s.id"
                                    :label="s.name"
                                    :value="s.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <!-- A supplier's own orders are the only ones that can ever
                             be received against, so the list is scoped to whichever
                             supplier is chosen instead of listing every order in
                             the system and letting a mismatch slip through. -->
                        <el-form-item :label="$t('linked_purchase_order')">
                            <el-select
                                v-model="form.purchase_order_id"
                                :placeholder="form.supplier_id ? $t('select_purchase_order_or_direct') : $t('select_supplier_first')"
                                style="width: 100%"
                                filterable
                                clearable
                                :disabled="!form.supplier_id"
                                :loading="purchaseOrdersStore.loading"
                                @change="handlePurchaseOrderChange"
                            >
                                <el-option
                                    v-for="o in purchaseOrdersStore.orders"
                                    :key="o.id"
                                    :label="o.order_number"
                                    :value="o.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="12">
                        <el-form-item :label="$t('actual_receipt_date')">
                            <el-date-picker v-model="form.receipt_date" type="date" :placeholder="$t('receipt_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <!-- The receipt puts the goods on the balance sheet, so where
                             they land is the operator's call rather than a silent
                             fallback to whichever warehouse happens to be first. -->
                        <el-form-item :label="$t('receiving_warehouse')" required>
                            <el-select v-model="form.warehouse_id" :placeholder="$t('choose_warehouse')" style="width: 100%" :disabled="isEditMode">
                                <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- Kept out of the item prices on purpose: tax paid to a
                     supplier is recoverable from the authority, so booking it
                     into the cost of the goods overstates the stock and hides
                     the claim. -->
                <el-form-item :label="$t('purchase_tax_amount')" class="mt-3">
                    <el-input v-model="form.tax_amount" type="number" min="0" step="0.01" :disabled="isEditMode" />
                    <small class="field-hint">{{ $t('purchase_tax_hint') }}</small>
                </el-form-item>

                <el-form-item :label="$t('receipt_notes')" class="mt-3">
                    <el-input v-model="form.notes" type="textarea" :rows="3" :placeholder="$t('receipt_notes_placeholder')" />
                </el-form-item>

                <!-- Dynamic items grid -->
                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;"><i class="fas fa-boxes text-primary"></i> {{ $t('incoming_items_and_quantities') }}</h3>
                        <el-button v-if="!isEditMode" type="primary" size="small" plain @click="addItemRow">
                            <i class="fas fa-plus"></i> {{ $t('add_item') }}
                        </el-button>
                    </div>

                    <!-- Lines are locked once the receipt exists: the stock was taken
                         in against them and their cost posted to the ledger, so
                         changing them here would leave both describing a delivery
                         that never happened. -->
                    <el-alert
                        v-if="isEditMode"
                        type="info"
                        show-icon
                        :closable="false"
                        class="mb-3"
                        :title="$t('receipt_lines_locked_notice')"
                    />

                    <div class="items-grid-wrapper">
                        <div v-for="(item, idx) in form.items" :key="idx" class="item-grid-row">
                            <div class="item-row-top">
                                <el-select v-model="item.product_id" :placeholder="$t('select_item')" filterable style="flex: 2.5;" :disabled="isEditMode" @change="(val) => updateItemPrice(val, idx)">
                                    <el-option
                                        v-for="p in productsStore.products"
                                        :key="p.id"
                                        :label="p.name_ar + ' (SKU: ' + p.sku + ')'"
                                        :value="p.id"
                                    />
                                </el-select>
                                <el-input-number v-model="item.quantity" :min="1" :placeholder="$t('quantity')" style="flex: 1;" :disabled="isEditMode" />
                                <el-button
                                    v-if="!isEditMode"
                                    type="success"
                                    circle
                                    plain
                                    @click="openQuickAddProduct(idx)"
                                    :title="$t('add_new_product')"
                                >
                                    <i class="fas fa-plus"></i>
                                </el-button>
                                <el-button v-if="!isEditMode" type="danger" circle @click="removeItemRow(idx)" :disabled="form.items.length <= 1">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </div>
                            <!-- Cost is rolled into the product's weighted-average
                                 cost on save; sale price, if set, replaces the
                                 product's shelf price outright — receiving this
                                 purchase is what puts both into effect. -->
                            <div class="item-row-prices">
                                <div class="price-field">
                                    <label>{{ $t('purchase_cost') }}</label>
                                    <el-input v-model="item.unit_price" placeholder="0.00" :disabled="isEditMode" />
                                </div>
                                <div class="price-field">
                                    <label>{{ $t('sale_price') }}</label>
                                    <el-input v-model="item.sale_price" placeholder="0.00" :disabled="isEditMode" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="formDrawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveReceipt">{{ $t('save_receipt') }}</el-button>
                </div>
            </el-form>
        </el-drawer>

        <!-- Quick Add Product Dialog: lets an unlisted item be created and
             dropped straight into the receipt line that needed it, so a
             missing product no longer means abandoning the whole receipt to
             go create it in the catalog first. -->
        <el-dialog
            v-model="quickAddDialogVisible"
            :title="$t('quick_add_product')"
            width="480px"
            append-to-body
            destroy-on-close
        >
            <p class="quick-add-hint">{{ $t('quick_add_product_hint') }}</p>
            <el-form :model="quickAddForm" label-position="top">
                <el-form-item :label="$t('product_name_ar')" required>
                    <el-input v-model="quickAddForm.name_ar" />
                </el-form-item>
                <el-form-item :label="$t('product_name_en')" required>
                    <el-input v-model="quickAddForm.name_en" />
                </el-form-item>
                <el-form-item :label="$t('category')" required>
                    <el-select v-model="quickAddForm.category_id" filterable style="width: 100%">
                        <el-option
                            v-for="c in productsStore.categories"
                            :key="c.id"
                            :label="c.name_ar || c.name"
                            :value="c.id"
                        />
                    </el-select>
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('purchase_cost')">
                            <el-input v-model="quickAddForm.cost_price" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('sale_price')" required>
                            <el-input v-model="quickAddForm.price" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('sku_optional')">
                            <el-input v-model="quickAddForm.sku" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('unit_optional')">
                            <el-input v-model="quickAddForm.unit" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="quickAddDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="quickAddSubmitting" @click="submitQuickAddProduct">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';
import { useSuppliersStore } from '@/stores/suppliers';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { useProductsStore } from '@/stores/products';
import { useInventoryStore } from '@/stores/inventory';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { baseCurrencyCode } from '@/utils/currency';
import { Search } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const store = usePurchaseReceiptsStore();
const suppliersStore = useSuppliersStore();
const purchaseOrdersStore = usePurchaseOrdersStore();
const productsStore = useProductsStore();
const inventoryStore = useInventoryStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedReceipt = ref(null);

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingReceiptId = ref(null);

// Quick-add-product state: lets a missing item be created without leaving
// the receipt form, then drops straight into the row that needed it.
const quickAddDialogVisible = ref(false);
const quickAddSubmitting = ref(false);
const quickAddTargetIndex = ref(null);
const quickAddForm = reactive({
    name_ar: '',
    name_en: '',
    category_id: '',
    cost_price: '',
    price: '',
    sku: '',
    unit: ''
});

const form = reactive({
    supplier_id: '',
    purchase_order_id: '',
    warehouse_id: '',
    receipt_date: '',
    tax_amount: 0,
    notes: '',
    items: []
});

const warehouses = computed(() => inventoryStore.warehouses);

const resetForm = () => {
    form.supplier_id = '';
    form.purchase_order_id = '';
    // A single active warehouse is not a choice worth asking for.
    form.warehouse_id = warehouses.value.length === 1 ? warehouses.value[0].id : '';
    form.receipt_date = new Date().toISOString().split('T')[0];
    form.tax_amount = 0;
    form.notes = '';
    form.items = [{ product_id: '', quantity: 1, unit_price: '', sale_price: '' }];
};

const filteredReceipts = computed(() => {
    if (!searchQuery.value.trim()) return store.receipts;
    const query = searchQuery.value.toLowerCase();
    return store.receipts.filter((receipt) => {
        return [
            receipt.receipt_number,
            receipt.supplier?.name,
            receipt.receipt_date,
            receipt.notes
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.receipts.length, 5));

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        selectedReceipt.value = res.data.data;
    } catch (e) {
        ElMessage.error(t('failed_to_load_receipt_details'));
    } finally {
        loadingDetail.value = false;
    }
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    resetForm();
    formDrawerVisible.value = true;
};

// Reached via the "record a goods receipt" prompt shown right after a
// purchase order is placed: opens the create drawer pre-bound to that order
// instead of leaving the operator to search for it in the picker.
const openCreateDrawerForOrder = async (orderId) => {
    isEditMode.value = false;
    resetForm();
    formDrawerVisible.value = true;
    try {
        const res = await purchaseOrdersApi.getById(orderId);
        const order = res.data.data;
        if (order?.supplier_id) {
            form.supplier_id = order.supplier_id;
            await purchaseOrdersStore.fetchOrders({ supplier_id: order.supplier_id, per_page: 100 }).catch(() => {});
        }
        form.purchase_order_id = orderId;
        await handlePurchaseOrderChange(orderId);
    } catch (e) {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
    }
};

const openEditDrawer = async (id) => {
    isEditMode.value = true;
    editingReceiptId.value = id;
    formDrawerVisible.value = true;
    resetForm();
    submittingForm.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        const receipt = res.data.data;
        form.supplier_id = receipt.supplier_id;
        form.purchase_order_id = receipt.purchase_order_id;
        form.warehouse_id = receipt.warehouse_id;
        form.receipt_date = receipt.receipt_date;
        // Shown but locked, like the lines: the tax was posted with them.
        form.tax_amount = receipt.tax_amount ?? 0;
        form.notes = receipt.notes;
        form.items = receipt.items.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            sale_price: item.sale_price
        }));

        // Populate the order list scoped to this receipt's supplier so the
        // already-linked order (if any) shows up as a valid option.
        if (form.supplier_id) {
            await purchaseOrdersStore.fetchOrders({ supplier_id: form.supplier_id, per_page: 100 }).catch(() => {});
        }
    } catch (e) {
        ElMessage.error(t('failed_to_load_receipt_for_edit'));
        formDrawerVisible.value = false;
    } finally {
        submittingForm.value = false;
    }
};

// Purchase orders are scoped to the chosen supplier: an order belonging to a
// different supplier could never be received against this receipt anyway,
// so listing every order in the system just invited picking the wrong one.
const handleSupplierChange = async (supplierId) => {
    if (form.purchase_order_id) {
        form.purchase_order_id = '';
        form.items = [{ product_id: '', quantity: 1, unit_price: '', sale_price: '' }];
    }

    if (!supplierId) {
        purchaseOrdersStore.orders = [];
        return;
    }

    try {
        await purchaseOrdersStore.fetchOrders({ supplier_id: supplierId, per_page: 100 });
    } catch (e) {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
    }
};

// Form Dynamic items grid actions
const addItemRow = () => {
    form.items.push({ product_id: '', quantity: 1, unit_price: '', sale_price: '' });
};

const removeItemRow = (idx) => {
    form.items.splice(idx, 1);
};

const updateItemPrice = (productId, idx) => {
    const prod = productsStore.products.find(p => p.id === productId);
    if (prod) {
        // The receipt's price is what the supplier is paid, so it starts
        // from the product's cost — not its retail price, which is what the
        // line is instead defaulted to sell at.
        form.items[idx].unit_price = prod.cost_price || prod.price;
        form.items[idx].sale_price = prod.price;
    }
};

// Quick-add-product: opens pre-filled with whatever price the operator had
// already typed on this line, since that number is the purchase cost anyway.
const openQuickAddProduct = (idx) => {
    quickAddTargetIndex.value = idx;
    quickAddForm.name_ar = '';
    quickAddForm.name_en = '';
    quickAddForm.category_id = '';
    quickAddForm.cost_price = form.items[idx]?.unit_price || '';
    quickAddForm.price = '';
    quickAddForm.sku = '';
    quickAddForm.unit = '';
    if (!productsStore.categories.length) {
        productsStore.fetchCategories().catch(() => {});
    }
    quickAddDialogVisible.value = true;
};

const slugify = (text) => text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

const submitQuickAddProduct = async () => {
    if (!quickAddForm.name_ar || !quickAddForm.name_en || !quickAddForm.category_id || !quickAddForm.price) {
        ElMessage.warning(t('please_fill_required_product_fields'));
        return;
    }

    quickAddSubmitting.value = true;
    try {
        // The catalog requires a unique slug, but this dialog never shows one —
        // a timestamp suffix keeps two products with the same name from
        // colliding without asking the operator to think about it.
        const baseSlug = slugify(quickAddForm.name_en) || 'product';
        const product = await productsStore.createProduct({
            name_ar: quickAddForm.name_ar,
            name_en: quickAddForm.name_en,
            slug: `${baseSlug}-${Date.now().toString(36)}`,
            category_id: quickAddForm.category_id,
            price: quickAddForm.price,
            cost_price: quickAddForm.cost_price || null,
            sku: quickAddForm.sku || null,
            unit: quickAddForm.unit || null,
            currency: baseCurrencyCode(),
            stock_quantity: 0
        });

        const idx = quickAddTargetIndex.value;
        if (idx !== null && form.items[idx]) {
            form.items[idx].product_id = product.id;
            if (!form.items[idx].unit_price) {
                form.items[idx].unit_price = quickAddForm.cost_price || product.cost_price || product.price;
            }
            if (!form.items[idx].sale_price) {
                form.items[idx].sale_price = quickAddForm.price || product.price;
            }
        }

        ElMessage.success(t('product_created_and_selected'));
        quickAddDialogVisible.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_create_product'));
    } finally {
        quickAddSubmitting.value = false;
    }
};

const handlePurchaseOrderChange = async (purchaseOrderId) => {
    if (!purchaseOrderId) {
        // If cleared, reset items to empty
        form.items = [{ product_id: '', quantity: 1, unit_price: '', sale_price: '' }];
        return;
    }

    try {
        const response = await purchaseReceiptsApi.getPurchaseOrderDetails(purchaseOrderId);
        if (response.data.success) {
            const data = response.data.data;
            
            // Auto-fill supplier_id if not set
            if (data.supplier_id && !form.supplier_id) {
                form.supplier_id = data.supplier_id;
            }
            
            // Auto-fill items from purchase order, sale price included — it
            // was already decided when the order was made.
            if (data.items && data.items.length > 0) {
                form.items = data.items.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    sale_price: item.sale_price
                }));

                ElMessage.success(t('items_filled_from_purchase_order'));
            }
        }
    } catch (error) {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
        console.error('Error fetching purchase order details:', error);
    }
};

const saveReceipt = async () => {
    if (!form.supplier_id) {
        ElMessage.warning(t('please_select_supplier_first'));
        return;
    }

    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            // Only the descriptive fields travel: the lines and the warehouse
            // are fixed once the goods are in and their cost is posted.
            await purchaseReceiptsApi.update(editingReceiptId.value, {
                purchase_order_id: form.purchase_order_id || null,
                receipt_date: form.receipt_date,
                notes: form.notes,
            });
            ElMessage.success(t('receipt_updated'));
        } else {
            if (!form.warehouse_id) {
                ElMessage.warning(t('please_select_receiving_warehouse'));
                return;
            }
            if (form.items.some(item => !item.product_id || !item.quantity || !item.unit_price)) {
                ElMessage.warning(t('please_fill_all_item_fields'));
                return;
            }

            await purchaseReceiptsApi.create(form);
            ElMessage.success(t('receipt_saved_stock_and_entry'));
        }
        formDrawerVisible.value = false;
        await store.fetchReceipts();
    } catch (e) {
        // The API explains precisely why a receipt cannot be rewritten; echoing
        // a generic failure here would hide the reason and the way forward.
        ElMessage.error(e.response?.data?.message || t('failed_to_save_receipt'));
    } finally {
        submittingForm.value = false;
    }
};

const deleteReceipt = async (id) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_receipt'),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await purchaseReceiptsApi.delete(id);
        ElMessage.success(t('receipt_deleted'));
        await store.fetchReceipts();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_delete_receipt'));
    }
};

onMounted(async () => {
    store.fetchReceipts().catch(() => {});
    suppliersStore.fetchSuppliers().catch(() => {});
    // Purchase orders load once a supplier is chosen (handleSupplierChange) or
    // when editing a receipt that already has one, so the list is always
    // scoped to a supplier instead of dumping every order in the system.
    productsStore.fetchProducts({ per_page: 100 }).catch(() => {});
    inventoryStore.fetchSummary().catch(() => {});

    const orderId = route.query.create_for_order;
    if (orderId) {
        // Drop the query param so a refresh/back-nav doesn't reopen the drawer.
        router.replace({ query: { ...route.query, create_for_order: undefined } });
        openCreateDrawerForOrder(orderId);
    }
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

.receipt-link-txt {
    color: var(--accent-blue);
    font-weight: 700;
    cursor: pointer;
}

.receipt-link-txt:hover {
    text-decoration: underline;
}

.supplier-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
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

.timeline-step-tracker {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.75rem 1.25rem;
    border-radius: var(--radius-md);
}

.visual-progress-timeline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.progress-base-bar {
    position: absolute;
    top: 20px;
    left: 15%;
    right: 15%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 15%;
    right: 15%;
    height: 4px;
    background: var(--success);
    z-index: 2;
}

.timeline-nodes-wrapper {
    display: flex;
    justify-content: space-around;
    width: 100%;
    z-index: 3;
}

.timeline-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.node-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--success);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: var(--shadow-sm);
}

.timeline-node span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-dark);
}

.card-title-txt {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.financial-summary-block {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.financial-row {
    display: flex;
    justify-content: space-between;
    width: 250px;
    color: var(--text-medium);
    font-size: 0.9rem;
}

.financial-row.grand-total {
    border-top: 2px solid var(--border-color);
    padding-top: 0.5rem;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--accent-blue);
}

.alert-success-box {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 1.25rem 1.5rem;
    border-radius: var(--radius-md);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    color: #065f46;
}

.alert-success-box i {
    font-size: 1.4rem;
    margin-top: 0.15rem;
}

.alert-success-box h4 {
    margin: 0 0 0.5rem 0;
    font-weight: 700;
}

.alert-success-box ul {
    margin: 0;
    padding-right: 1.25rem;
    font-size: 0.9rem;
}

.notes-txt-view {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-medium);
    line-height: 1.6;
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

.quick-add-hint {
    margin: 0 0 1.25rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Form Grid row */
.item-row-top {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.item-row-prices {
    display: flex;
    gap: 1rem;
    margin-top: 0.75rem;
}

.price-field {
    flex: 1;
    max-width: 220px;
}

.price-field label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}

.item-grid-row {
    display: block;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}
</style>
