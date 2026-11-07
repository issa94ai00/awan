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
                    ref="receiptsTable"
                    :data="filteredReceipts"
                    row-key="id"
                    style="width: 100%"
                    stripe
                    highlight-current-row
                    class="custom-table"
                    @expand-change="onExpandChange"
                >
                    <!-- What arrived on the receipt: each size under its product
                         with its size, colour, material, specs and codes. -->
                    <el-table-column type="expand" width="44">
                        <template #default="{ row }">
                            <DocumentLinesPanel :lines="receiptLines(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="receipt_number" :label="$t('receipt_number')" width="160">
                        <template #default="{ row }">
                            <span class="receipt-link-txt" @click="openDetailDrawer(row.id)">{{ row.receipt_number }}</span>
                            <button
                                v-if="row.items?.length"
                                type="button"
                                class="lines-toggle"
                                :title="$t('rc_show_lines')"
                                @click.stop="toggleLines(row)"
                            >
                                {{ $t('po_items_count', row.items.length) }}
                                <i class="fas fa-chevron-down lines-chevron" :class="{ open: expandedIds.has(row.id) }"></i>
                            </button>
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
                    <el-table-column prop="receipt_date" :label="$t('the_date')" width="120" align="center">
                        <template #default="{ row }">{{ formatDate(row.receipt_date) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('rc_total')" width="130" align="center">
                        <template #default="{ row }">
                            <div class="money-cell">
                                <strong>{{ money(row.total_amount) }}</strong>
                                <div v-if="num(row.discount) > 0" class="discount-table-tag" :title="`${$t('discount')}: ${money(row.discount)}`">
                                    <i class="fas fa-tag"></i>
                                    <span>-{{ money(row.discount) }}</span>
                                    <small v-if="row.discount_percent != null && num(row.discount_percent) > 0">({{ row.discount_percent }}%)</small>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <!-- What was paid against it, how, and what is still owed. -->
                    <el-table-column :label="$t('rc_paid')" width="150" align="center">
                        <template #default="{ row }">
                            <div class="paid-cell">
                                <strong>{{ money(row.paid_amount) }}</strong>
                                <small v-if="row.payment_methods?.length">{{ methodsText(row.payment_methods) }}</small>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('rc_due')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="num(row.due_amount) < 0.01" type="success" size="small" effect="plain">{{ $t('fully_paid') }}</el-tag>
                            <el-tag v-else :type="num(row.paid_amount) > 0 ? 'warning' : 'danger'" size="small" effect="plain">{{ money(row.due_amount) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="notes" :label="$t('comments')" min-width="160" show-overflow-tooltip />
                    
                    <!-- Actions Column -->
                    <el-table-column :label="$t('actions')" width="260" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button size="small" type="info" plain @click="openDetailDrawer(row.id)" :title="$t('view_details')">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" type="primary" plain @click="openPrint(row.id)" :title="$t('print')">
                                    <i class="fas fa-print"></i>
                                </el-button>
                                <el-button
                                    v-if="mayPay && num(row.due_amount) >= 0.01"
                                    size="small"
                                    type="success"
                                    plain
                                    @click="openPayDialog(row)"
                                    :title="$t('rc_pay_remaining')"
                                >
                                    <i class="fas fa-money-bill-wave"></i>
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
            :size="drawerSize"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedReceipt" class="drawer-detail-content">
                <div class="drawer-print-bar">
                    <el-button type="primary" plain size="small" @click="openPrint(selectedReceipt.id)">
                        <i class="fas fa-print"></i>&nbsp;{{ $t('print') }}
                    </el-button>
                </div>
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
                                <el-table-column :label="$t('item_product')" min-width="160">
                                    <template #default="{ row }">
                                        <span>{{ row.product?.name_ar || row.description || '-' }}</span>
                                        <div v-if="row.product_variant_id" class="cell-variant">
                                            <VariantDetails v-if="lineVariantInfo(row)" :variant="row.variant" show-codes />
                                            <VariantChip v-else :label="variantLabelOf(row.variant) || row.description" :sku="row.variant?.sku" show-sku />
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="quantity" :label="$t('quantity_received')" width="140" align="center" />
                                <el-table-column prop="unit_price" :label="$t('purchase_price')" width="130">
                                    <template #default="{ row }">{{ formatUnitPrice(row.unit_price) }}</template>
                                </el-table-column>
                                <el-table-column prop="sale_price" :label="$t('sale_price')" width="130">
                                    <template #default="{ row }">{{ row.sale_price != null ? formatUnitPrice(row.sale_price) : '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="$t('grand_total')" width="130">
                                    <template #default="{ row }">{{ money(row.quantity * row.unit_price) }}</template>
                                </el-table-column>
                            </el-table>

                            <div class="financial-summary-block mt-4">
                                <div class="financial-row">
                                    <span>{{ $t('rc_goods_value') }}</span>
                                    <span>{{ money(selectedReceipt.goods_total) }}</span>
                                </div>
                                <div v-if="num(selectedReceipt.discount) > 0" class="financial-row discount-row">
                                    <span>
                                        {{ $t('discount') }}
                                        <span v-if="selectedReceipt.discount_percent != null && num(selectedReceipt.discount_percent) > 0" class="discount-badge-pill">
                                            {{ selectedReceipt.discount_percent }}%
                                        </span>
                                    </span>
                                    <span class="text-danger">− {{ money(selectedReceipt.discount) }}</span>
                                </div>
                                <div v-if="num(selectedReceipt.tax_amount) > 0" class="financial-row">
                                    <span>{{ $t('tax_label') }}</span>
                                    <span>+ {{ money(selectedReceipt.tax_amount) }}</span>
                                </div>
                                <div class="financial-row grand-total">
                                    <span>{{ $t('grand_total_label') }}</span>
                                    <span>{{ money(selectedReceipt.total_amount) }}</span>
                                </div>
                                <div class="financial-row">
                                    <span>{{ $t('rc_paid') }}</span>
                                    <span>{{ money(selectedReceipt.paid_amount) }}</span>
                                </div>
                                <div class="financial-row" :class="num(selectedReceipt.due_amount) >= 0.01 ? 'text-danger' : 'text-success'">
                                    <span>{{ $t('rc_due') }}</span>
                                    <span>{{ num(selectedReceipt.due_amount) >= 0.01 ? money(selectedReceipt.due_amount) : $t('fully_paid') }}</span>
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

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <div class="payments-card-head">
                                    <span class="card-title-txt"><i class="fas fa-money-bill-wave text-muted mr-1"></i> {{ $t('rc_payments') }}</span>
                                    <el-button
                                        v-if="mayPay && num(selectedReceipt.due_amount) >= 0.01"
                                        size="small"
                                        type="success"
                                        plain
                                        @click="openPayDialog(selectedReceipt)"
                                    >
                                        {{ $t('rc_pay_remaining') }}
                                    </el-button>
                                </div>
                            </template>
                            <div v-if="selectedReceipt.payments?.length" class="info-list">
                                <div v-for="p in selectedReceipt.payments" :key="p.id" class="info-item">
                                    <span class="lbl">
                                        {{ formatDate(p.payment_date) }} · {{ paymentMethodLabel(p.payment_method) }}
                                        <template v-if="p.reference"> · {{ p.reference }}</template>
                                    </span>
                                    <strong>{{ money(p.amount) }}</strong>
                                </div>
                            </div>
                            <p v-else class="muted-txt">{{ $t('rc_no_payments') }}</p>
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

        <PurchaseReceiptPrintDialog v-model="printVisible" :receipt-id="printReceiptId" />

        <!-- Create / edit: the receipt workspace (lines beside the catalogue). -->
        <PurchaseReceiptForm ref="receiptFormRef" @saved="onReceiptSaved" />

        <!-- Paying what is left on a receipt: a supplier payment linked to it. -->
        <el-dialog v-model="payDialog.visible" :title="$t('rc_pay_remaining')" width="440px" append-to-body>
            <el-form label-position="top" @submit.prevent="submitPayment">
                <p class="pay-dialog-meta">
                    {{ payDialog.receipt?.receipt_number }} · {{ payDialog.receipt?.supplier?.name }}
                    <br />
                    {{ $t('rc_due') }}: <strong>{{ money(payDialog.receipt?.due_amount) }}</strong>
                </p>
                <el-form-item :label="$t('amount')" required>
                    <el-input-number v-model="payDialog.amount" :min="0.01" :max="num(payDialog.receipt?.due_amount)" :precision="2" :controls="false" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('payment_method')" required>
                    <el-radio-group v-model="payDialog.payment_method">
                        <el-radio-button v-for="m in PAY_METHODS" :key="m" :value="m">{{ paymentMethodLabel(m) }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="$t('rc_payment_date')">
                    <el-date-picker v-model="payDialog.payment_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>
                <el-form-item v-if="payDialog.payment_method !== 'cash'" :label="$t('rc_payment_reference')">
                    <el-input v-model="payDialog.reference" maxlength="100" :placeholder="$t('rc_payment_reference_hint')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="payDialog.visible = false">{{ $t('cancel') }}</el-button>
                <el-button type="success" :loading="payDialog.saving" :disabled="!(num(payDialog.amount) > 0)" @click="submitPayment">
                    {{ $t('rc_record_payment') }}
                </el-button>
            </template>
        </el-dialog>

    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, onBeforeUnmount, computed, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';
import { useSuppliersStore } from '@/stores/suppliers';
import { useAuthStore } from '@/stores/auth';
import { useInventoryStore } from '@/stores/inventory';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { formatMoney, formatUnitPrice } from '@/utils/currency';
import { Search } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import VariantDetails from '@/components/admin/products/VariantDetails.vue';
import DocumentLinesPanel from '@/components/admin/sales/DocumentLinesPanel.vue';
import PurchaseReceiptPrintDialog from '@/components/admin/purchases/PurchaseReceiptPrintDialog.vue';
import PurchaseReceiptForm from '@/components/admin/purchases/PurchaseReceiptForm.vue';
import { variantLabelOf, lineVariantInfo } from '@/utils/productPick';
import { paymentMethodLabel } from '@/utils/sales';
import { matchesSearch } from '@/utils/search';
import { supplierPaymentsApi } from '@/api/supplierPayments';

const { t } = useI18n();

const route = useRoute();
const router = useRouter();
const store = usePurchaseReceiptsStore();
const suppliersStore = useSuppliersStore();
const authStore = useAuthStore();
const inventoryStore = useInventoryStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedReceipt = ref(null);

// Opened through its exposed methods; it reports back through `saved`.
const receiptFormRef = ref(null);

// A 55% drawer on a phone left too narrow a column to receive into.
const viewportWidth = ref(window.innerWidth);
const onResize = () => { viewportWidth.value = window.innerWidth; };
const drawerSize = computed(() => (viewportWidth.value < 900 ? '100%' : '55%'));

// As on the supplier-payments screen: no card, which this business does not
// pay suppliers by.
const PAY_METHODS = ['cash', 'bank_transfer', 'check'];

// Paying a supplier is an admin task: the payments API is behind role:admin
// (User::isAdmin), so only an admin is offered it. Offering it to anyone who
// may raise a purchase order (the sells role) only led to a refusal.
const mayPay = computed(() => Boolean(authStore.isSuperAdmin || authStore.user?.is_admin || authStore.hasRole(['admin', 'master'])));

const methodsText = (methods = []) => methods.map(paymentMethodLabel).join(' + ');

const num = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};
const money = (value) => formatMoney(value);
const formatDate = (value) => (value ? String(value).slice(0, 10) : '-');

// Local date: toISOString() is UTC, which dated a delivery booked just after
// midnight in Damascus to the day before.
const todayIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// ── Expandable list rows ─────────────────────────────────────────────────
// The index carries each receipt's lines with their variants.
const receiptsTable = ref(null);
const expandedIds = reactive(new Set());

const onExpandChange = (row, expandedRows) => {
    const open = Array.isArray(expandedRows) ? expandedRows.some((r) => r.id === row.id) : !!expandedRows;
    if (open) expandedIds.add(row.id);
    else expandedIds.delete(row.id);
};

const toggleLines = (row) => receiptsTable.value?.toggleRowExpansion(row);

const receiptLines = (receipt) => (receipt.items || []).map((item) => ({
    id: item.id,
    productId: item.product_id,
    image: item.product?.image_main || item.product?.image,
    name: item.product?.name_ar || item.description,
    sku: item.product?.sku,
    variant: item.variant,
    variantId: item.product_variant_id,
    quantity: num(item.quantity),
    unitPrice: item.unit_price,
    total: num(item.total) || num(item.unit_price) * num(item.quantity),
}));

const filteredReceipts = computed(() => {
    if (!searchQuery.value.trim()) return store.receipts;
    return store.receipts.filter((receipt) => {
        return matchesSearch([
            receipt.receipt_number,
            receipt.supplier?.name,
            receipt.purchase_order?.order_number,
            receipt.receipt_date,
            receipt.notes
        ], searchQuery.value);
    });
});

const recentCount = computed(() => Math.min(store.receipts.length, 5));

// Drawer Actions
// The goods received note for one receipt.
const printVisible = ref(false);
const printReceiptId = ref(null);
const openPrint = (id) => {
    printReceiptId.value = id;
    printVisible.value = true;
};

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

const openCreateDrawer = () => receiptFormRef.value?.openCreate();
const openEditDrawer = (id) => receiptFormRef.value?.openEdit(id);

const onReceiptSaved = async ({ id, edited }) => {
    await store.fetchReceipts();
    if (edited && detailDrawerVisible.value && selectedReceipt.value?.id === id) openDetailDrawer(id);
};

/* ------------------------------------------------------------------ *
 * Paying what is left on a receipt
 * ------------------------------------------------------------------ */

const payDialog = reactive({
    visible: false,
    saving: false,
    receipt: null,
    amount: 0,
    payment_method: 'cash',
    payment_date: '',
    reference: '',
});

const openPayDialog = (receipt) => {
    Object.assign(payDialog, {
        visible: true,
        saving: false,
        receipt,
        amount: num(receipt.due_amount),
        payment_method: 'cash',
        payment_date: todayIso(),
        reference: '',
    });
};

const submitPayment = async () => {
    const receipt = payDialog.receipt;
    if (!receipt || payDialog.saving || !(num(payDialog.amount) > 0)) return;
    payDialog.saving = true;
    try {
        await supplierPaymentsApi.create({
            supplier_id: receipt.supplier_id,
            purchase_receipt_id: receipt.id,
            purchase_order_id: receipt.purchase_order_id || null,
            amount: num(payDialog.amount),
            payment_method: payDialog.payment_method,
            payment_date: payDialog.payment_date || null,
            reference: payDialog.reference || null,
        });
        payDialog.visible = false;
        ElMessage.success(t('rc_payment_recorded'));
        await store.fetchReceipts();
        if (detailDrawerVisible.value && selectedReceipt.value?.id === receipt.id) {
            await openDetailDrawer(receipt.id);
        }
    } catch (error) {
        const errors = error.response?.data?.errors;
        ElMessage.error((errors && Object.values(errors).flat()[0]) || error.response?.data?.message || t('rc_payment_failed'));
    } finally {
        payDialog.saving = false;
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

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});

onMounted(() => {
    window.addEventListener('resize', onResize);
    // A purchase order's detail links here with ?search=<receipt number>.
    if (route.query.search) searchQuery.value = String(route.query.search);
    store.fetchReceipts().catch(() => {});
    suppliersStore.fetchSuppliers().catch(() => {});
    inventoryStore.fetchSummary().catch(() => {});

    const orderId = route.query.create_for_order;
    if (orderId) {
        // Drop the query param so a refresh/back-nav doesn't reopen the drawer.
        router.replace({ query: { ...route.query, create_for_order: undefined } });
        receiptFormRef.value?.openCreateForOrder(orderId);
    }
});
</script>

<style scoped>
.drawer-print-bar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.75rem;
}

.purchases-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
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

.text-danger {
    color: var(--el-color-danger, #dc2626) !important;
}

/* The size under the product's name in the detail table. */
.lines-toggle { all: unset; cursor: pointer; display: flex; align-items: center; gap: 0.3rem; margin-top: 0.25rem; font-size: 0.75rem; font-weight: 600; color: #2563eb; }
.lines-toggle:hover { text-decoration: underline; }
.lines-toggle:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }
.lines-chevron { font-size: 0.6rem; transition: transform 0.2s ease; }
.lines-chevron.open { transform: rotate(180deg); }

.cell-variant {
    margin-top: 0.2rem;
}

@media (max-width: 640px) {
    

    

    

    

    

    

    

    .financial-row {
        width: 100%;
    }
}

/* ---- Payment on the receipt ---- */
.paid-cell { display: flex; flex-direction: column; line-height: 1.25; }
.paid-cell small { color: var(--el-text-color-secondary); font-size: 0.75rem; }

.payments-card-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.muted-txt { color: var(--el-text-color-secondary); margin: 0; font-size: 0.85rem; }
.pay-dialog-meta { margin: 0 0 1rem; color: var(--el-text-color-regular); line-height: 1.7; }
.financial-row.text-danger { color: var(--el-color-danger); font-weight: 600; }
.financial-row.text-success { color: var(--el-color-success); font-weight: 600; }

.discount-badge-pill {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    padding: 1px 6px;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.discount-table-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 2px;
}
.discount-table-tag i {
    font-size: 0.65rem;
}
.financial-row.discount-row {
    color: #ef4444;
}

.empty-icon {
    font-size: 2.2rem;
    color: #cbd5e1;
}


</style>
