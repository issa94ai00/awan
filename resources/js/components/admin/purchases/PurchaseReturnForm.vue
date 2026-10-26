<template>
    <el-drawer
        :model-value="modelValue"
        :title="$t('record_purchase_return')"
        :size="drawerSize"
        direction="rtl"
        class="return-form-drawer"
        :before-close="requestClose"
        @update:model-value="(value) => !value && requestClose()"
        @open="reset"
    >
        <!-- Says what this document is for, because the alternative people
             reach for — a stock adjustment — books the goods out as
             shrinkage and leaves the supplier owed in full. -->
        <el-alert
            type="info"
            show-icon
            :closable="false"
            class="form-alert"
            :title="$t('purchase_return_is_not_shrinkage')"
        />

        <el-form label-position="top" class="return-form" @submit.prevent>
            <!-- 1. Who the goods go back to, and from where -->
            <section class="form-section">
                <h4 class="section-title"><span class="step">1</span>{{ $t('pret_section_source') }}</h4>

                <div class="field-grid">
                    <el-form-item :label="$t('supplier')" required>
                        <el-select
                            v-model="form.supplier_id"
                            filterable
                            :placeholder="$t('pret_choose_supplier')"
                            style="width: 100%"
                            @change="onSupplierChange"
                        >
                            <el-option
                                v-for="supplier in suppliers"
                                :key="supplier.id"
                                :label="supplier.name"
                                :value="supplier.id"
                            />
                        </el-select>
                        <span v-if="selectedSupplier" class="field-hint">
                            {{ $t('pret_supplier_owed', { amount: formatCurrency(selectedSupplier.balance, selectedSupplier.currency) }) }}
                        </span>
                    </el-form-item>

                    <el-form-item :label="$t('pret_from_delivery')">
                        <el-select
                            v-model="form.purchase_receipt_id"
                            clearable
                            filterable
                            :disabled="!form.supplier_id"
                            :loading="receiptsLoading"
                            :placeholder="form.supplier_id ? $t('pret_any_delivery') : $t('pret_choose_supplier_first')"
                            style="width: 100%"
                            @change="onReceiptChange"
                        >
                            <el-option
                                v-for="receipt in receipts"
                                :key="receipt.id"
                                :label="receiptLabel(receipt)"
                                :value="receipt.id"
                            />
                        </el-select>
                        <span class="field-hint">{{ $t('pret_from_delivery_hint') }}</span>
                    </el-form-item>

                    <el-form-item :label="$t('warehouse')" required>
                        <el-select
                            v-model="form.warehouse_id"
                            :placeholder="$t('pret_choose_warehouse')"
                            style="width: 100%"
                            @change="refreshAvailability"
                        >
                            <el-option
                                v-for="warehouse in warehouses"
                                :key="warehouse.id"
                                :label="warehouse.name"
                                :value="warehouse.id"
                            />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="$t('return_date')">
                        <el-date-picker
                            v-model="form.return_date"
                            type="date"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            :clearable="false"
                            :disabled-date="isFuture"
                            style="width: 100%"
                        />
                    </el-form-item>
                </div>

                <el-form-item :label="$t('reason')" class="reason-item">
                    <el-input v-model="form.reason" maxlength="255" :placeholder="$t('return_reason_example')" />
                    <div class="reason-chips">
                        <button
                            v-for="key in REASON_KEYS"
                            :key="key"
                            type="button"
                            class="reason-chip"
                            :class="{ 'is-on': form.reason === $t(key) }"
                            @click="form.reason = $t(key)"
                        >
                            {{ $t(key) }}
                        </button>
                    </div>
                </el-form-item>
            </section>

            <!-- 2. What goes back -->
            <section class="form-section">
                <div class="section-head">
                    <h4 class="section-title"><span class="step">2</span>{{ $t('returned_items') }}</h4>
                    <el-button size="small" type="primary" plain :icon="Plus" @click="addLine()">
                        {{ $t('add_item') }}
                    </el-button>
                </div>

                <div class="lines">
                    <div class="line line--head">
                        <span>{{ $t('product') }}</span>
                        <span>{{ $t('quantity') }}</span>
                        <span>{{ $t('credit_per_unit') }}</span>
                        <span class="num">{{ $t('pret_line_credit') }}</span>
                        <span />
                    </div>

                    <div v-for="(line, index) in form.items" :key="line.key" class="line">
                        <div class="line-product">
                            <!-- One option per size: a return says which size went
                                 back, as the receipt line it reverses did. -->
                            <el-select
                                v-model="line.pick"
                                filterable
                                remote
                                reserve-keyword
                                :remote-method="searchProducts"
                                :loading="productSearchLoading"
                                :placeholder="$t('pret_search_product')"
                                style="width: 100%"
                                @change="(key) => onProductChange(line, key)"
                            >
                                <el-option
                                    v-for="product in productOptions(line)"
                                    :key="optionKey(product)"
                                    :label="productLabel(product)"
                                    :value="optionKey(product)"
                                    :disabled="isTaken(optionKey(product), line)"
                                >
                                    <div class="product-option">
                                        <span class="product-option-name">
                                            {{ baseName(product) }}
                                            <VariantChip v-if="product.variant_id" :label="product.variant_label" />
                                        </span>
                                        <small v-if="product.sku" dir="ltr">{{ product.sku }}</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <!-- The other sizes of the picked product, one click away;
                                 sizes on the chosen delivery are marked. -->
                            <div v-if="siblingVariants(line).length > 1" class="line-sizes">
                                <span class="sizes-label"><i class="fas fa-tag"></i> {{ $t('po_switch_size') }}</span>
                                <button
                                    v-for="v in siblingVariants(line)"
                                    :key="optionKey(v)"
                                    type="button"
                                    class="size-chip"
                                    :class="{ 'is-active': optionKey(v) === line.pick, 'is-received': receiptGroups.has(optionKey(v)) }"
                                    :disabled="isTaken(optionKey(v), line)"
                                    :title="v.sku || ''"
                                    @click="switchLineVariant(line, v)"
                                >
                                    <span dir="ltr">{{ v.variant_label || v.sku }}</span>
                                    <small v-if="receiptGroups.has(optionKey(v))">{{ $t('pret_on_receipt') }}</small>
                                </button>
                            </div>
                            <div v-if="line.product_id" class="line-meta">
                                <VariantChip v-if="line.product_variant_id" :label="line.variant_label" />
                                <span v-if="line.received !== null">
                                    {{ $t('pret_received_n', { count: line.received }) }}
                                    <template v-if="line.alreadyReturned">
                                        · {{ $t('pret_already_returned_n', { count: line.alreadyReturned }) }}
                                    </template>
                                </span>
                                <span v-if="line.available === 'loading'" class="muted">
                                    <el-icon class="is-loading"><Loading /></el-icon>
                                </span>
                                <span
                                    v-else-if="line.available !== null && form.warehouse_id"
                                    :class="{ 'is-warn': line.quantity > line.available }"
                                >
                                    {{ $t('pret_available_n', { count: line.available }) }}
                                </span>
                            </div>
                            <span v-if="lineWarning(line)" class="line-warning">
                                <el-icon><WarningFilled /></el-icon>{{ lineWarning(line) }}
                            </span>
                        </div>

                        <el-input-number
                            v-model="line.quantity"
                            :min="form.purchase_receipt_id ? 0 : 1"
                            :max="line.returnable ?? undefined"
                            controls-position="right"
                            class="line-qty"
                        />

                        <el-input-number
                            v-model="line.unit_price"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            :placeholder="line.cost !== null ? $t('pret_at_cost', { amount: formatAmount(line.cost) }) : $t('pret_at_cost_short')"
                            class="line-price"
                            :value-on-clear="null"
                        />

                        <span class="line-total num">
                            <template v-if="lineCredit(line) !== null">
                                <span v-if="line.unit_price === null" class="approx" :title="$t('pret_estimate_hint')">≈</span>{{ formatAmount(lineCredit(line)) }}
                            </template>
                            <template v-else>—</template>
                        </span>

                        <el-button
                            circle
                            text
                            type="danger"
                            :icon="Delete"
                            :disabled="form.items.length <= 1"
                            :aria-label="$t('delete')"
                            @click="removeLine(index)"
                        />
                    </div>
                </div>

                <!-- The price is what the supplier credits, which is not
                     always what the goods cost us; the gap is a real result
                     and the server books it as one. -->
                <small class="hint">{{ $t('credit_per_unit_hint') }}</small>
            </section>

            <!-- 3. Tax and notes -->
            <section class="form-section">
                <h4 class="section-title"><span class="step">3</span>{{ $t('pret_section_extra') }}</h4>
                <div class="field-grid">
                    <el-form-item :label="$t('tax_returned')">
                        <el-input-number
                            v-model="form.tax_amount"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            style="width: 100%"
                        />
                        <span v-if="receiptTaxShare !== null" class="field-hint">
                            {{ $t('pret_tax_share_hint', { amount: formatAmount(receiptTaxShare) }) }}
                            <el-button link type="primary" size="small" @click="form.tax_amount = receiptTaxShare">
                                {{ $t('pret_use_it') }}
                            </el-button>
                        </span>
                    </el-form-item>
                    <el-form-item :label="$t('notes')">
                        <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" show-word-limit />
                    </el-form-item>
                </div>
            </section>
        </el-form>

        <template #footer>
            <div class="form-footer">
                <dl class="totals">
                    <div>
                        <dt>{{ $t('pret_goods_credit') }}</dt>
                        <dd><span v-if="hasEstimate" class="approx">≈</span>{{ formatAmount(creditTotal) }}</dd>
                    </div>
                    <div>
                        <dt>{{ $t('tax_returned') }}</dt>
                        <dd>{{ formatAmount(form.tax_amount) }}</dd>
                    </div>
                    <div class="grand">
                        <dt>{{ $t('pret_supplier_owed_less') }}</dt>
                        <dd><span v-if="hasEstimate" class="approx">≈</span>{{ formatCurrency(creditTotal + (Number(form.tax_amount) || 0)) }}</dd>
                    </div>
                </dl>
                <div class="footer-actions">
                    <span v-if="blocker" class="blocker">{{ blocker }}</span>
                    <el-button @click="requestClose()">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="saving" :disabled="!!blocker" @click="submit">
                        {{ $t('pret_record_it') }}
                    </el-button>
                </div>
            </div>
        </template>
    </el-drawer>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, Loading, Plus, WarningFilled } from '@element-plus/icons-vue';
import { purchaseReturnsApi } from '@/api/purchaseReturns';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { productsApi } from '@/api/products';
import { inventoryApi } from '@/api/inventory';
import { apiErrorMessage, formatCurrency, localIsoDate } from '@/utils/sales';
import { baseCurrencyCode, currencyDecimals } from '@/utils/currency';
import { formatDecimal, moneyDecimals } from '@/utils/numberFormat';
import { optionKey, pickKey, baseName, variantLabelOf, optionFromLine } from '@/utils/productPick';
import { useVariantSiblings } from '@/composables/useVariantSiblings';
import VariantChip from '@/components/admin/products/VariantChip.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    suppliers: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    /** Opens with this supplier already chosen (e.g. from a list filter). */
    presetSupplierId: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);
const { t } = useI18n();

const REASON_KEYS = ['pret_reason_faulty', 'pret_reason_wrong_item', 'pret_reason_damaged', 'pret_reason_excess', 'pret_reason_expired'];

const today = () => localIsoDate();
let lineSeq = 0;

const newLine = (patch = {}) => ({
    key: ++lineSeq,
    // What the select binds to: the product, or one size of it.
    pick: null,
    product_id: null,
    product_variant_id: null,
    variant_label: '',
    // The search row the line was picked from, so it stays among the options.
    option: null,
    product: null,
    quantity: 1,
    unit_price: null,
    cost: null,
    available: null,
    received: null,
    alreadyReturned: 0,
    returnable: null,
    ...patch,
});

const form = reactive({
    supplier_id: null,
    purchase_receipt_id: null,
    warehouse_id: null,
    return_date: today(),
    tax_amount: 0,
    reason: '',
    notes: '',
    items: [newLine()],
});

const saving = ref(false);
let snapshot = '';
const serialize = () => JSON.stringify({ ...form, items: form.items.map(({ product_id, product_variant_id, quantity, unit_price }) => ({ product_id, product_variant_id, quantity, unit_price })) });

const drawerSize = computed(() => (window.innerWidth < 768 ? '100%' : window.innerWidth < 1280 ? '75%' : '62%'));

const defaultWarehouseId = () => {
    const list = props.warehouses;
    return (list.find((w) => w.is_primary) || (list.length === 1 ? list[0] : null))?.id ?? null;
};

const reset = () => {
    Object.assign(form, {
        supplier_id: props.presetSupplierId,
        purchase_receipt_id: null,
        warehouse_id: defaultWarehouseId(),
        return_date: today(),
        tax_amount: 0,
        reason: '',
        notes: '',
        items: [newLine()],
    });
    receipts.value = [];
    selectedReceipt.value = null;
    if (form.supplier_id) loadReceipts();
    snapshot = serialize();
};

const requestClose = async () => {
    if (saving.value) return;
    if (serialize() !== snapshot) {
        try {
            await ElMessageBox.confirm(t('pret_discard_confirm'), t('confirm'), {
                confirmButtonText: t('prod_admin_discard'),
                cancelButtonText: t('prod_admin_keep_editing'),
                type: 'warning',
            });
        } catch {
            return;
        }
    }
    emit('update:modelValue', false);
};

// ── Supplier and delivery ─────────────────────────────────────────────────
const selectedSupplier = computed(() => props.suppliers.find((s) => s.id === form.supplier_id) || null);

const receipts = ref([]);
const receiptsLoading = ref(false);
const selectedReceipt = ref(null);

const loadReceipts = async () => {
    receiptsLoading.value = true;
    try {
        const res = await purchaseReceiptsApi.getAll({ supplier_id: form.supplier_id });
        receipts.value = res.data?.data?.receipts || [];
    } catch {
        receipts.value = [];
    } finally {
        receiptsLoading.value = false;
    }
};

const receiptLabel = (receipt) => [
    receipt.receipt_number,
    String(receipt.receipt_date || '').slice(0, 10),
    formatCurrency(receipt.total_amount, receipt.currency),
].filter(Boolean).join(' · ');

const onSupplierChange = () => {
    form.purchase_receipt_id = null;
    selectedReceipt.value = null;
    receipts.value = [];
    if (form.supplier_id) loadReceipts();
};

/**
 * Picking the delivery fills the lines from it: what was received, at what
 * it was bought, less whatever has already gone back against it.
 */
const onReceiptChange = async (receiptId) => {
    selectedReceipt.value = receipts.value.find((r) => r.id === receiptId) || null;
    if (!selectedReceipt.value) {
        form.items = [newLine()];
        return;
    }

    if (selectedReceipt.value.warehouse_id) form.warehouse_id = selectedReceipt.value.warehouse_id;

    const returned = {};
    try {
        const res = await purchaseReturnsApi.getAll({ purchase_receipt_id: receiptId, per_page: 100 });
        (res.data?.data?.returns || []).forEach((ret) => (ret.items || []).forEach((item) => {
            const key = pickKey(item.product_id, item.product_variant_id);
            returned[key] = (returned[key] || 0) + Number(item.quantity);
        }));
    } catch { /* the caps are a guide; the server still checks the shelf */ }
    returnedByPick.value = returned;

    // One line per product, or per size of it, as the server books it.
    const groups = new Map();
    (selectedReceipt.value.items || []).forEach((item) => {
        const key = pickKey(item.product_id, item.product_variant_id);
        const existing = groups.get(key);
        if (existing) {
            existing.received += Number(item.quantity);
        } else {
            groups.set(key, {
                option: optionFromLine(item),
                product: item.product,
                variant: item.variant || null,
                received: Number(item.quantity),
                price: Number(item.unit_price),
            });
        }
    });
    receiptGroups.value = groups;
    rememberVariantRows([...groups.values()].map((g) => g.option));

    form.items = [...groups.entries()].map(([key, info]) => {
        const line = newLine({
            pick: key,
            product_id: info.option.id,
            product_variant_id: info.option.variant_id || null,
            variant_label: info.option.variant_label || '',
            option: info.option,
            product: info.product,
            quantity: 0,
            cost: info.option.cost_price != null ? Number(info.option.cost_price) : null,
        });
        applyReceiptInfo(line);
        if (line.product_variant_id) ensureVariantsLoaded(line.product_id);
        return line;
    });
    if (!form.items.length) form.items = [newLine()];
    refreshAvailability();
};

// The chosen delivery's lines by product-and-size, and what earlier returns
// already took back against each, so a line knows what it can still return.
const receiptGroups = ref(new Map());
const returnedByPick = ref({});

const applyReceiptInfo = (line) => {
    const info = line.pick ? receiptGroups.value.get(line.pick) : null;
    if (!info) {
        line.received = null;
        line.alreadyReturned = 0;
        line.returnable = null;
        return;
    }
    const alreadyReturned = returnedByPick.value[line.pick] || 0;
    line.unit_price = info.price;
    line.received = info.received;
    line.alreadyReturned = alreadyReturned;
    line.returnable = Math.max(0, info.received - alreadyReturned);
};

/** The receipt's tax, pro rata to the share of its goods going back. */
const receiptTaxShare = computed(() => {
    const receipt = selectedReceipt.value;
    const tax = Number(receipt?.tax_amount) || 0;
    if (!receipt || !tax) return null;
    const goods = (receipt.items || []).reduce((sum, i) => sum + Number(i.quantity) * Number(i.unit_price), 0);
    if (!goods) return null;
    return Math.round((tax * creditTotal.value / goods) * 100) / 100;
});

// ── Lines ────────────────────────────────────────────────────────────────
const productResults = ref([]);
const productSearchLoading = ref(false);
let searchTimer = null;

const searchProducts = (query) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        productSearchLoading.value = true;
        try {
            // One row per size, and a size's own code finds it.
            const res = await productsApi.getAll({ search: query || undefined, per_page: 30, expand_variants: 1 });
            productResults.value = res.data?.data || [];
            rememberVariantRows(productResults.value);
        } catch {
            productResults.value = [];
        } finally {
            productSearchLoading.value = false;
        }
    }, 250);
};

// A line's own row stays in its options even when the search moves on.
const productOptions = (line) => {
    const list = productResults.value.slice();
    if (line.option && !list.some((p) => optionKey(p) === optionKey(line.option))) list.unshift(line.option);
    return list;
};

const productLabel = (product) => {
    const name = product.name_ar || product.name_en || `#${product.id}`;
    return product.sku ? `${name} (${product.sku})` : name;
};

// The server books one line per product-and-size, so a second line for the
// same one is steered to the first instead.
const isTaken = (key, line) => form.items.some((l) => l !== line && l.pick === key);

const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();
const siblingVariants = (line) => (line.product_id && line.product_variant_id ? siblingsOf(line.product_id) : []);

const applyRow = (line, row) => {
    line.pick = optionKey(row);
    line.option = row;
    line.product = row;
    line.product_id = row.id;
    line.product_variant_id = row.variant_id || null;
    line.variant_label = row.variant_label || '';
    line.cost = row.cost_price != null ? Number(row.cost_price) : null;
    if (row.variant_id) ensureVariantsLoaded(row.id);
};

const onProductChange = (line, key) => {
    const row = productResults.value.find((p) => optionKey(p) === key) || (line.option && optionKey(line.option) === key ? line.option : null);
    if (!row) return;
    applyRow(line, row);
    // A line for something on the chosen delivery is capped by it; one for
    // anything else is a free return.
    applyReceiptInfo(line);
    loadAvailability(line);
};

// Switching a line to another size keeps its quantity and a credit that was
// typed; the delivery's figures for that size apply when it is on it.
const switchLineVariant = (line, row) => {
    const key = optionKey(row);
    if (line.pick === key || isTaken(key, line)) return;
    const creditUntouched = line.unit_price === null || line.unit_price === (receiptGroups.value.get(line.pick)?.price ?? null);
    applyRow(line, row);
    if (creditUntouched) line.unit_price = null;
    applyReceiptInfo(line);
    loadAvailability(line);
};

const loadAvailability = async (line) => {
    if (!line.product_id || !form.warehouse_id) {
        line.available = null;
        return;
    }
    const productId = line.product_id;
    line.available = 'loading';
    try {
        const res = await inventoryApi.getStock({ product_id: productId, warehouse_id: form.warehouse_id, per_page: 50 });
        const rows = res.data?.data?.stock || [];
        if (line.product_id !== productId) return;
        line.available = rows.reduce((sum, row) => sum + Math.max(0, Number(row.available ?? row.quantity) || 0), 0);
    } catch {
        line.available = null;
    }
};

const refreshAvailability = () => form.items.forEach(loadAvailability);

const addLine = () => {
    form.items.push(newLine());
    if (!productResults.value.length) searchProducts('');
};

const removeLine = (index) => {
    if (form.items.length > 1) form.items.splice(index, 1);
};

const lineWarning = (line) => {
    if (!line.product_id) return '';
    if (line.returnable !== null && line.quantity > line.returnable) {
        return t('pret_more_than_received', { count: line.returnable });
    }
    if (typeof line.available === 'number' && line.quantity > line.available) {
        return t('pret_more_than_available', { count: line.available });
    }
    return '';
};

// Blank credit falls back to what the units cost; the product's cost price is
// the closest estimate the screen has of the FIFO figure the server will use.
const lineCredit = (line) => {
    if (!line.product_id || !line.quantity) return null;
    const unit = line.unit_price ?? line.cost;
    return unit === null ? null : unit * line.quantity;
};

const activeLines = computed(() => form.items.filter((l) => l.product_id && Number(l.quantity) > 0));
const creditTotal = computed(() => activeLines.value.reduce((sum, l) => sum + (lineCredit(l) || 0), 0));
const hasEstimate = computed(() => activeLines.value.some((l) => l.unit_price === null));

const blocker = computed(() => {
    if (!form.supplier_id) return t('pret_need_supplier');
    if (!form.warehouse_id) return t('pret_need_warehouse');
    if (!activeLines.value.length) return t('pret_need_items');
    if (activeLines.value.some((l) => l.returnable !== null && l.quantity > l.returnable)) return t('pret_fix_quantities');
    return '';
});

const isFuture = (date) => date.getTime() > Date.now();

// Figures inside the form, where the currency is already said once below.
const formatAmount = (value) => {
    const places = moneyDecimals(currencyDecimals(baseCurrencyCode()));
    return formatDecimal(Number(value) || 0, { minimumFractionDigits: places, maximumFractionDigits: places });
};

// ── Save ─────────────────────────────────────────────────────────────────
const submit = async () => {
    if (blocker.value) return;
    saving.value = true;
    try {
        const res = await purchaseReturnsApi.create({
            supplier_id: form.supplier_id,
            purchase_receipt_id: form.purchase_receipt_id || undefined,
            warehouse_id: form.warehouse_id,
            return_date: form.return_date,
            tax_amount: Number(form.tax_amount) || 0,
            reason: form.reason || null,
            notes: form.notes || null,
            items: activeLines.value.map((line) => ({
                product_id: line.product_id,
                product_variant_id: line.product_variant_id || null,
                quantity: Number(line.quantity),
                // Left out when blank, so the server falls back to what the
                // units actually cost — the honest default.
                ...(line.unit_price !== null ? { unit_price: Number(line.unit_price) } : {}),
            })),
        });

        const balance = res.data?.supplier_balance;
        ElMessage.success(balance !== undefined
            ? t('pret_recorded_balance', { amount: formatCurrency(balance, selectedSupplier.value?.currency) })
            : t('purchase_return_recorded'));
        snapshot = serialize();
        emit('saved', res.data?.data);
        emit('update:modelValue', false);
    } catch (e) {
        // The server explains precisely why — stock not on the shelf, or a
        // closed period — and echoing a generic failure would hide both.
        ElMessage.error(apiErrorMessage(e, t('failed_to_save_purchase_return')));
    } finally {
        saving.value = false;
    }
};
</script>

<style scoped>
.form-alert { margin-bottom: 1rem; }

.form-section {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.1rem 0.4rem;
    margin-bottom: 1rem;
    background: #fff;
}

.section-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.9rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.step {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    background: #eff6ff;
    color: #2563eb;
}

.field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0 1rem;
}

.field-hint { display: block; font-size: 0.78rem; color: #64748b; line-height: 1.5; margin-top: 0.25rem; }

.reason-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem; }
.reason-chip {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    border-radius: 999px;
    padding: 0.15rem 0.7rem;
    font-size: 0.78rem;
    cursor: pointer;
    font-family: inherit;
}
.reason-chip:hover { border-color: #93c5fd; color: #2563eb; }
.reason-chip.is-on { background: #eff6ff; border-color: #2563eb; color: #2563eb; }

.lines { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 0.6rem; }

.line {
    display: grid;
    grid-template-columns: minmax(0, 2.6fr) 120px 140px 110px 36px;
    gap: 0.6rem;
    align-items: start;
}
.line--head {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    padding-bottom: 0.2rem;
    border-bottom: 1px solid #f1f5f9;
}

.line-qty, .line-price { width: 100%; }
.line-product { min-width: 0; }

.line-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }

.product-option { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.product-option-name { display: inline-flex; align-items: center; gap: 0.4rem; min-width: 0; }
.product-option small { color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

/* Sibling sizes under a line */
.line-sizes { display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem; margin-top: 0.35rem; }
.sizes-label { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; font-weight: 600; color: #64748b; }

.size-chip {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1.5;
    transition: all 0.15s ease;
}

.size-chip:hover:not(:disabled) { border-color: #93c5fd; color: #1d4ed8; }
.size-chip.is-received { border-color: #fcd34d; background: #fffbeb; }
.size-chip.is-active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; cursor: default; }
.size-chip:disabled { cursor: default; opacity: 0.45; }
.size-chip span { unicode-bidi: isolate; }
.size-chip small { font-size: 0.66rem; color: #b45309; padding-inline-start: 0.3rem; border-inline-start: 1px solid #fde68a; }
.line-meta .is-warn { color: #d97706; font-weight: 600; }
.line-warning { display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: #d97706; margin-top: 0.2rem; }

.line-total { padding-top: 0.45rem; font-weight: 600; }
.num { text-align: end; font-variant-numeric: tabular-nums; }
.approx { color: #94a3b8; margin-inline-end: 0.15rem; }
.muted { color: #94a3b8; }

.hint { display: block; color: #64748b; font-size: 0.78rem; margin-bottom: 0.8rem; line-height: 1.6; }

.form-footer { display: flex; flex-direction: column; gap: 0.75rem; }

.totals {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
    margin: 0;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
.totals > div { display: flex; flex-direction: column; }
.totals dt { font-size: 0.75rem; color: #64748b; }
.totals dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
.totals .grand { margin-inline-start: auto; text-align: end; }
.totals .grand dd { font-size: 1.1rem; color: #16a34a; }

.footer-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.6rem; flex-wrap: wrap; }
.blocker { font-size: 0.8rem; color: #64748b; margin-inline-end: auto; }

@media (max-width: 768px) {
    .line {
        grid-template-columns: 1fr 1fr 36px;
        padding-bottom: 0.6rem;
        border-bottom: 1px dashed #e2e8f0;
    }
    .line--head { display: none; }
    .line-product { grid-column: 1 / -1; }
    .line-total { grid-column: 1 / 3; text-align: start; padding-top: 0; }
    .totals .grand { margin-inline-start: 0; text-align: start; }
}
</style>
