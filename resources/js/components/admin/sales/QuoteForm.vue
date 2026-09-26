<template>
    <el-dialog
        :model-value="modelValue"
        :title="quote ? $t('qt_edit_title', { number: quote.quote_number }) : $t('qt_new_title')"
        :width="dialogWidth"
        class="quote-form-dialog"
        :close-on-click-modal="false"
        :before-close="(done) => requestClose(done)"
        @open="reset"
    >
        <el-form label-position="top" class="quote-form" @submit.prevent>
            <div class="head-grid">
                <el-form-item :label="$t('client')" required :error="errors.customer_id">
                    <el-select
                        v-model="form.customer_id"
                        filterable
                        remote
                        :remote-method="searchCustomers"
                        :loading="customersLoading"
                        :placeholder="$t('qt_find_customer')"
                        style="width: 100%"
                        @focus="!customerOptions.length && searchCustomers('')"
                    >
                        <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id">
                            <div class="option-row">
                                <span>{{ c.name }}</span>
                                <span class="option-meta" dir="ltr">{{ c.phone || c.company || '' }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('valid_until')" :error="errors.valid_until">
                    <el-date-picker
                        v-model="form.valid_until"
                        type="date"
                        value-format="YYYY-MM-DD"
                        format="YYYY-MM-DD"
                        :disabled-date="(d) => localIsoDate(d) < localIsoDate()"
                        :placeholder="$t('qt_no_expiry')"
                        style="width: 100%"
                    />
                    <div class="quick-days">
                        <button
                            v-for="days in [7, 14, 30]"
                            :key="days"
                            type="button"
                            class="chip"
                            :class="{ 'is-on': form.valid_until === inDays(days) }"
                            @click="form.valid_until = inDays(days)"
                        >
                            {{ $t('qt_days_n', { count: days }) }}
                        </button>
                    </div>
                </el-form-item>
            </div>

            <!-- ── Lines ── -->
            <div class="lines-head">
                <h4>{{ $t('items') }}</h4>
                <el-select
                    ref="productPicker"
                    v-model="pickedProduct"
                    filterable
                    remote
                    clearable
                    :remote-method="searchProducts"
                    :loading="productsLoading"
                    :placeholder="$t('qt_add_product')"
                    class="product-picker"
                    value-key="id"
                    @change="addProduct"
                >
                    <template #prefix><i class="fas fa-magnifying-glass"></i></template>
                    <el-option v-for="p in productOptions" :key="p.id" :label="p.name_ar" :value="p">
                        <div class="option-row">
                            <span class="option-name">{{ productName(p) }}</span>
                            <span class="option-meta">
                                <span v-if="p.sku" dir="ltr">{{ p.sku }}</span>
                                <strong>{{ formatCurrency(listPrice(p)) }}</strong>
                            </span>
                        </div>
                    </el-option>
                </el-select>
            </div>

            <div v-if="!form.items.length" class="lines-empty" :class="{ 'has-error': errors.items }">
                <i class="fas fa-box-open"></i>
                <span>{{ errors.items || $t('qt_no_lines') }}</span>
            </div>

            <div v-else class="lines">
                <div class="line line--head" aria-hidden="true">
                    <span>{{ $t('product') }}</span>
                    <span>{{ $t('quantity') }}</span>
                    <span>{{ $t('unit_price') }}</span>
                    <span>{{ $t('discount') }}</span>
                    <span>{{ $t('tax') }}</span>
                    <span class="num">{{ $t('total') }}</span>
                    <span />
                </div>
                <div v-for="(line, i) in form.items" :key="line.key" class="line">
                    <div class="line-product">
                        <strong>{{ line.name }}</strong>
                        <span v-if="line.sku" class="option-meta" dir="ltr">{{ line.sku }}</span>
                    </div>
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('quantity') }}</span>
                        <el-input-number v-model="line.quantity" :min="1" :step="1" step-strictly size="small" controls-position="right" />
                    </label>
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('unit_price') }}</span>
                        <el-input-number v-model="line.unit_price" :min="0" :precision="2" :controls="false" size="small" />
                    </label>
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('discount') }}</span>
                        <el-input-number
                            v-model="line.discount"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            size="small"
                            :class="{ 'is-invalid': lineDiscountTooBig(line) || errors[`items.${i}.discount`] }"
                        />
                    </label>
                    <label class="line-field">
                        <span class="mobile-label">{{ $t('tax') }}</span>
                        <el-input-number v-model="line.tax" :min="0" :precision="2" :controls="false" size="small" />
                    </label>
                    <span class="num line-total">{{ formatCurrency(lineTotal(line)) }}</span>
                    <el-button text circle type="danger" :aria-label="$t('delete')" @click="form.items.splice(i, 1)">
                        <i class="fas fa-xmark"></i>
                    </el-button>
                </div>
            </div>

            <!-- ── Totals ── -->
            <div class="bottom-grid">
                <div>
                    <el-form-item :label="$t('notes')">
                        <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" show-word-limit />
                    </el-form-item>
                    <el-form-item :label="$t('qt_terms')">
                        <el-input v-model="form.terms" type="textarea" :rows="2" maxlength="2000" :placeholder="$t('qt_terms_placeholder')" />
                    </el-form-item>
                </div>

                <div class="totals">
                    <div class="totals-row">
                        <span>{{ $t('subtotal') }}</span>
                        <span class="num">{{ formatCurrency(subtotal) }}</span>
                    </div>
                    <div class="totals-row">
                        <span>{{ $t('qt_extra_discount') }}</span>
                        <el-input-number
                            v-model="form.discount"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            size="small"
                            :class="{ 'is-invalid': discountTooBig || errors.discount }"
                        />
                    </div>
                    <div class="totals-row">
                        <span>{{ $t('qt_extra_tax') }}</span>
                        <el-input-number v-model="form.tax" :min="0" :precision="2" :controls="false" size="small" />
                    </div>
                    <div class="totals-row grand">
                        <span>{{ $t('total') }}</span>
                        <span class="num">{{ formatCurrency(total) }}</span>
                    </div>
                </div>
            </div>

            <el-alert v-if="blocker" type="warning" :closable="false" show-icon :title="blocker" class="blocker" />
        </el-form>

        <template #footer>
            <el-button @click="requestClose()">{{ $t('cancel') }}</el-button>
            <el-button :loading="saving === 'draft'" :disabled="!!blocker || !!saving" @click="submit(false)">
                {{ quote ? $t('save_changes') : $t('qt_save_draft') }}
            </el-button>
            <el-button
                v-if="!quote || quote.status === 'draft'"
                type="primary"
                :loading="saving === 'send'"
                :disabled="!!blocker || !!saving"
                @click="submit(true)"
            >
                <i class="fas fa-paper-plane"></i>&nbsp;{{ $t('qt_save_and_send') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { posApi } from '@/api/pos';
import { useQuotesStore } from '@/stores/quotes';
import { apiErrorMessage, formatCurrency, localIsoDate } from '@/utils/sales';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The full quote to edit; null to write a new one. */
    quote: { type: Object, default: null },
    /** Starting customer for a new quote (e.g. the one the list is filtered on). */
    presetCustomer: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'saved']);

const { t, locale } = useI18n();
const store = useQuotesStore();

const blank = () => ({
    customer_id: null,
    valid_until: inDays(14),
    discount: 0,
    tax: 0,
    notes: '',
    terms: '',
    items: [],
});

const form = reactive(blank());
const errors = reactive({});
const saving = ref(null);
const dialogWidth = computed(() => (window.innerWidth < 900 ? '96%' : '860px'));

let lineSeq = 0;
let snapshot = '';
const serialize = () => JSON.stringify(form);

function inDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return localIsoDate(d);
}

const toNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);

// ── Customers ──────────────────────────────────────────────────────────
const customerOptions = ref([]);
const customersLoading = ref(false);
let customerTimer = null;

const searchCustomers = (query) => {
    clearTimeout(customerTimer);
    customerTimer = setTimeout(async () => {
        customersLoading.value = true;
        try {
            const res = await posApi.customers({ search: query || undefined, per_page: 30 });
            const found = res.data?.data?.customers || [];
            // Keep the chosen customer listed, or the select shows a bare id.
            const current = customerOptions.value.find((c) => c.id === form.customer_id);
            customerOptions.value = current && !found.some((c) => c.id === current.id) ? [current, ...found] : found;
        } catch {
            // The list stays as it was; typing again retries.
        } finally {
            customersLoading.value = false;
        }
    }, 250);
};

// ── Products ───────────────────────────────────────────────────────────
const productOptions = ref([]);
const productsLoading = ref(false);
const pickedProduct = ref(null);
const productPicker = ref(null);
let productTimer = null;

const productName = (p) => (locale.value === 'en' && p.name_en ? p.name_en : p.name_ar);
// The price the customer would pay today, sale included.
const listPrice = (p) => toNum(p.has_sale && p.sale_price ? p.sale_price : p.price);

const searchProducts = (query) => {
    clearTimeout(productTimer);
    if (!query || query.trim().length < 2) {
        productOptions.value = [];
        return;
    }
    productTimer = setTimeout(async () => {
        productsLoading.value = true;
        try {
            const res = await posApi.productLookup({ q: query.trim() });
            const data = res.data?.data || [];
            productOptions.value = Array.isArray(data) ? data : [];
        } catch {
            productOptions.value = [];
        } finally {
            productsLoading.value = false;
        }
    }, 250);
};

const addProduct = (product) => {
    if (!product) return;
    // A second pick of the same product adds to its line.
    const existing = form.items.find((line) => line.product_id === product.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        form.items.push({
            key: ++lineSeq,
            product_id: product.id,
            name: productName(product),
            sku: product.sku || '',
            quantity: 1,
            unit_price: listPrice(product),
            discount: 0,
            tax: 0,
        });
    }
    delete errors.items;
    pickedProduct.value = null;
    productOptions.value = [];
    productPicker.value?.focus?.();
};

// ── Totals ─────────────────────────────────────────────────────────────
const lineGross = (line) => toNum(line.unit_price) * toNum(line.quantity);
const lineTotal = (line) => lineGross(line) - toNum(line.discount) + toNum(line.tax);
const lineDiscountTooBig = (line) => toNum(line.discount) > lineGross(line) + 0.00001;

const subtotal = computed(() => form.items.reduce((sum, line) => sum + lineTotal(line), 0));
const discountTooBig = computed(() => toNum(form.discount) > subtotal.value + 0.00001);
const total = computed(() => subtotal.value - toNum(form.discount) + toNum(form.tax));

// Mirrors what the API refuses, so the reason shows before the save does.
const blocker = computed(() => {
    if (!form.customer_id) return t('qt_need_customer');
    if (!form.items.length) return t('qt_need_lines');
    if (form.items.some(lineDiscountTooBig)) return t('qt_line_discount_too_big');
    if (discountTooBig.value) return t('qt_discount_too_big');
    return '';
});

// ── Open, close, save ──────────────────────────────────────────────────
const reset = () => {
    Object.keys(errors).forEach((k) => delete errors[k]);
    saving.value = null;
    pickedProduct.value = null;
    productOptions.value = [];

    const q = props.quote;
    if (q) {
        Object.assign(form, {
            customer_id: q.customer_id,
            // A lapsed date cannot be saved again; leave it for the user to set.
            valid_until: q.valid_until && String(q.valid_until).slice(0, 10) >= localIsoDate()
                ? String(q.valid_until).slice(0, 10)
                : null,
            discount: toNum(q.discount),
            tax: toNum(q.tax),
            notes: q.notes || '',
            terms: q.terms || '',
            items: (q.items || []).map((item) => ({
                key: ++lineSeq,
                product_id: item.product_id,
                name: item.description || item.product?.name_ar || '—',
                sku: item.product?.sku || '',
                quantity: toNum(item.quantity) || 1,
                unit_price: toNum(item.unit_price),
                discount: toNum(item.discount),
                tax: toNum(item.tax),
            })),
        });
        customerOptions.value = q.customer ? [q.customer] : [];
    } else {
        Object.assign(form, blank());
        form.customer_id = props.presetCustomer?.id || null;
        customerOptions.value = props.presetCustomer ? [props.presetCustomer] : [];
    }
    snapshot = serialize();
};

const requestClose = async (done) => {
    if (saving.value) return;
    if (serialize() !== snapshot) {
        try {
            await ElMessageBox.confirm(t('prod_admin_discard_changes'), t('confirm'), {
                confirmButtonText: t('prod_admin_discard'),
                cancelButtonText: t('prod_admin_keep_editing'),
                type: 'warning',
            });
        } catch {
            return;
        }
    }
    if (typeof done === 'function') done();
    emit('update:modelValue', false);
};

const submit = async (andSend) => {
    if (blocker.value || saving.value) return;
    Object.keys(errors).forEach((k) => delete errors[k]);
    saving.value = andSend ? 'send' : 'draft';

    const payload = {
        customer_id: form.customer_id,
        valid_until: form.valid_until || null,
        discount: toNum(form.discount),
        tax: toNum(form.tax),
        notes: form.notes || null,
        terms: form.terms || null,
        items: form.items.map((line) => ({
            product_id: line.product_id,
            description: line.name,
            quantity: toNum(line.quantity),
            unit_price: toNum(line.unit_price),
            discount: toNum(line.discount),
            tax: toNum(line.tax),
        })),
    };

    try {
        let saved = props.quote
            ? await store.updateQuote(props.quote.id, payload)
            : await store.createQuote(payload);

        if (andSend && saved?.status === 'draft') {
            try {
                saved = await store.updateQuoteStatus(saved, 'sent');
            } catch (error) {
                ElMessage.warning(apiErrorMessage(error, t('failed_to_update_quote_status')));
            }
        }

        ElMessage.success(props.quote ? t('qt_saved') : t('qt_created', { number: saved?.quote_number || '' }));
        snapshot = serialize();
        emit('saved', saved);
        emit('update:modelValue', false);
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        Object.entries(fieldErrors).forEach(([key, messages]) => {
            errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
        });
        ElMessage.error(apiErrorMessage(error, t('qt_save_failed')));
    } finally {
        saving.value = null;
    }
};
</script>

<style scoped>
.quote-form { font-family: 'Cairo', sans-serif; }

.head-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 0 1rem; }

.quick-days { display: flex; gap: 0.35rem; margin-top: 0.4rem; flex-wrap: wrap; }
.chip {
    all: unset;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.1rem 0.6rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    color: #475569;
    line-height: 1.6;
}
.chip:hover { border-color: #93c5fd; color: #2563eb; }
.chip.is-on { background: #eff6ff; border-color: #2563eb; color: #2563eb; font-weight: 600; }
.chip:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

.option-row { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
.option-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.option-meta { display: inline-flex; gap: 0.6rem; font-size: 0.78rem; color: #64748b; }

.lines-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 0.25rem 0 0.6rem; flex-wrap: wrap; }
.lines-head h4 { margin: 0; font-size: 0.95rem; }
.product-picker { flex: 1 1 280px; max-width: 420px; }

.lines-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 1.4rem;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
    font-size: 0.88rem;
}
.lines-empty.has-error { border-color: #f87171; color: #b91c1c; }

.lines { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.line {
    display: grid;
    grid-template-columns: minmax(0, 2.2fr) 110px 110px 95px 95px minmax(90px, 1fr) 36px;
    gap: 0.5rem;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}
.line:last-child { border-bottom: none; }
.line--head { background: #f8fafc; font-size: 0.75rem; color: #64748b; font-weight: 600; }
.line-product { display: flex; flex-direction: column; min-width: 0; }
.line-product strong { font-size: 0.86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.line-field :deep(.el-input-number) { width: 100%; }
.mobile-label { display: none; }
.num { text-align: end; font-variant-numeric: tabular-nums; }
.line-total { font-weight: 700; }
.is-invalid :deep(.el-input__wrapper) { box-shadow: 0 0 0 1px #ef4444 inset; }

.bottom-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 1.25rem; margin-top: 1rem; }
.totals { background: #f8fafc; border-radius: 10px; padding: 0.75rem 1rem; align-self: start; }
.totals-row { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.3rem 0; font-size: 0.88rem; }
.totals-row :deep(.el-input-number) { width: 120px; }
.totals-row.grand { border-top: 1px solid #e2e8f0; margin-top: 0.3rem; padding-top: 0.6rem; font-size: 1.05rem; font-weight: 800; }

.blocker { margin-top: 0.75rem; }

@media (max-width: 760px) {
    .head-grid, .bottom-grid { grid-template-columns: 1fr; }
    .line--head { display: none; }
    .line { grid-template-columns: 1fr 1fr; }
    .line-product { grid-column: 1 / -1; }
    .mobile-label { display: block; font-size: 0.72rem; color: #64748b; }
    .product-picker { max-width: none; }
}
</style>
