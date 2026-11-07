<template>
    <el-drawer
        v-model="visible"
        :size="drawerSize"
        direction="rtl"
        destroy-on-close
        class="pf-drawer"
        :before-close="confirmClose"
        @opened="onOpened"
    >
        <template #header>
            <div class="pf-title">
                <span class="pf-title-icon is-receipt"><i class="fas fa-truck-ramp-box"></i></span>
                <div>
                    <h2>
                        {{ isEdit ? $t('edit_goods_receipt') : $t('create_goods_receipt') }}
                        <span v-if="isEdit && receiptNumber" class="pf-title-tag" dir="ltr">{{ receiptNumber }}</span>
                    </h2>
                    <p>{{ isEdit ? $t('receipt_lines_locked_notice') : $t('pf_rc_sub') }}</p>
                </div>
            </div>
        </template>

        <div
            v-loading="loading"
            class="pf-shell"
            :class="{ 'is-single': isEdit }"
            @keydown.ctrl.enter.prevent="save"
            @keydown.meta.enter.prevent="save"
            @keydown.f2.exact.prevent="focusCatalog"
            @keydown.f4.exact.prevent="!isEdit && openQuickAddProduct('')"
        >
            <div class="pf-doc">
                <!-- Where it came from, against which request, where it went -->
                <section ref="headRef" class="pf-card pf-card-pad">
                    <div class="pf-fields">
                        <div class="pf-field" :class="{ 'is-invalid': attempted && !form.supplier_id }">
                            <span class="pf-label">{{ $t('supplier') }} <span class="pf-req">*</span></span>
                            <div class="pf-row">
                                <el-select
                                    ref="supplierRef"
                                    v-model="form.supplier_id"
                                    :placeholder="$t('select_supplier')"
                                    filterable
                                    :disabled="isEdit"
                                    @change="onSupplierChange"
                                >
                                    <el-option
                                        v-for="s in suppliersStore.suppliers"
                                        :key="s.id"
                                        :label="s.company ? `${s.name} — ${s.company}` : s.name"
                                        :value="s.id"
                                    />
                                </el-select>
                                <el-button v-if="!isEdit" plain :title="$t('add_new_supplier')" @click="supplierDialog = true">
                                    <i class="fas fa-user-plus"></i>
                                </el-button>
                            </div>
                        </div>

                        <!-- Scoped to the supplier, and to requests that can still
                             take goods: listing received and cancelled ones invited
                             receiving the same delivery twice. -->
                        <div class="pf-field">
                            <span class="pf-label">{{ $t('linked_purchase_order') }}</span>
                            <el-select
                                v-model="form.purchase_order_id"
                                :placeholder="!form.supplier_id ? $t('select_supplier_first') : (openOrders.length ? $t('select_purchase_order_or_direct') : $t('rc_no_open_orders'))"
                                filterable
                                clearable
                                :disabled="!form.supplier_id || isEdit"
                                :loading="ordersLoading"
                                style="width: 100%"
                                @change="onOrderChange"
                            >
                                <el-option v-for="o in openOrders" :key="o.id" :label="o.order_number" :value="o.id">
                                    <div class="pf-row" style="justify-content: space-between; width: 100%">
                                        <span>
                                            <strong dir="ltr">{{ o.order_number }}</strong>
                                            &nbsp;<el-tag size="small" :type="orderStageTag(o.status)" effect="light">{{ orderStageLabel(o.status) }}</el-tag>
                                        </span>
                                        <small class="pf-hint">{{ String(o.order_date || o.created_at || '').slice(0, 10) }} · {{ money(o.total) }}</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <small v-if="form.supplier_id && !isEdit" class="pf-hint">
                                {{ openOrders.length ? $t('rc_open_orders_hint', openOrders.length) : $t('pf_direct_purchase') }}
                            </small>
                        </div>

                        <div class="pf-field" :class="{ 'is-invalid': attempted && !form.warehouse_id }">
                            <span class="pf-label">{{ $t('receiving_warehouse') }} <span class="pf-req">*</span></span>
                            <el-select v-model="form.warehouse_id" :placeholder="$t('choose_warehouse')" :disabled="isEdit" style="width: 100%">
                                <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                            </el-select>
                        </div>

                        <div class="pf-field">
                            <span class="pf-label">{{ $t('actual_receipt_date') }}</span>
                            <el-date-picker
                                v-model="form.receipt_date"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                :clearable="false"
                                style="width: 100%"
                            />
                        </div>
                    </div>

                    <!-- The request beside the receipt, so the two can be checked
                         against each other. -->
                    <div v-if="linkedOrder" class="pf-linked" style="margin-top: 1rem">
                        <div class="pf-linked-head">
                            <span class="pf-row">
                                <i class="fas fa-file-signature" style="color: #4f46e5"></i>
                                <strong dir="ltr">{{ linkedOrder.order_number }}</strong>
                                <el-tag size="small" :type="orderStageTag(linkedOrder.status)" effect="light">{{ orderStageLabel(linkedOrder.status) }}</el-tag>
                            </span>
                            <span class="pf-row">
                                <el-button v-if="!isEdit && orderLines.length" size="small" text type="primary" @click="refillFromOrder">
                                    <i class="fas fa-rotate"></i>&nbsp;{{ $t('rc_refill_from_order') }}
                                </el-button>
                                <router-link :to="{ path: '/admin/purchases/orders', query: { search: linkedOrder.order_number } }" class="el-link el-link--primary" style="font-size: 0.8rem">
                                    {{ $t('view_details') }} <i class="fas fa-arrow-up-right-from-square"></i>
                                </router-link>
                            </span>
                        </div>
                        <div class="pf-linked-facts">
                            <div><span>{{ $t('po_order_date') }}</span><strong>{{ String(linkedOrder.order_date || linkedOrder.created_at || '-').slice(0, 10) }}</strong></div>
                            <div><span>{{ $t('due_date') }}</span><strong :class="{ 'text-danger': linkedOrderOverdue }">{{ linkedOrder.due_date ? String(linkedOrder.due_date).slice(0, 10) : '-' }}</strong></div>
                            <div><span>{{ $t('rc_order_total') }}</span><strong>{{ money(linkedOrder.total) }}</strong></div>
                            <div><span>{{ $t('items_count') }}</span><strong>{{ orderLines.length || linkedOrder.items?.length || 0 }}</strong></div>
                        </div>
                        <template v-if="!isEdit && orderProgress.ordered > 0">
                            <div class="pf-row" style="justify-content: space-between; font-size: 0.75rem">
                                <span>{{ $t('pf_receive_progress', { got: orderProgress.after, of: orderProgress.ordered }) }}</span>
                                <strong>{{ orderProgress.pct }}%</strong>
                            </div>
                            <div class="pf-progress"><span :style="{ width: `${orderProgress.pct}%` }"></span></div>
                        </template>
                        <el-alert v-if="orderPendingApproval" type="warning" :closable="false" show-icon :title="$t('rc_order_not_approved')" />
                        <el-alert v-if="orderAlreadyReceived && !isEdit" type="info" :closable="false" show-icon :title="$t('rc_order_already_received')" />
                    </div>
                </section>

                <div v-if="!isEdit && showChecklist" class="pf-checklist" :class="{ 'is-ready': !blockers.length }">
                    <span class="pf-checklist-title">
                        <i class="fas" :class="blockers.length ? 'fa-list-check' : 'fa-circle-check'"></i>
                        {{ blockers.length ? $t('pf_before_saving') : $t('pf_ready_to_save') }}
                    </span>
                    <button v-for="b in blockers" :key="b.id" type="button" @click="focusBlocker(b)">
                        <i class="fas" :class="b.icon"></i> {{ b.label }}
                    </button>
                </div>

                <!-- What came -->
                <section ref="linesRef" class="pf-card pf-lines-card">
                    <header class="pf-card-head">
                        <h3 class="pf-card-title">
                            <i class="fas fa-dolly"></i>
                            {{ $t('incoming_items_and_quantities') }}
                            <span class="pf-count">{{ form.items.length }}</span>
                        </h3>
                        <div v-if="!isEdit" class="pf-card-tools">
                            <el-button v-if="linkedOrder && orderLines.length" size="small" type="success" plain @click="receiveAllAsOrdered">
                                <i class="fas fa-check-double"></i>&nbsp;{{ $t('rc_all_arrived') }}
                            </el-button>
                            <el-button v-if="form.items.length" size="small" text type="danger" @click="clearLines">
                                <i class="fas fa-eraser"></i>&nbsp;{{ $t('pf_clear_lines') }}
                            </el-button>
                        </div>
                    </header>

                    <template v-if="form.items.length">
                        <div class="pf-lines-head" :class="{ 'is-locked': isEdit }">
                            <span>#</span>
                            <span>{{ $t('product') }}</span>
                            <span>{{ $t('quantity') }}</span>
                            <span>{{ $t('purchase_cost') }}</span>
                            <span>{{ $t('sale_price') }}</span>
                            <span>{{ $t('po_line_total') }}</span>
                            <span v-if="!isEdit"></span>
                        </div>
                        <div class="pf-lines">
                            <PurchaseLineRow
                                v-for="(item, idx) in form.items"
                                :key="item.key"
                                :item="item"
                                :index="idx"
                                :row="cache.rowOf(item.pick)"
                                :siblings="siblingsOf(item)"
                                :locked="isEdit"
                                :currency="currencyCode"
                                :tone="lineTone(item)"
                                :taken-by="(v) => lineNumberOf(v, idx)"
                                :flagged-picks="orderedPicks"
                                :flagged-short="$t('rc_ordered_short')"
                                :last-cost="!isEdit && orderLineFor(item) ? orderLineFor(item).unit_price : null"
                                :last-cost-label="$t('pf_request_price')"
                                @switch-variant="(v) => switchVariant(idx, v)"
                                @remove="removeLine(idx)"
                            >
                                <template #notes>
                                    <div v-if="duplicatePicks.has(item.pick)" class="pf-line-note">
                                        <span class="pf-badge is-danger"><i class="fas fa-clone"></i> {{ $t('pf_duplicate_line') }}</span>
                                    </div>
                                    <div v-if="linkedOrder && item.product_id && !isEdit" class="pf-line-note">
                                        <template v-if="lineVsOrder(item).state === 'extra'">
                                            <span class="pf-badge is-info"><i class="fas fa-circle-plus"></i> {{ $t('rc_not_in_order') }}</span>
                                        </template>
                                        <template v-else>
                                            <span>{{ $t('rc_ordered_n', { n: lineVsOrder(item).ordered }) }}</span>
                                            <span v-if="lineVsOrder(item).before">· {{ $t('rc_received_before_n', { n: lineVsOrder(item).before }) }}</span>
                                            <span v-if="lineVsOrder(item).state === 'short'" class="pf-badge is-warn">{{ $t('rc_short_n', { n: lineVsOrder(item).diff }) }}</span>
                                            <span v-else-if="lineVsOrder(item).state === 'over'" class="pf-badge is-danger">{{ $t('rc_over_n', { n: lineVsOrder(item).diff }) }}</span>
                                            <span v-else class="pf-badge is-ok"><i class="fas fa-check"></i> {{ $t('rc_matches_order') }}</span>
                                        </template>
                                    </div>
                                </template>
                            </PurchaseLineRow>
                        </div>
                    </template>

                    <div v-else class="pf-empty">
                        <span class="pf-empty-icon"><i class="fas fa-dolly"></i></span>
                        <strong>{{ $t('pf_no_lines_title') }}</strong>
                        <p>{{ form.supplier_id && openOrders.length && !form.purchase_order_id ? $t('pf_no_lines_rc_pick') : $t('pf_no_lines_rc') }}</p>
                        <div class="pf-row">
                            <el-button type="primary" @click="focusCatalog"><i class="fas fa-magnifying-glass"></i>&nbsp;{{ $t('pf_search_catalog') }}</el-button>
                            <el-button @click="openQuickAddProduct('')"><i class="fas fa-plus"></i>&nbsp;{{ $t('pqa_button') }}</el-button>
                        </div>
                    </div>

                    <!-- Request lines still owed that nothing here covers yet -->
                    <div v-if="!isEdit && missingOrderLines.length" class="pf-missing">
                        <span>{{ $t('rc_missing_lines') }}</span>
                        <button v-for="line in missingOrderLines" :key="linePick(line)" type="button" @click="restoreOrderLine(line)">
                            <i class="fas fa-plus"></i>
                            {{ line.product?.name_ar || line.product_name }}
                            <span v-if="line.product_variant_id" dir="ltr">({{ variantLabelOf(line.variant) || line.variant_label || '' }})</span>
                            <span dir="ltr">×{{ line.remaining_quantity }}</span>
                        </button>
                    </div>

                    <footer v-if="form.items.length" class="pf-lines-foot">
                        <span>{{ $t('po_items_count', filledCount) }} · {{ $t('rc_units_n', { n: totalUnits }) }}</span>
                        <span>{{ $t('rc_goods_value') }}: <strong>{{ money(goodsTotal) }}</strong></span>
                    </footer>
                </section>

                <!-- Notes, payment and the money -->
                <div class="pf-bottom">
                    <div style="display: flex; flex-direction: column; gap: 1rem">
                        <section v-if="!isEdit && mayPay" ref="payRef" class="pf-card pf-card-pad">
                            <span class="pf-label" style="margin-bottom: 0.5rem"><i class="fas fa-money-bill-wave"></i> {{ $t('rc_paid_now') }}</span>
                            <div class="pf-fields">
                                <div class="pf-field span-12" :class="{ 'is-invalid': paidTooMuch }">
                                    <div class="pf-row">
                                        <div class="pf-money-input" style="flex: 1">
                                            <input
                                                v-model="form.paid_amount"
                                                type="number"
                                                inputmode="decimal"
                                                min="0"
                                                step="0.01"
                                                dir="ltr"
                                                placeholder="0.00"
                                            />
                                            <span class="pf-unit">{{ currencyCode }}</span>
                                        </div>
                                        <el-button plain @click="form.paid_amount = round2(receiptTotal)">{{ $t('rc_pay_in_full') }}</el-button>
                                    </div>
                                    <small v-if="paidTooMuch" class="pf-hint" style="color: #b91c1c">{{ $t('pf_paid_exceeds') }}</small>
                                </div>
                                <div class="pf-field span-12">
                                    <el-radio-group v-model="form.payment_method" :disabled="num(form.paid_amount) <= 0" size="default">
                                        <el-radio-button v-for="m in PAY_METHODS" :key="m" :value="m">{{ paymentMethodLabel(m) }}</el-radio-button>
                                    </el-radio-group>
                                </div>
                                <div v-if="num(form.paid_amount) > 0 && form.payment_method !== 'cash'" class="pf-field span-12">
                                    <el-input v-model="form.payment_reference" maxlength="100" :placeholder="$t('rc_payment_reference_hint')" />
                                </div>
                            </div>
                        </section>

                        <section class="pf-card pf-card-pad">
                            <span class="pf-label" style="margin-bottom: 0.4rem"><i class="fas fa-note-sticky"></i> {{ $t('receipt_notes') }}</span>
                            <el-input
                                v-model="form.notes"
                                type="textarea"
                                :rows="4"
                                maxlength="1000"
                                show-word-limit
                                :placeholder="$t('receipt_notes_placeholder')"
                            />
                        </section>
                    </div>

                    <section ref="totalsRef" class="pf-card pf-card-pad pf-totals">
                        <div class="pf-sum-row">
                            <span>{{ $t('rc_goods_value') }}</span>
                            <strong>{{ money(goodsTotal) }}</strong>
                        </div>

                        <PurchaseDiscountControl
                            v-if="!isEdit"
                            :form="form"
                            :ctl="discount"
                            :currency="currencyCode"
                            :invalid="discountTooLarge"
                            :invalid-text="$t('rc_discount_exceeds_goods')"
                        />
                        <div v-else-if="num(form.discount) > 0" class="pf-sum-row is-minus">
                            <span>{{ $t('discount') }}</span>
                            <span class="pf-num">− {{ money(form.discount) }}</span>
                        </div>

                        <div v-if="!isEdit" class="pf-field span-12">
                            <span class="pf-label"><i class="fas fa-receipt"></i> {{ $t('purchase_tax_amount') }}</span>
                            <div class="pf-money-input">
                                <input v-model="form.tax_amount" type="number" inputmode="decimal" min="0" step="0.01" dir="ltr" placeholder="0.00" />
                                <span class="pf-unit">{{ currencyCode }}</span>
                            </div>
                            <small class="pf-hint">{{ $t('purchase_tax_hint') }}</small>
                        </div>
                        <div v-else-if="num(form.tax_amount) > 0" class="pf-sum-row">
                            <span>{{ $t('tax_label') }}</span>
                            <span class="pf-num">+ {{ money(form.tax_amount) }}</span>
                        </div>

                        <div class="pf-grand" :class="{ 'is-invalid': discountTooLarge }">
                            <span>{{ $t('rc_total') }}</span>
                            <strong>{{ money(receiptTotal) }}</strong>
                        </div>

                        <template v-if="!isEdit">
                            <div v-if="num(form.paid_amount) > 0" class="pf-sum-row is-minus">
                                <span>{{ $t('rc_paid_now') }} ({{ paymentMethodLabel(form.payment_method) }})</span>
                                <span class="pf-num">− {{ money(form.paid_amount) }}</span>
                            </div>
                            <div class="pf-sum-row">
                                <span>{{ $t('rc_owed_to_supplier') }}</span>
                                <strong>{{ money(owedAfterPayment) }}</strong>
                            </div>
                            <div v-if="linkedOrder && Math.abs(receiptTotal - num(linkedOrder.total)) >= 0.01" class="pf-sum-row is-muted">
                                <span>{{ $t('rc_vs_order_total') }}</span>
                                <span class="pf-num">{{ receiptTotal > num(linkedOrder.total) ? '+' : '−' }} {{ money(Math.abs(receiptTotal - num(linkedOrder.total))) }}</span>
                            </div>
                        </template>
                    </section>
                </div>
            </div>

            <aside v-if="!isEdit" class="pf-side" :class="{ 'is-folded': catalogFolded }">
                <button type="button" class="pf-catalog-toggle" @click="catalogFolded = !catalogFolded">
                    <span><i class="fas fa-store"></i> {{ $t('pf_catalog') }}</span>
                    <i class="fas" :class="catalogFolded ? 'fa-chevron-down' : 'fa-chevron-up'"></i>
                </button>
                <PurchaseCatalogPanel
                    ref="catalogRef"
                    :count-of="countOf"
                    :categories="categoryOptions"
                    :add-label="$t('rc_add_to_receipt')"
                    :highlight-picks="orderedPicks"
                    :highlight-rows="orderRows"
                    :highlight-label="$t('pf_show_request_items')"
                    :highlight-short="$t('rc_ordered_short')"
                    @rows="cache.remember"
                    @add="addRow"
                    @set="setCount"
                    @commit="commitCount"
                    @create="openQuickAddProduct"
                />
            </aside>
        </div>

        <template #footer>
            <div class="pf-footer">
                <div class="pf-footer-sum">
                    <div class="pf-footer-metric is-main" :class="{ 'is-invalid': discountTooLarge || paidTooMuch }">
                        <span>{{ num(form.paid_amount) > 0 ? $t('rc_owed_to_supplier') : $t('rc_total') }}</span>
                        <strong>{{ money(num(form.paid_amount) > 0 ? owedAfterPayment : receiptTotal) }}</strong>
                    </div>
                    <span class="pf-footer-sep"></span>
                    <div class="pf-footer-metric">
                        <span>{{ $t('items') }}</span>
                        <strong>{{ filledCount }}</strong>
                    </div>
                    <div class="pf-footer-metric">
                        <span>{{ $t('pf_units') }}</span>
                        <strong>{{ totalUnits }}</strong>
                    </div>
                </div>
                <div class="pf-footer-actions">
                    <span class="pf-kbd-hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd><template v-if="!isEdit"> · <kbd>F2</kbd> {{ $t('search') }} · <kbd>F4</kbd> {{ $t('pqa_button') }}</template></span>
                    <el-button @click="confirmClose()">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="saving" @click="save">
                        <i class="fas fa-truck-ramp-box"></i>&nbsp;{{ isEdit ? $t('save') : $t('save_receipt') }}
                    </el-button>
                </div>
            </div>
        </template>
    </el-drawer>

    <ProductQuickAdd
        v-model="quickAddVisible"
        purchase
        :seed="quickAddSeed"
        seed-cost=""
        :categories="categoryOptions"
        :stock-note="$t('pqa_stock_note_purchase')"
        @created="onQuickProduct"
        @use-existing="onQuickProduct"
    />

    <SupplierQuickAddDialog v-model="supplierDialog" @created="onSupplierCreated" />
</template>

<script setup>
/**
 * Recording a goods receipt: what a supplier delivered, into which
 * warehouse, against which purchase request, and what was paid on the spot.
 *
 * Saving it takes the goods into stock and posts their cost, so its lines are
 * fixed afterwards — editing a receipt changes only its date, notes and
 * request link. Laid out like the order form: the receipt beside the
 * catalogue, with the request's lines marked in the catalogue and each line
 * compared with what the request still expects.
 */
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useAuthStore } from '@/stores/auth';
import { useSuppliersStore } from '@/stores/suppliers';
import { useInventoryStore } from '@/stores/inventory';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { categoriesApi } from '@/api/categories';
import { baseCurrencyCode, formatMoney } from '@/utils/currency';
import { normalizePurchaseOrderStatus } from '@/utils/purchaseOrderStatus';
import { paymentMethodLabel } from '@/utils/sales';
import { optionKey, pickKey, variantLabelOf, variantFieldsOf, optionFromLine } from '@/utils/productPick';
import { num, todayIso, usePurchaseProductCache, useDocumentDiscount } from '@/composables/usePurchaseDocument';
import PurchaseCatalogPanel from '@/components/admin/purchases/PurchaseCatalogPanel.vue';
import PurchaseLineRow from '@/components/admin/purchases/PurchaseLineRow.vue';
import PurchaseDiscountControl from '@/components/admin/purchases/PurchaseDiscountControl.vue';
import SupplierQuickAddDialog from '@/components/admin/purchases/SupplierQuickAddDialog.vue';
import ProductQuickAdd from '@/components/admin/sales/ProductQuickAdd.vue';
import './purchase-form.css';

const emit = defineEmits(['saved']);

const { t, locale } = useI18n();
const authStore = useAuthStore();
const suppliersStore = useSuppliersStore();
const inventoryStore = useInventoryStore();

const currencyCode = computed(() => baseCurrencyCode());
const money = (v) => formatMoney(v);
const round2 = (n) => Math.round(num(n) * 100) / 100;

// Paying a supplier is for an admin only — the API refuses a receipt that
// carries a payment from anyone else (User::isAdmin), so nobody else is
// offered the field only to have the whole receipt refused.
const mayPay = computed(() => Boolean(authStore.isSuperAdmin || authStore.user?.is_admin || authStore.hasRole(['admin', 'master'])));
// No card: this business does not pay suppliers by card.
const PAY_METHODS = ['cash', 'bank_transfer', 'check'];

/* Drawer ----------------------------------------------------------- */

const viewportWidth = ref(window.innerWidth);
const onResize = () => { viewportWidth.value = window.innerWidth; };
const drawerSize = computed(() => (viewportWidth.value < 1100 ? '100%' : '94%'));

const visible = ref(false);
const loading = ref(false);
const saving = ref(false);
const attempted = ref(false);
const isEdit = ref(false);
const editingId = ref(null);
const receiptNumber = ref('');
const catalogFolded = ref(false);

/* Form ------------------------------------------------------------- */

let lineSeq = 0;
const newLine = (fields = {}) => ({
    key: ++lineSeq,
    pick: '',
    product_id: '',
    product_variant_id: null,
    quantity: 1,
    unit_price: '',
    sale_price: '',
    ...fields,
});

const form = reactive({
    supplier_id: '',
    purchase_order_id: '',
    warehouse_id: '',
    receipt_date: '',
    tax_amount: 0,
    discount: 0,
    discount_percent: '',
    discount_mode: 'percent',
    notes: '',
    // Paid on the spot, if anything; the rest stays owed.
    paid_amount: 0,
    payment_method: 'cash',
    payment_reference: '',
    items: [],
});

const cache = usePurchaseProductCache();
const warehouses = computed(() => inventoryStore.warehouses);

// The primary warehouse, or the only one — otherwise the operator chooses.
const defaultWarehouseId = () => {
    const list = warehouses.value;
    if (list.length === 1) return list[0].id;
    return list.find((w) => w.is_primary)?.id || '';
};

watch(warehouses, () => {
    if (visible.value && !isEdit.value && !form.warehouse_id) form.warehouse_id = defaultWarehouseId();
});

const goodsTotal = computed(() => form.items.reduce((sum, l) => sum + num(l.quantity) * num(l.unit_price), 0));
const discount = useDocumentDiscount(form, goodsTotal);
// The API refuses a discount above the goods (tax is not discounted).
const discountTooLarge = computed(() => num(form.discount) > goodsTotal.value + 0.000001);
const receiptTotal = computed(() => Math.max(0, goodsTotal.value - num(form.discount)) + num(form.tax_amount));
const owedAfterPayment = computed(() => Math.max(0, receiptTotal.value - num(form.paid_amount)));
const paidTooMuch = computed(() => mayPay.value && num(form.paid_amount) > round2(receiptTotal.value) + 0.01);
const filledCount = computed(() => form.items.filter((l) => l.product_id).length);
const totalUnits = computed(() => form.items.reduce((sum, l) => sum + num(l.quantity), 0));

/* The request a receipt is recorded against --------------------------- */

// Straight from the API rather than the purchase-orders store, which backs
// the orders screen: loading one supplier's requests into it replaced that
// screen's list and its counts.
const supplierOrders = ref([]);
const ordersLoading = ref(false);
const linkedOrder = ref(null);
// The request's lines with what earlier receipts already brought in.
const orderLines = ref([]);

const stageOf = (order) => normalizePurchaseOrderStatus(order?.status);
const remainingOf = (order) => (order?.items || [])
    .reduce((sum, item) => sum + Math.max(0, num(item.quantity) - num(item.received_quantity)), 0);

// Requests that can still take goods, approved first. A received request
// stays only while part of it is still owed.
const openOrders = computed(() => {
    const rank = { confirmed: 0, processing: 0, pending: 1, completed: 2 };
    const list = supplierOrders.value.filter((order) => {
        const stage = stageOf(order);
        if (stage === 'cancelled') return false;
        if (stage === 'completed') return remainingOf(order) > 0;
        return true;
    });
    if (linkedOrder.value && !list.some((o) => o.id === linkedOrder.value.id)) list.push(linkedOrder.value);
    return list.sort((a, b) => (rank[stageOf(a)] ?? 3) - (rank[stageOf(b)] ?? 3) || b.id - a.id);
});

const orderStageLabel = (status) => ({
    pending: t('awaiting_approval'),
    confirmed: t('sales_status_confirmed'),
    processing: t('sales_status_processing'),
    completed: t('received_state'),
    cancelled: t('sales_status_cancelled'),
}[stageOf({ status })] || status);

const orderStageTag = (status) => ({
    pending: 'warning',
    confirmed: 'primary',
    processing: 'primary',
    completed: 'success',
    cancelled: 'danger',
}[stageOf({ status })] || 'info');

const orderPendingApproval = computed(() => stageOf(linkedOrder.value) === 'pending');
const orderAlreadyReceived = computed(() => orderLines.value.some((line) => line.received_quantity > 0));
const linkedOrderOverdue = computed(() => {
    const due = linkedOrder.value?.due_date;
    return !!due && String(due).slice(0, 10) < todayIso() && stageOf(linkedOrder.value) !== 'completed';
});

const loadSupplierOrders = async (supplierId) => {
    if (!supplierId) {
        supplierOrders.value = [];
        return;
    }
    ordersLoading.value = true;
    try {
        const res = await purchaseOrdersApi.getAll({ supplier_id: supplierId, per_page: 100 });
        supplierOrders.value = res.data?.data?.orders || [];
    } catch {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
    } finally {
        ordersLoading.value = false;
    }
};

const linePick = (line) => pickKey(line.product_id, line.product_variant_id);

/**
 * The request line a receipt line fills: the same size, or — for a size the
 * request only asked for as the product — the product's own line. The server
 * settles received quantities the same way.
 */
const orderLineFor = (item) => {
    const exact = orderLines.value.find((line) => line.product_id === item.product_id
        && (line.product_variant_id || null) === (item.product_variant_id || null));
    if (exact || !item.product_variant_id) return exact;
    return orderLines.value.find((line) => line.product_id === item.product_id && !line.product_variant_id);
};

/** How a receipt line compares with what the request still expects. */
const lineVsOrder = (item) => {
    const line = orderLineFor(item);
    if (!line) return { state: 'extra' };
    const expected = line.remaining_quantity > 0 ? line.remaining_quantity : line.quantity;
    const qty = num(item.quantity);
    return {
        ordered: line.quantity,
        before: line.received_quantity,
        diff: Math.abs(qty - expected),
        state: qty < expected ? 'short' : (qty > expected ? 'over' : 'match'),
    };
};

const orderedPicks = computed(() => new Set(orderLines.value.filter((l) => l.product_id).map(linePick)));
// The request's lines as catalogue rows, for "show only what was ordered".
const orderRows = computed(() => orderLines.value.filter((l) => l.product_id).map((l) => optionFromLine(l)));

const missingOrderLines = computed(() => orderLines.value.filter((line) => line.product_id
    && line.remaining_quantity > 0
    && !form.items.some((item) => item.pick === linePick(line))));

// How much of the request will be in once this receipt is saved.
const orderProgress = computed(() => {
    const ordered = orderLines.value.reduce((s, l) => s + num(l.quantity), 0);
    const before = orderLines.value.reduce((s, l) => s + Math.min(num(l.quantity), num(l.received_quantity)), 0);
    const now = form.items.reduce((s, item) => (orderLineFor(item) ? s + num(item.quantity) : s), 0);
    const after = Math.min(ordered, before + now);
    return { ordered, after, pct: ordered > 0 ? Math.round((after / ordered) * 100) : 0 };
});

const lineFromOrder = (line, quantity) => newLine({
    pick: linePick(line),
    product_id: line.product_id,
    product_variant_id: line.product_variant_id || null,
    variant: line.variant ? variantFieldsOf(line.variant) : null,
    product_name: line.product?.name_ar || line.product_name || '',
    quantity: Math.max(1, Math.floor(num(quantity)) || 1),
    unit_price: num(line.unit_price),
    sale_price: line.sale_price != null && line.sale_price !== '' ? num(line.sale_price) : '',
});

const fillLinesFromOrder = () => {
    const owed = orderLines.value.filter((line) => line.product_id && line.remaining_quantity > 0);
    // Nothing left owed (a repeat delivery): the whole request again, and
    // the lines say "over".
    const source = owed.length ? owed : orderLines.value.filter((line) => line.product_id);
    form.items = source.map((line) => lineFromOrder(line, owed.length ? line.remaining_quantity : line.quantity));
};

// The request's discount as a share of its goods, so a part delivery gets
// its part of the discount rather than all of it.
const applyOrderDiscount = (data) => {
    const order = data?.purchase_order || data || {};
    const pct = data?.discount_percent != null && data.discount_percent !== '' ? num(data.discount_percent)
        : (order.discount_percent != null && order.discount_percent !== '' ? num(order.discount_percent) : null);
    const amount = num(data?.discount ?? order.discount);
    const orderSubtotal = num(order.subtotal);
    form.discount_mode = 'percent';
    if (pct > 0) form.discount_percent = pct;
    else if (amount > 0 && orderSubtotal > 0) form.discount_percent = round2((amount / orderSubtotal) * 100);
    else form.discount_percent = '';
    discount.fromPercent();
};

/**
 * Fills the receipt from its request: lines at the quantities still owed and
 * the agreed prices, the request's tax on a first delivery, its discount, a
 * note naming it, and the warehouse.
 */
const applyOrder = async (orderId, { askBeforeReplacing = true } = {}) => {
    const response = await purchaseReceiptsApi.getPurchaseOrderDetails(orderId);
    const data = response.data?.data;
    if (!data) return;

    if (data.receivable === false) {
        ElMessage.warning(t('rc_order_cancelled'));
        form.purchase_order_id = '';
        return;
    }

    let replace = true;
    if (askBeforeReplacing && form.items.length) {
        try {
            await ElMessageBox.confirm(t('rc_replace_lines_message'), t('linked_purchase_order'), {
                type: 'warning',
                confirmButtonText: t('rc_replace_lines'),
                cancelButtonText: t('rc_keep_lines'),
                distinguishCancelAndClose: true,
            });
        } catch (action) {
            // Closing the box backs out of linking; "keep mine" links the
            // request and compares against it.
            if (action !== 'cancel') {
                form.purchase_order_id = linkedOrder.value?.id || '';
                return;
            }
            replace = false;
        }
    }

    linkedOrder.value = data.purchase_order;
    orderLines.value = (data.items || []).map((line) => ({
        ...line,
        quantity: num(line.quantity),
        received_quantity: num(line.received_quantity),
        remaining_quantity: num(line.remaining_quantity ?? line.quantity),
    }));
    cache.remember(orderLines.value, { loadSizes: true });

    if (data.supplier_id && !form.supplier_id) form.supplier_id = data.supplier_id;
    if (replace) fillLinesFromOrder();

    // Tax belongs to the delivery it was invoiced on; a later delivery of the
    // same request would book it twice.
    if (!orderAlreadyReceived.value && !(data.purchase_order?.receipts_count > 0)) {
        form.tax_amount = num(data.purchase_order?.tax);
    } else {
        form.tax_amount = 0;
    }
    applyOrderDiscount(data);

    if (!form.notes) form.notes = t('rc_default_note', { number: data.purchase_order?.order_number });
    if (!form.warehouse_id) form.warehouse_id = defaultWarehouseId();

    ElMessage.success(t('items_filled_from_purchase_order'));
};

const refillFromOrder = async () => {
    try {
        await ElMessageBox.confirm(t('rc_replace_lines_message'), t('rc_refill_from_order'), {
            type: 'warning',
            confirmButtonText: t('rc_replace_lines'),
            cancelButtonText: t('cancel'),
        });
    } catch {
        return;
    }
    fillLinesFromOrder();
    if (linkedOrder.value) applyOrderDiscount({ purchase_order: linkedOrder.value });
};

// The common case — everything came as asked — in one click.
const receiveAllAsOrdered = () => {
    form.items.forEach((item) => {
        const line = orderLineFor(item);
        if (line) item.quantity = Math.max(1, line.remaining_quantity > 0 ? line.remaining_quantity : line.quantity);
    });
    missingOrderLines.value.forEach((line) => restoreOrderLine(line));
};

const restoreOrderLine = (line) => {
    cache.remember([line], { loadSizes: true });
    form.items.push(lineFromOrder(line, line.remaining_quantity));
};

const onSupplierChange = async (supplierId) => {
    // Another supplier's request can never be received on this receipt (the
    // API refuses it), so a link to one goes, and the lines it brought.
    if (form.purchase_order_id || linkedOrder.value) {
        form.purchase_order_id = '';
        linkedOrder.value = null;
        orderLines.value = [];
        form.items = [];
        form.tax_amount = 0;
        form.discount_percent = '';
        discount.fromPercent();
    }
    await loadSupplierOrders(supplierId);

    // One request waiting on this supplier is almost certainly this delivery.
    const receivable = openOrders.value.filter((o) => ['confirmed', 'processing'].includes(stageOf(o)));
    if (receivable.length === 1 && !form.items.length) {
        form.purchase_order_id = receivable[0].id;
        await onOrderChange(receivable[0].id);
    }
};

const onSupplierCreated = (supplier) => {
    form.supplier_id = supplier.id;
    onSupplierChange(supplier.id);
};

const onOrderChange = async (orderId) => {
    if (!orderId) {
        // Unlinked: a direct purchase. What was entered stays.
        linkedOrder.value = null;
        orderLines.value = [];
        return;
    }
    loading.value = true;
    try {
        await applyOrder(orderId);
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_load_purchase_order_data'));
        form.purchase_order_id = linkedOrder.value?.id || '';
    } finally {
        loading.value = false;
    }
};

/* Lines ------------------------------------------------------------ */

const siblingsOf = (item) => (item.product_id ? cache.siblingsOf(item.product_id) : []);

// Stock comes in once per product (or size) per receipt, so a second line for
// the same one would never reach the warehouse. The API refuses it too.
const duplicatePicks = computed(() => {
    const seen = new Set();
    const dupes = new Set();
    form.items.forEach(({ pick }) => {
        if (pick) (seen.has(pick) ? dupes : seen).add(pick);
    });
    return dupes;
});

const lineTone = (item) => {
    if (!item.product_id || duplicatePicks.value.has(item.pick)) return 'danger';
    if (attempted.value && (item.unit_price === '' || item.unit_price == null)) return 'warn';
    if (!linkedOrder.value || isEdit.value) return 'ok';
    const { state } = lineVsOrder(item);
    return { extra: 'info', short: 'warn', over: 'danger' }[state] || 'ok';
};

const lineNumberOf = (variantRow, exceptIdx) => {
    const key = optionKey(variantRow);
    return form.items.findIndex((l, i) => i !== exceptIdx && l.pick === key) + 1;
};

// A line's default prices: the request's agreed figures when it has the
// product, else the catalogue's (cost to buy, price to sell).
const defaultPricesFor = (item) => {
    const line = orderLineFor(item);
    if (line) return { cost: num(line.unit_price), sale: line.sale_price != null && line.sale_price !== '' ? num(line.sale_price) : '' };
    const row = cache.rowOf(item.pick);
    if (!row) return { cost: '', sale: '' };
    return {
        cost: num(row.cost_price) > 0 ? num(row.cost_price) : num(row.price),
        sale: row.price != null && row.price !== '' ? num(row.price) : '',
    };
};

const countOf = (row) => num(form.items.find((l) => l.pick === optionKey(row))?.quantity);

/**
 * A product from the catalogue. One on the request comes in at the agreed
 * prices and, the first time, at the quantity still owed.
 */
const addRow = (row, delta = 1) => {
    cache.remember([row], { loadSizes: true });
    const key = optionKey(row);
    const idx = form.items.findIndex((l) => l.pick === key);
    if (idx !== -1) {
        const next = Math.floor(num(form.items[idx].quantity)) + delta;
        if (next <= 0) form.items.splice(idx, 1);
        else form.items[idx].quantity = next;
        return;
    }
    if (delta <= 0) return;
    const line = newLine({
        pick: key,
        product_id: row.id,
        product_variant_id: row.variant_id || null,
        variant: row.variant_id ? variantFieldsOf(row) : null,
        quantity: Math.floor(delta),
    });
    const prices = defaultPricesFor(line);
    line.unit_price = prices.cost;
    line.sale_price = prices.sale;
    const ordered = orderLineFor(line);
    if (ordered && delta === 1 && ordered.remaining_quantity > 0) line.quantity = ordered.remaining_quantity;
    form.items.push(line);
};

const setCount = (row, n) => {
    const line = form.items.find((l) => l.pick === optionKey(row));
    if (line && n >= 1) line.quantity = n;
};
const commitCount = (row, n) => {
    const idx = form.items.findIndex((l) => l.pick === optionKey(row));
    if (idx === -1) {
        if (n >= 1) addRow(row, n);
        return;
    }
    if (n < 1) form.items.splice(idx, 1);
    else form.items[idx].quantity = n;
};

const removeLine = (idx) => form.items.splice(idx, 1);

const clearLines = async () => {
    try {
        await ElMessageBox.confirm(t('pf_clear_lines_confirm'), t('pf_clear_lines'), {
            type: 'warning',
            confirmButtonText: t('pf_clear_lines'),
            cancelButtonText: t('cancel'),
        });
        form.items = [];
    } catch {
        // Kept them.
    }
};

// Another size for a line keeps its quantity and any price typed on it; a
// price still at the old size's default follows the new size. A size another
// line already holds joins the two.
const switchVariant = (idx, row) => {
    const item = form.items[idx];
    const key = optionKey(row);
    if (!item || item.pick === key) return;

    const other = form.items.findIndex((l, i) => i !== idx && l.pick === key);
    if (other !== -1) {
        form.items[other].quantity = num(form.items[other].quantity) + num(item.quantity);
        form.items.splice(idx, 1);
        ElMessage.info(t('po_merged_into_line', { n: other > idx ? other : other + 1 }));
        return;
    }

    const before = defaultPricesFor(item);
    const costUntouched = item.unit_price === '' || num(item.unit_price) === num(before.cost);
    const saleUntouched = item.sale_price === '' || num(item.sale_price) === num(before.sale);

    cache.remember([row]);
    item.pick = key;
    item.product_id = row.id;
    item.product_variant_id = row.variant_id || null;
    item.variant = variantFieldsOf(row);

    const after = defaultPricesFor(item);
    if (costUntouched) item.unit_price = after.cost;
    if (saleUntouched) item.sale_price = after.sale;
};

/* What blocks saving ------------------------------------------------ */

const blockers = computed(() => {
    const list = [];
    if (!form.supplier_id) list.push({ id: 'supplier', target: 'supplier', icon: 'fa-user', label: t('select_supplier') });
    if (!form.warehouse_id) list.push({ id: 'warehouse', target: 'head', icon: 'fa-warehouse', label: t('please_select_receiving_warehouse') });
    if (!form.items.length) list.push({ id: 'items', target: 'catalog', icon: 'fa-boxes-stacked', label: t('pf_add_one_item') });
    const noProduct = form.items.filter((l) => !l.product_id).length;
    if (noProduct) list.push({ id: 'noproduct', target: 'lines', icon: 'fa-ban', label: t('pf_lines_without_product', { n: noProduct }) });
    const noCost = form.items.filter((l) => l.product_id && (l.unit_price === '' || l.unit_price == null)).length;
    if (noCost) list.push({ id: 'cost', target: 'lines', icon: 'fa-coins', label: t('pf_lines_without_cost', { n: noCost }) });
    if (duplicatePicks.value.size) list.push({ id: 'dupes', target: 'lines', icon: 'fa-clone', label: t('pf_duplicate_lines') });
    if (discountTooLarge.value) list.push({ id: 'discount', target: 'totals', icon: 'fa-percent', label: t('rc_discount_exceeds_goods') });
    if (paidTooMuch.value) list.push({ id: 'paid', target: 'pay', icon: 'fa-money-bill-wave', label: t('pf_paid_exceeds') });
    return list;
});

const showChecklist = computed(() => attempted.value || form.items.length > 0);

const headRef = ref(null);
const linesRef = ref(null);
const totalsRef = ref(null);
const payRef = ref(null);
const supplierRef = ref(null);
const catalogRef = ref(null);

const focusCatalog = () => {
    if (isEdit.value) return;
    catalogFolded.value = false;
    nextTick(() => catalogRef.value?.focus());
};

const focusBlocker = (b) => {
    if (b.target === 'catalog') return focusCatalog();
    const el = { supplier: headRef, head: headRef, lines: linesRef, totals: totalsRef, pay: payRef }[b.target]?.value;
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (b.target === 'supplier') supplierRef.value?.focus?.();
};

/* Unsaved-changes guard ---------------------------------------------- */

let snapshot = '';
const snapshotOf = () => JSON.stringify({ ...form, items: form.items.map(({ key, ...rest }) => rest) });
const markClean = async () => {
    await nextTick();
    snapshot = snapshotOf();
};
const isDirty = () => snapshot !== '' && snapshotOf() !== snapshot;

const confirmClose = async (done) => {
    const close = () => (typeof done === 'function' ? done() : (visible.value = false));
    if (saving.value) return;
    if (!isDirty()) return close();
    try {
        await ElMessageBox.confirm(t('rc_unsaved_changes'), t('cancel'), {
            type: 'warning',
            confirmButtonText: t('po_discard_changes'),
            cancelButtonText: t('po_keep_editing'),
        });
        close();
    } catch {
        // Kept editing.
    }
};

/* Opening ----------------------------------------------------------- */

const reset = () => {
    Object.assign(form, {
        supplier_id: '',
        purchase_order_id: '',
        warehouse_id: defaultWarehouseId(),
        receipt_date: todayIso(),
        tax_amount: 0,
        discount: 0,
        discount_percent: '',
        discount_mode: 'percent',
        notes: '',
        paid_amount: 0,
        payment_method: 'cash',
        payment_reference: '',
        items: [],
    });
    supplierOrders.value = [];
    linkedOrder.value = null;
    orderLines.value = [];
    attempted.value = false;
    isEdit.value = false;
    editingId.value = null;
    receiptNumber.value = '';
    snapshot = '';
};

const openCreate = async () => {
    reset();
    visible.value = true;
    await markClean();
};

// From a request's "receive" button: opens already filled from it.
const openCreateForOrder = async (orderId) => {
    reset();
    visible.value = true;
    loading.value = true;
    try {
        const { data } = await purchaseOrdersApi.getById(orderId);
        const order = data.data;
        form.supplier_id = order.supplier_id;
        await loadSupplierOrders(order.supplier_id);
        form.purchase_order_id = order.id;
        await applyOrder(order.id, { askBeforeReplacing: false });
        // Filled from the request is the starting point, not unsaved work.
        await markClean();
    } catch {
        ElMessage.error(t('failed_to_load_purchase_order_data'));
    } finally {
        loading.value = false;
    }
};

const openEdit = async (id) => {
    reset();
    isEdit.value = true;
    editingId.value = id;
    visible.value = true;
    loading.value = true;
    try {
        const { data } = await purchaseReceiptsApi.getById(id);
        const receipt = data.data;
        receiptNumber.value = receipt.receipt_number || '';
        form.supplier_id = receipt.supplier_id;
        form.purchase_order_id = receipt.purchase_order_id || '';
        form.warehouse_id = receipt.warehouse_id;
        form.receipt_date = String(receipt.receipt_date || todayIso()).slice(0, 10);
        form.tax_amount = num(receipt.tax_amount);
        form.notes = receipt.notes || '';
        form.items = (receipt.items || []).map((item) => newLine({
            pick: pickKey(item.product_id, item.product_variant_id),
            product_id: item.product_id,
            product_variant_id: item.product_variant_id || null,
            variant: item.variant ? variantFieldsOf(item.variant) : null,
            product_name: item.product?.name_ar || item.description || '',
            quantity: num(item.quantity),
            unit_price: num(item.unit_price),
            sale_price: item.sale_price != null ? num(item.sale_price) : '',
        }));
        cache.remember(receipt.items);
        // Shown, not changed: the discount was posted with the lines.
        form.discount_mode = 'amount';
        form.discount = num(receipt.discount);
        form.discount_percent = receipt.discount_percent ?? '';
        if (receipt.purchase_order) linkedOrder.value = receipt.purchase_order;
        if (form.supplier_id) await loadSupplierOrders(form.supplier_id);
        await markClean();
    } catch {
        ElMessage.error(t('failed_to_load_receipt_for_edit'));
        visible.value = false;
    } finally {
        loading.value = false;
    }
};

/* New product / categories -------------------------------------------- */

const quickAddVisible = ref(false);
const quickAddSeed = ref('');
const supplierDialog = ref(false);
const categories = ref([]);

const categoryOptions = computed(() => {
    const name = (c) => (locale.value === 'en' && c.name_en ? c.name_en : (c.name_ar || c.name || ''));
    const roots = categories.value.filter((c) => !c.parent_id);
    return roots.flatMap((root) => [
        { id: root.id, parent_id: null, label: name(root) },
        ...categories.value.filter((c) => c.parent_id === root.id).map((c) => ({ id: c.id, parent_id: c.parent_id, label: name(c) })),
    ]);
});

const openQuickAddProduct = (seed = '') => {
    quickAddSeed.value = String(seed || '').trim();
    quickAddVisible.value = true;
};

const onQuickProduct = (prod, quantity = 1) => {
    const qty = Math.max(1, Math.floor(Number(quantity)) || 1);
    const had = countOf(prod);
    addRow(prod, qty);
    // A new product is never on the request; one picked as "existing" might
    // be, and then gets exactly what the dialog said.
    const line = form.items.find((l) => l.pick === optionKey(prod));
    if (line) line.quantity = had + qty;
};

/* Saving --------------------------------------------------------------- */

const apiError = (e, fallback) => {
    const errors = e?.response?.data?.errors;
    const first = errors && Object.values(errors).flat()[0];
    return first || e?.response?.data?.message || fallback;
};

const save = async () => {
    if (saving.value || loading.value) return;
    attempted.value = true;

    if (!isEdit.value) {
        if (blockers.value.length) {
            const first = blockers.value[0];
            ElMessage.warning(first.label);
            focusBlocker(first);
            return;
        }
        // A delivery that differs from its request is normal, but it should
        // be a decision rather than a slip.
        const off = linkedOrder.value ? form.items.filter((item) => lineVsOrder(item).state !== 'match').length : 0;
        const missing = linkedOrder.value ? missingOrderLines.value.length : 0;
        if (off + missing > 0) {
            try {
                await ElMessageBox.confirm(
                    t('rc_differs_from_order_message', { lines: off + missing }),
                    t('rc_differs_from_order'),
                    { type: 'warning', confirmButtonText: t('save_receipt'), cancelButtonText: t('rc_review_lines') },
                );
            } catch {
                linesRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
        }
    }

    saving.value = true;
    try {
        if (isEdit.value) {
            // Only the descriptive fields travel: the lines and the warehouse
            // are fixed once the goods are in and their cost is posted.
            await purchaseReceiptsApi.update(editingId.value, {
                purchase_order_id: form.purchase_order_id || null,
                receipt_date: form.receipt_date,
                notes: form.notes,
            });
            ElMessage.success(t('receipt_updated'));
        } else {
            const paid = mayPay.value ? round2(form.paid_amount) : 0;
            const { data } = await purchaseReceiptsApi.create({
                supplier_id: form.supplier_id,
                purchase_order_id: form.purchase_order_id || null,
                warehouse_id: form.warehouse_id,
                receipt_date: form.receipt_date || null,
                tax_amount: num(form.tax_amount),
                ...discount.payload(),
                notes: form.notes || null,
                paid_amount: paid > 0 ? paid : null,
                payment_method: paid > 0 ? form.payment_method : null,
                payment_reference: paid > 0 ? (form.payment_reference || null) : null,
                items: form.items.map((l) => ({
                    product_id: l.product_id,
                    product_variant_id: l.product_variant_id || null,
                    quantity: Math.max(1, Math.floor(num(l.quantity))),
                    unit_price: num(l.unit_price),
                    sale_price: l.sale_price === '' || l.sale_price == null ? null : num(l.sale_price),
                })),
            });
            ElMessage.success(t('receipt_saved_stock_and_entry'));
            snapshot = '';
            visible.value = false;
            emit('saved', { id: data?.data?.id, edited: false });
            return;
        }
        snapshot = '';
        visible.value = false;
        emit('saved', { id: editingId.value, edited: true });
    } catch (e) {
        // The API says precisely why a receipt cannot be saved.
        ElMessage.error(apiError(e, t('failed_to_save_receipt')));
    } finally {
        saving.value = false;
    }
};

const onOpened = () => {
    if (isEdit.value || loading.value) return;
    if (!form.supplier_id) supplierRef.value?.focus?.();
    else focusCatalog();
};

onMounted(() => {
    window.addEventListener('resize', onResize);
    if (!suppliersStore.suppliers.length) suppliersStore.fetchSuppliers().catch(() => {});
    if (!inventoryStore.warehouses.length) inventoryStore.fetchSummary().catch(() => {});
    categoriesApi.getAll({ per_page: 500 })
        .then((res) => { categories.value = res.data?.data || (Array.isArray(res.data) ? res.data : []); })
        .catch(() => {});
});

onBeforeUnmount(() => window.removeEventListener('resize', onResize));

defineExpose({ openCreate, openCreateForOrder, openEdit });
</script>
