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
                <span class="pf-title-icon"><i class="fas fa-file-signature"></i></span>
                <div>
                    <h2>
                        {{ isEdit ? $t('edit_purchase_order') : $t('create_purchase_order') }}
                        <span v-if="isEdit && orderNumber" class="pf-title-tag" dir="ltr">{{ orderNumber }}</span>
                        <span v-if="isEdit && editingStatus" class="pf-title-tag">{{ statusLabel(editingStatus) }}</span>
                    </h2>
                    <p>{{ isEdit ? $t('pf_po_edit_sub') : $t('po_new_order_hint') }}</p>
                </div>
            </div>
        </template>

        <div
            v-loading="loading"
            class="pf-shell"
            :class="{ 'is-single': locked }"
            @keydown.ctrl.enter.prevent="savePrimary"
            @keydown.meta.enter.prevent="savePrimary"
            @keydown.f2.exact.prevent="focusCatalog"
            @keydown.f4.exact.prevent="!locked && openQuickAddProduct('')"
        >
            <div ref="docRef" class="pf-doc">
                <el-alert
                    v-if="locked"
                    type="info"
                    :closable="false"
                    show-icon
                    :title="$t('po_edit_locked_hint', { status: statusLabel(editingStatus) })"
                />

                <!-- Who, for whom and when -->
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
                                    :disabled="locked"
                                >
                                    <el-option
                                        v-for="s in suppliersStore.suppliers"
                                        :key="s.id"
                                        :label="s.company ? `${s.name} — ${s.company}` : s.name"
                                        :value="s.id"
                                    />
                                </el-select>
                                <el-button v-if="!locked" plain :title="$t('add_new_supplier')" @click="supplierDialog = true">
                                    <i class="fas fa-user-plus"></i>
                                </el-button>
                            </div>
                            <small v-if="selectedSupplier && (selectedSupplier.phone || selectedSupplier.balance != null)" class="pf-hint">
                                <template v-if="selectedSupplier.phone"><i class="fas fa-phone"></i> <span dir="ltr">{{ selectedSupplier.phone }}</span></template>
                                <template v-if="selectedSupplier.balance != null && num(selectedSupplier.balance) !== 0">
                                    · {{ $t('pf_supplier_balance') }}: <strong>{{ money(selectedSupplier.balance) }}</strong>
                                </template>
                            </small>
                        </div>

                        <div class="pf-field">
                            <span class="pf-label">{{ $t('po_for_sale') }}</span>
                            <PurchaseSaleLink v-model="form.sale_link" @pick="fillFromSale" />
                        </div>

                        <div class="pf-field span-4">
                            <span class="pf-label">{{ $t('purchase_order_date') }}</span>
                            <el-date-picker
                                v-model="form.order_date"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                :clearable="false"
                                style="width: 100%"
                            />
                        </div>

                        <div class="pf-field span-8" :class="{ 'is-invalid': dueBeforeOrder }">
                            <span class="pf-label">{{ $t('due_date') }}</span>
                            <div class="pf-row">
                                <el-date-picker
                                    v-model="form.due_date"
                                    type="date"
                                    :placeholder="$t('due_date')"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    :disabled-date="isBeforeOrderDate"
                                    style="width: 170px; flex-shrink: 0"
                                />
                                <div class="pf-chips">
                                    <button
                                        v-for="d in DUE_PRESETS"
                                        :key="d"
                                        type="button"
                                        class="pf-chip-btn"
                                        :class="{ 'is-active': form.due_date && form.due_date === addDaysIso(form.order_date, d) }"
                                        @click="form.due_date = addDaysIso(form.order_date, d)"
                                    >{{ d === 0 ? $t('today') : $t('pf_plus_days', { n: d }) }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- What still stands between the form and a save -->
                <div v-if="!locked && showChecklist" class="pf-checklist" :class="{ 'is-ready': !blockers.length }">
                    <span class="pf-checklist-title">
                        <i class="fas" :class="blockers.length ? 'fa-list-check' : 'fa-circle-check'"></i>
                        {{ blockers.length ? $t('pf_before_saving') : $t('pf_ready_to_save') }}
                    </span>
                    <button v-for="b in blockers" :key="b.id" type="button" @click="focusBlocker(b)">
                        <i class="fas" :class="b.icon"></i> {{ b.label }}
                    </button>
                </div>

                <!-- Lines -->
                <section ref="linesRef" class="pf-card pf-lines-card">
                    <header class="pf-card-head">
                        <h3 class="pf-card-title">
                            <i class="fas fa-boxes-stacked"></i>
                            {{ $t('items_and_quantities') }}
                            <span class="pf-count">{{ form.items.length }}</span>
                        </h3>
                        <div v-if="!locked && form.items.length" class="pf-card-tools">
                            <el-button size="small" text type="danger" @click="clearLines">
                                <i class="fas fa-eraser"></i> {{ $t('pf_clear_lines') }}
                            </el-button>
                        </div>
                    </header>

                    <template v-if="form.items.length">
                        <div class="pf-lines-head" :class="{ 'is-locked': locked }">
                            <span>#</span>
                            <span>{{ $t('product') }}</span>
                            <span>{{ $t('quantity') }}</span>
                            <span>{{ $t('purchase_cost') }}</span>
                            <span>{{ $t('sale_price') }}</span>
                            <span>{{ $t('po_line_total') }}</span>
                            <span v-if="!locked"></span>
                        </div>
                        <div class="pf-lines">
                            <PurchaseLineRow
                                v-for="(item, idx) in form.items"
                                :key="item.key"
                                :item="item"
                                :index="idx"
                                :row="cache.rowOf(item.pick)"
                                :siblings="siblingsOf(item)"
                                :needs-size="needsSize(item)"
                                :locked="locked"
                                :currency="currencyCode"
                                :tone="lineTone(item)"
                                :taken-by="(v) => lineNumberOf(v, idx)"
                                @switch-variant="(v) => switchVariant(idx, v)"
                                @remove="removeLine(idx)"
                            >
                                <template #notes>
                                    <div v-if="!item.product_id" class="pf-line-note">
                                        <span class="pf-badge is-danger"><i class="fas fa-ban"></i> {{ $t('pf_product_missing') }}</span>
                                    </div>
                                    <div v-if="item.from_sale" class="pf-line-note">
                                        <span class="pf-badge" :class="saleSizeChanged(item) ? 'is-warn' : 'is-info'">
                                            <i class="fas" :class="saleSizeChanged(item) ? 'fa-right-left' : 'fa-link'"></i>
                                            {{ $t('po_line_from_sale', { number: form.sale_link?.number || '' }) }}
                                        </span>
                                        <span v-if="item.from_sale.label" dir="ltr">{{ item.from_sale.label }}</span>
                                        <span dir="ltr">×{{ item.from_sale.quantity }}</span>
                                    </div>
                                    <div v-if="duplicatePicks.has(item.pick)" class="pf-line-note">
                                        <span class="pf-badge is-danger"><i class="fas fa-clone"></i> {{ $t('pf_duplicate_line') }}</span>
                                    </div>
                                </template>
                            </PurchaseLineRow>
                        </div>
                    </template>

                    <div v-else class="pf-empty">
                        <span class="pf-empty-icon"><i class="fas fa-cart-flatbed"></i></span>
                        <strong>{{ $t('pf_no_lines_title') }}</strong>
                        <p>{{ $t('pf_no_lines_po') }}</p>
                        <div class="pf-row">
                            <el-button type="primary" @click="focusCatalog"><i class="fas fa-magnifying-glass"></i>&nbsp;{{ $t('pf_search_catalog') }}</el-button>
                            <el-button @click="openQuickAddProduct('')"><i class="fas fa-plus"></i>&nbsp;{{ $t('pqa_button') }}</el-button>
                        </div>
                    </div>

                    <footer v-if="form.items.length" class="pf-lines-foot">
                        <span>{{ $t('po_items_count', filledCount) }} · {{ $t('rc_units_n', { n: totalUnits }) }}</span>
                        <span>{{ $t('subtotal') }}: <strong>{{ money(subtotal) }}</strong></span>
                    </footer>
                </section>

                <!-- Notes and the money -->
                <div class="pf-bottom">
                    <section class="pf-card pf-card-pad">
                        <span class="pf-label" style="margin-bottom: 0.4rem"><i class="fas fa-note-sticky"></i> {{ $t('notes') }}</span>
                        <el-input
                            v-model="form.notes"
                            type="textarea"
                            :rows="5"
                            maxlength="1000"
                            show-word-limit
                            :placeholder="$t('purchase_order_notes_placeholder')"
                        />
                    </section>

                    <section ref="totalsRef" class="pf-card pf-card-pad pf-totals">
                        <div class="pf-sum-row">
                            <span>{{ $t('subtotal') }}</span>
                            <strong>{{ money(subtotal) }}</strong>
                        </div>

                        <PurchaseDiscountControl
                            v-if="!locked"
                            :form="form"
                            :ctl="discount"
                            :currency="currencyCode"
                            :invalid="discountTooLarge"
                            :invalid-text="$t('po_discount_exceeds_total')"
                        />
                        <div v-else-if="num(form.discount) > 0" class="pf-sum-row is-minus">
                            <span>{{ $t('discount') }}</span>
                            <span class="pf-num">− {{ money(form.discount) }}</span>
                        </div>

                        <div v-if="!locked" class="pf-field span-12">
                            <div class="pf-row" style="justify-content: space-between">
                                <span class="pf-label"><i class="fas fa-receipt"></i> {{ $t('tax') }}</span>
                                <button type="button" class="pf-chip-btn" :class="{ 'is-active': vatAuto }" @click="toggleVat">
                                    {{ $t('apply_vat_15') }}
                                </button>
                            </div>
                            <div class="pf-money-input">
                                <input
                                    v-model="form.tax"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="0.01"
                                    dir="ltr"
                                    placeholder="0.00"
                                    @input="vatAuto = false"
                                />
                                <span class="pf-unit">{{ currencyCode }}</span>
                            </div>
                        </div>
                        <div v-else-if="num(form.tax) > 0" class="pf-sum-row">
                            <span>{{ $t('tax') }}</span>
                            <span class="pf-num">+ {{ money(form.tax) }}</span>
                        </div>

                        <div class="pf-grand" :class="{ 'is-invalid': discountTooLarge }">
                            <span>{{ $t('grand_total_label') }}</span>
                            <strong>{{ money(total) }}</strong>
                        </div>
                    </section>
                </div>
            </div>

            <aside v-if="!locked" class="pf-side" :class="{ 'is-folded': catalogFolded }">
                <button type="button" class="pf-catalog-toggle" @click="catalogFolded = !catalogFolded">
                    <span><i class="fas fa-store"></i> {{ $t('pf_catalog') }}</span>
                    <i class="fas" :class="catalogFolded ? 'fa-chevron-down' : 'fa-chevron-up'"></i>
                </button>
                <PurchaseCatalogPanel
                    ref="catalogRef"
                    :count-of="countOf"
                    :categories="categoryOptions"
                    :add-label="$t('po_add_to_order')"
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
                    <div class="pf-footer-metric is-main" :class="{ 'is-invalid': discountTooLarge }">
                        <span>{{ $t('grand_total_label') }}</span>
                        <strong>{{ money(total) }}</strong>
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
                    <span class="pf-kbd-hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd><template v-if="!locked"> · <kbd>F2</kbd> {{ $t('search') }} · <kbd>F4</kbd> {{ $t('pqa_button') }}</template></span>
                    <el-button @click="confirmClose()">{{ $t('cancel') }}</el-button>
                    <template v-if="!isEdit">
                        <el-button :type="canApprove ? 'default' : 'primary'" :loading="saving" @click="save({ approve: false })">
                            <i class="fas fa-file-signature"></i>&nbsp;{{ $t('po_save_as_pending') }}
                        </el-button>
                        <el-button v-if="canApprove" type="primary" :loading="saving" @click="save({ approve: true })">
                            <i class="fas fa-circle-check"></i>&nbsp;{{ $t('po_save_and_approve') }}
                        </el-button>
                    </template>
                    <el-button v-else type="primary" :loading="saving" @click="save()">
                        <i class="fas fa-floppy-disk"></i>&nbsp;{{ $t('save_purchase_order') }}
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

    <SupplierQuickAddDialog v-model="supplierDialog" @created="(s) => (form.supplier_id = s.id)" />
</template>

<script setup>
/**
 * Creating and editing a purchase order.
 *
 * Laid out as a workspace: the order (supplier, dates, lines, money) on one
 * side and the catalogue beside it, so items are added without a dropdown
 * covering the lines they land in. A line exists only for a real product —
 * there are no blank rows to fill in or to trip the save over.
 *
 * Opened by the orders screen through the methods it exposes; tells it what
 * was saved through `saved`, and leaves the list and what to do next to it.
 */
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useAuthStore } from '@/stores/auth';
import { useSuppliersStore } from '@/stores/suppliers';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { salesOrdersApi } from '@/api/salesOrders';
import { invoicesApi } from '@/api/invoices';
import { categoriesApi } from '@/api/categories';
import { posApi } from '@/api/pos';
import { baseCurrencyCode, formatMoney } from '@/utils/currency';
import { normalizePurchaseOrderStatus } from '@/utils/purchaseOrderStatus';
import { optionKey, pickKey, variantLabelOf, variantFieldsOf } from '@/utils/productPick';
import { num, todayIso, addDaysIso, usePurchaseProductCache, useDocumentDiscount } from '@/composables/usePurchaseDocument';
import PurchaseSaleLink from '@/components/admin/purchases/PurchaseSaleLink.vue';
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

const canApprove = computed(() => Boolean(authStore.isSuperAdmin || authStore.user?.is_admin || authStore.hasRole(['admin', 'master'])));
const currencyCode = computed(() => baseCurrencyCode());
const money = (v) => formatMoney(v);
const DUE_PRESETS = [0, 3, 7, 14, 30];

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
const editingStatus = ref('');
const orderNumber = ref('');
const catalogFolded = ref(false);

// A received or cancelled order keeps its lines as booked: only its dates,
// notes and sale link can change, which is all the API takes for it.
const locked = computed(() => isEdit.value && ['completed', 'cancelled'].includes(normalizePurchaseOrderStatus(editingStatus.value)));

const statusLabel = (status) => ({
    pending: t('sales_status_pending'),
    confirmed: t('sales_status_confirmed'),
    processing: t('sales_status_processing'),
    completed: t('sales_status_completed'),
    cancelled: t('sales_status_cancelled'),
}[normalizePurchaseOrderStatus(status)] || status);

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
    order_date: '',
    due_date: '',
    discount: 0,
    discount_percent: '',
    discount_mode: 'percent',
    tax: 0,
    notes: '',
    // The sale this buys in for: { type: 'sales_order' | 'invoice', id, number, customer }.
    sale_link: null,
    items: [],
});

const cache = usePurchaseProductCache();

const subtotal = computed(() => form.items.reduce((sum, l) => sum + num(l.quantity) * num(l.unit_price), 0));
const discount = useDocumentDiscount(form, subtotal);
const total = computed(() => Math.max(0, subtotal.value + num(form.tax) - num(form.discount)));
// The API refuses a discount above the goods plus tax.
const discountTooLarge = computed(() => num(form.discount) > subtotal.value + num(form.tax) + 0.000001);
const filledCount = computed(() => form.items.filter((l) => l.product_id).length);
const totalUnits = computed(() => form.items.reduce((sum, l) => sum + num(l.quantity), 0));
const selectedSupplier = computed(() => suppliersStore.suppliers.find((s) => s.id === form.supplier_id) || null);

// The note the last sale fill wrote, so filling from another sale replaces it
// rather than stacking a second one, while a note someone typed is kept.
let autoNote = '';

/* 15% VAT, kept in step with the goods while it is on ----------------- */
const vatAuto = ref(false);
const vatFor = () => Math.round(Math.max(0, subtotal.value - num(form.discount)) * 0.15 * 100) / 100;
const toggleVat = () => {
    vatAuto.value = !vatAuto.value;
    form.tax = vatAuto.value ? vatFor() : 0;
};
watch([subtotal, () => form.discount], () => {
    if (vatAuto.value) form.tax = vatFor();
});

/* Lines ------------------------------------------------------------ */

const siblingsOf = (item) => (item.product_id ? cache.siblingsOf(item.product_id) : []);

// A line for a product that comes in sizes, with none chosen — what a sale
// sold before sizes were tracked leaves behind. Saving waits on it.
const needsSize = (item) => Boolean(item.product_id) && !item.product_variant_id && siblingsOf(item).length > 0;
const linesNeedingSize = computed(() => form.items.filter(needsSize));

// One line per product or size: a second line for the same one splits what
// the receipt later matches against.
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
    if (needsSize(item) || (attempted.value && (item.unit_price === '' || item.unit_price == null))) return 'warn';
    if (item.from_sale) return 'info';
    return 'ok';
};

const lineNumberOf = (variantRow, exceptIdx) => {
    const key = optionKey(variantRow);
    return form.items.findIndex((l, i) => i !== exceptIdx && l.pick === key) + 1;
};

const saleSizeChanged = (item) => Boolean(item.from_sale?.variant_id)
    && (item.product_variant_id || null) !== item.from_sale.variant_id;

// What a purchase line is priced at by default: the product's cost (what the
// supplier is paid), and the price it sells at.
const defaultCost = (row) => (num(row?.cost_price) > 0 ? num(row.cost_price) : num(row?.price));
const defaultSale = (row) => (row?.price != null && row.price !== '' ? num(row.price) : '');

const lineFromRow = (row, quantity = 1) => newLine({
    pick: optionKey(row),
    product_id: row.id,
    product_variant_id: row.variant_id || null,
    quantity,
    unit_price: defaultCost(row),
    sale_price: defaultSale(row),
});

const countOf = (row) => num(form.items.find((l) => l.pick === optionKey(row))?.quantity);

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
    if (delta > 0) form.items.push(lineFromRow(row, Math.floor(delta)));
};

// A count typed into a catalogue stepper: only a usable number lands while
// typing; leaving it at 0 removes the line.
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

/**
 * Another size for a line. Its quantity stays; a price still at the old
 * size's default follows the new size, a typed one is kept. A size another
 * line already holds joins the two lines.
 */
const switchVariant = (idx, row) => {
    const line = form.items[idx];
    const key = optionKey(row);
    if (!line || line.pick === key) return;

    const other = form.items.findIndex((l, i) => i !== idx && l.pick === key);
    if (other !== -1) {
        form.items[other].quantity = num(form.items[other].quantity) + num(line.quantity);
        form.items.splice(idx, 1);
        ElMessage.info(t('po_merged_into_line', { n: other > idx ? other : other + 1 }));
        return;
    }

    const previous = cache.rowOf(line.pick);
    // Prices on a line that had no size yet came from the sale, so choosing
    // its size fills only what is still empty.
    const hadSize = Boolean(line.product_variant_id);
    const costUntouched = line.unit_price === '' || (hadSize && (!previous || num(line.unit_price) === defaultCost(previous)));
    const saleUntouched = line.sale_price === '' || (hadSize && (!previous || num(line.sale_price) === num(defaultSale(previous))));

    cache.remember([row]);
    line.pick = key;
    line.product_id = row.id;
    line.product_variant_id = row.variant_id || null;
    line.variant = variantFieldsOf(row);
    line.sold_as = '';
    if (costUntouched) line.unit_price = defaultCost(row);
    if (saleUntouched) line.sale_price = defaultSale(row);
};

/* Dates ------------------------------------------------------------ */

const isBeforeOrderDate = (date) => {
    if (!form.order_date) return false;
    const [y, m, d] = form.order_date.split('-').map(Number);
    return date < new Date(y, m - 1, d);
};
const dueBeforeOrder = computed(() => Boolean(form.due_date && form.order_date && form.due_date < form.order_date));

/* What blocks saving ------------------------------------------------ */

const blockers = computed(() => {
    const list = [];
    if (!form.supplier_id) list.push({ id: 'supplier', target: 'supplier', icon: 'fa-user', label: t('select_supplier') });
    if (!form.items.length) list.push({ id: 'items', target: 'catalog', icon: 'fa-boxes-stacked', label: t('pf_add_one_item') });
    const noProduct = form.items.filter((l) => !l.product_id).length;
    if (noProduct) list.push({ id: 'noproduct', target: 'lines', icon: 'fa-ban', label: t('pf_lines_without_product', { n: noProduct }) });
    const noCost = form.items.filter((l) => l.product_id && (l.unit_price === '' || l.unit_price == null)).length;
    if (noCost) list.push({ id: 'cost', target: 'lines', icon: 'fa-coins', label: t('pf_lines_without_cost', { n: noCost }) });
    if (linesNeedingSize.value.length) list.push({ id: 'sizes', target: 'lines', icon: 'fa-tag', label: t('pf_lines_need_size', { n: linesNeedingSize.value.length }) });
    if (duplicatePicks.value.size) list.push({ id: 'dupes', target: 'lines', icon: 'fa-clone', label: t('pf_duplicate_lines') });
    if (dueBeforeOrder.value) list.push({ id: 'due', target: 'head', icon: 'fa-calendar-xmark', label: t('pf_due_before_order') });
    if (discountTooLarge.value) list.push({ id: 'discount', target: 'totals', icon: 'fa-percent', label: t('po_discount_exceeds_total') });
    return list;
});

const showChecklist = computed(() => attempted.value || Boolean(form.supplier_id) || form.items.length > 0);

const docRef = ref(null);
const headRef = ref(null);
const linesRef = ref(null);
const totalsRef = ref(null);
const supplierRef = ref(null);
const catalogRef = ref(null);

const focusCatalog = () => {
    if (locked.value) return;
    catalogFolded.value = false;
    nextTick(() => catalogRef.value?.focus());
};

const focusBlocker = (b) => {
    if (b.target === 'catalog') return focusCatalog();
    const el = { supplier: headRef, head: headRef, lines: linesRef, totals: totalsRef }[b.target]?.value;
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (b.target === 'supplier') supplierRef.value?.focus?.();
};

/* Unsaved-changes guard ---------------------------------------------- */

let snapshot = '';
const snapshotOf = () => JSON.stringify({ ...form, items: form.items.map(({ key, ...rest }) => rest) });
const markClean = async () => {
    // Let the discount and tax follow the loaded lines before calling it clean.
    await nextTick();
    snapshot = snapshotOf();
};
const isDirty = () => snapshot !== '' && snapshotOf() !== snapshot;

const confirmClose = async (done) => {
    const close = () => (typeof done === 'function' ? done() : (visible.value = false));
    if (saving.value) return;
    if (!isDirty()) return close();
    try {
        await ElMessageBox.confirm(t('po_unsaved_changes'), t('cancel'), {
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
        order_date: todayIso(),
        due_date: '',
        discount: 0,
        discount_percent: '',
        discount_mode: 'percent',
        tax: 0,
        notes: '',
        sale_link: null,
        items: [],
    });
    autoNote = '';
    vatAuto.value = false;
    attempted.value = false;
    isEdit.value = false;
    editingId.value = null;
    editingStatus.value = '';
    orderNumber.value = '';
    snapshot = '';
};

const show = async () => {
    visible.value = true;
    await markClean();
};

// A saved line as a form line: its product, size and agreed prices.
const lineFromSaved = (item, overrides = {}) => newLine({
    pick: pickKey(item.product_id, item.product_variant_id),
    product_id: item.product_id || '',
    product_variant_id: item.product_variant_id || null,
    variant: item.variant ? variantFieldsOf(item.variant) : null,
    product_name: item.product?.name_ar || item.product_name || item.name || '',
    quantity: Math.max(1, Math.floor(num(item.quantity)) || 1),
    unit_price: item.unit_price === '' || item.unit_price == null ? '' : num(item.unit_price),
    sale_price: item.sale_price != null && item.sale_price !== '' ? num(item.sale_price) : '',
    ...(item.notes ? { notes: item.notes } : {}),
    ...overrides,
});

const sumOf = (items) => (items || []).reduce((s, i) => s + num(i.quantity) * num(i.unit_price), 0);

const loadVat = () => {
    const chargeable = Math.max(0, subtotal.value - num(form.discount));
    vatAuto.value = num(form.tax) > 0 && Math.abs(num(form.tax) - Math.round(chargeable * 0.15 * 100) / 100) < 0.05;
};

/** A new, empty order — optionally for a supplier already chosen. */
const openCreate = async ({ supplier_id: supplierId = null } = {}) => {
    reset();
    if (supplierId) form.supplier_id = Number(supplierId);
    await show();
};

/** An existing order, for editing. */
const openEdit = async (id) => {
    reset();
    isEdit.value = true;
    editingId.value = id;
    visible.value = true;
    loading.value = true;
    try {
        const { data } = await purchaseOrdersApi.getById(id);
        const order = data.data;
        editingStatus.value = order.status;
        orderNumber.value = order.order_number || '';
        form.supplier_id = order.supplier_id;
        form.order_date = String(order.order_date || order.created_at || todayIso()).slice(0, 10);
        form.due_date = order.due_date ? String(order.due_date).slice(0, 10) : '';
        form.tax = num(order.tax);
        form.notes = order.notes || '';
        form.sale_link = saleLinkOf(order);
        form.items = (order.items || []).map((item) => lineFromSaved(item));
        cache.remember(order.items, { loadSizes: true });
        discount.load(order, order.subtotal ?? sumOf(order.items));
        loadVat();
        await markClean();
    } catch {
        ElMessage.error(t('failed_to_load_order_for_edit'));
        visible.value = false;
    } finally {
        loading.value = false;
    }
};

/**
 * A new order with the supplier and lines of an existing one — restocking is
 * mostly the same order placed again. Dated today, pending, noted.
 */
const openDuplicate = async (id) => {
    try {
        const { data } = await purchaseOrdersApi.getById(id);
        const order = data.data;
        reset();
        form.supplier_id = order.supplier_id;
        form.tax = num(order.tax);
        form.notes = t('po_duplicated_from', { number: order.order_number });
        // A line whose product left the catalogue cannot be ordered again.
        const lines = (order.items || []).filter((item) => item.product_id);
        form.items = lines.map((item) => lineFromSaved(item));
        cache.remember(lines, { loadSizes: true });
        discount.load(order, sumOf(lines));
        loadVat();
        await show();
    } catch (e) {
        ElMessage.error(apiError(e, t('failed_to_load_order_details_msg')));
    }
};

/**
 * What a sales order is short of, as a new order. The quantities are fetched
 * now rather than passed along, so what lands is what is short now.
 */
const openFromShortage = async (salesOrderId) => {
    try {
        const { data } = await salesOrdersApi.shortages(salesOrderId);
        const shortages = data?.data?.shortages ?? [];
        const order = data?.data?.sales_order || {};
        const number = order.order_number ?? salesOrderId;
        if (!shortages.length) {
            ElMessage.info(t('sales.shortage_none_left'));
            return;
        }
        reset();
        form.items = shortages.map((row) => lineFromSaved({ ...row, quantity: row.suggested_quantity, product_name: row.name }));
        cache.remember(shortages.map((row) => ({ ...row, product_name: row.name })), { loadSizes: true });
        // The customer's delivery date is when the goods are needed by, if it
        // has not already passed.
        if (order.expected_delivery && order.expected_delivery >= form.order_date) form.due_date = order.expected_delivery;
        form.notes = [
            order.customer_name
                ? t('po_from_sales_order_note', { number, customer: order.customer_name })
                : t('sales.prefilled_from_order', { order: number }),
            order.notes,
        ].filter(Boolean).join('\n').slice(0, 1000);
        autoNote = form.notes;
        form.sale_link = { type: 'sales_order', id: Number(order.id ?? salesOrderId), number, customer: order.customer_name || '' };
        await show();
        ElMessage.success(t('sales.prefilled_from_order', { order: number }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('sales.shortage_prefill_failed'));
    }
};

/** Every line of a sales order, as a new order for the buyer to place. */
const openFromSalesOrder = async (salesOrderId) => {
    try {
        const draft = await fetchSaleDraft({ type: 'sales_order', id: salesOrderId });
        if (!draft.lines.length) {
            ElMessage.info(t('po_from_sales_order_empty'));
            return;
        }
        reset();
        applySaleDraft(draft);
        form.sale_link = draft.link;
        await show();
        const unsized = draft.lines.filter((line) => line.needs_variant).length;
        if (unsized) ElMessage.warning(t('po_from_sales_order_ready_sizes', { number: draft.link.number, count: unsized }, unsized));
        else ElMessage.success(t('po_from_sales_order_ready', { number: draft.link.number }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('po_from_sales_order_failed'));
    }
};

/**
 * A new order holding one product (and size), from a link elsewhere in the
 * admin: ?add_product_id, add_variant_id, add_qty, add_price, supplier_id, notes.
 */
const openWithProduct = async (query) => {
    reset();
    if (query.supplier_id) form.supplier_id = Number(query.supplier_id);
    if (query.notes) form.notes = String(query.notes).slice(0, 1000);
    const productId = Number(query.add_product_id);
    const variantId = query.add_variant_id ? Number(query.add_variant_id) : null;
    const qty = Math.max(1, Math.floor(Number(query.add_qty)) || 1);
    const price = query.add_price != null && query.add_price !== '' ? Number(query.add_price) : null;

    let target = null;
    try {
        const res = await posApi.productLookup({ q: query.sku || query.name || String(productId), expand_variants: 1 });
        const list = res.data?.data || [];
        target = list.find((p) => p.id === productId && (variantId ? p.variant_id === variantId : !p.variant_id))
            || list.find((p) => p.id === productId)
            || null;
    } catch {
        // Opened without the line; the operator adds it from the catalogue.
    }
    if (target) {
        cache.remember([target], { loadSizes: true });
        const line = lineFromRow(target, qty);
        if (price != null && Number.isFinite(price) && price >= 0) line.unit_price = price;
        form.items = [line];
    } else if (productId) {
        ElMessage.warning(t('pf_product_not_found'));
    }
    await show();
};

/* The sale this order buys in for ------------------------------------ */

const saleLinkOf = (order) => {
    if (order?.sales_order) {
        return { type: 'sales_order', id: order.sales_order.id, number: order.sales_order.order_number, customer: order.sales_order.customer?.name || '' };
    }
    if (order?.invoice) {
        return { type: 'invoice', id: order.invoice.id, number: order.invoice.invoice_number, customer: order.invoice.customer?.name || '' };
    }
    return null;
};

// Both are always sent, so linking one sale, or none, clears the other.
const saleLinkPayload = () => ({
    sales_order_id: form.sale_link?.type === 'sales_order' ? form.sale_link.id : null,
    invoice_id: form.sale_link?.type === 'invoice' ? form.sale_link.id : null,
});

const fetchSaleDraft = async (link) => {
    if (link.type === 'invoice') {
        const { data } = await invoicesApi.purchaseDraft(link.id);
        const invoice = data?.data?.invoice || {};
        return {
            link: { type: 'invoice', id: invoice.id ?? link.id, number: invoice.invoice_number, customer: invoice.customer_name || '' },
            lines: data?.data?.items || [],
            note: t('po_from_invoice_note', { number: invoice.invoice_number, customer: invoice.customer_name || '—' }),
            notes: invoice.notes,
            expected: null,
        };
    }
    const { data } = await salesOrdersApi.purchaseDraft(link.id);
    const order = data?.data?.sales_order || {};
    return {
        link: { type: 'sales_order', id: order.id ?? link.id, number: order.order_number, customer: order.customer_name || '' },
        lines: data?.data?.items || [],
        note: t('po_from_sales_order_note', { number: order.order_number, customer: order.customer_name || '—' }),
        notes: order.notes,
        expected: order.expected_delivery,
    };
};

const applySaleDraft = ({ lines, note, notes, expected }) => {
    form.items = lines.map((line) => lineFromSaved(line, {
        sold_as: line.needs_variant ? (line.sold_as || '') : '',
        from_sale: {
            variant_id: line.product_variant_id || null,
            label: line.variant ? variantLabelOf(line.variant) : (line.sold_as || ''),
            quantity: num(line.quantity),
        },
    }));
    cache.remember(lines, { loadSizes: true });
    if (expected && expected >= form.order_date) form.due_date = expected;
    if (!form.notes.trim() || form.notes === autoNote) {
        form.notes = [note, notes].filter(Boolean).join('\n').slice(0, 1000);
        autoNote = form.notes;
    }
};

/**
 * A sale picked in the form: linked by the picker already, and its lines
 * filled in — after asking, when the form has lines of its own. Keeping them
 * keeps the link alone.
 */
const fillFromSale = async (link) => {
    if (locked.value || !link) return;
    if (form.items.length) {
        try {
            await ElMessageBox.confirm(t('po_sale_replace_lines', { number: link.number }), t('po_sale_replace_title'), {
                type: 'info',
                confirmButtonText: t('po_sale_replace'),
                cancelButtonText: t('po_sale_keep_lines'),
            });
        } catch {
            return;
        }
    }
    loading.value = true;
    try {
        const draft = await fetchSaleDraft(link);
        if (!draft.lines.length) {
            ElMessage.info(t('po_sale_empty', { number: link.number }));
            return;
        }
        applySaleDraft(draft);
        ElMessage.success(t('po_sale_filled', { number: link.number }));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('po_sale_fill_failed'));
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

// A product the dialog created, or an existing one it pointed to instead.
const onQuickProduct = (prod, quantity = 1) => {
    addRow(prod, Math.max(1, Math.floor(Number(quantity)) || 1));
};

/* Saving --------------------------------------------------------------- */

const apiError = (e, fallback) => {
    const errors = e?.response?.data?.errors;
    const first = errors && Object.values(errors).flat()[0];
    return first || e?.response?.data?.message || fallback;
};

const payload = () => {
    const base = {
        order_date: form.order_date || null,
        due_date: form.due_date || null,
        notes: form.notes || null,
        ...saleLinkPayload(),
    };
    if (locked.value) return base;
    const { discount: amount, discount_percent: percent } = discount.payload();
    return {
        ...base,
        supplier_id: form.supplier_id,
        discount: amount,
        discount_percent: percent,
        tax: num(form.tax),
        items: form.items.map((l) => ({
            product_id: l.product_id,
            product_variant_id: l.product_variant_id || null,
            quantity: Math.max(1, Math.floor(num(l.quantity))),
            unit_price: num(l.unit_price),
            sale_price: l.sale_price === '' || l.sale_price == null ? null : num(l.sale_price),
            ...(l.notes ? { notes: l.notes } : {}),
        })),
    };
};

const save = async ({ approve = false } = {}) => {
    if (saving.value || loading.value) return;
    attempted.value = true;
    if (!canApprove.value) approve = false;
    if (!locked.value && blockers.value.length) {
        const first = blockers.value[0];
        ElMessage.warning(first.label);
        focusBlocker(first);
        return;
    }

    saving.value = true;
    try {
        if (isEdit.value) {
            await purchaseOrdersApi.update(editingId.value, payload());
            snapshot = '';
            visible.value = false;
            ElMessage.success(t('purchase_order_updated'));
            emit('saved', { id: editingId.value, edited: true, approved: false });
        } else {
            const { data } = await purchaseOrdersApi.create({ ...payload(), status: approve ? 'confirmed' : 'pending' });
            snapshot = '';
            visible.value = false;
            ElMessage.success(t(approve ? 'purchase_order_approved' : 'purchase_order_saved'));
            emit('saved', { id: data?.data?.id, edited: false, approved: approve });
        }
    } catch (e) {
        ElMessage.error(apiError(e, t('failed_to_save_purchase_order')));
    } finally {
        saving.value = false;
    }
};

// Ctrl+Enter: approve on create for those who may, else save.
const savePrimary = () => (isEdit.value ? save() : save({ approve: canApprove.value }));

const onOpened = () => {
    if (locked.value || loading.value) return;
    if (!form.supplier_id) supplierRef.value?.focus?.();
    else focusCatalog();
};

onMounted(() => {
    window.addEventListener('resize', onResize);
    if (!suppliersStore.suppliers.length) suppliersStore.fetchSuppliers().catch(() => {});
    categoriesApi.getAll({ per_page: 500 })
        .then((res) => { categories.value = res.data?.data || (Array.isArray(res.data) ? res.data : []); })
        .catch(() => {});
});

onBeforeUnmount(() => window.removeEventListener('resize', onResize));

defineExpose({ openCreate, openEdit, openDuplicate, openFromShortage, openFromSalesOrder, openWithProduct });
</script>
