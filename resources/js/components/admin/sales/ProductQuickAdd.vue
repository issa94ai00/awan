<template>
    <!--
        "New product" from inside a sales form. A customer asking for an item
        the catalogue does not have yet should not cost the seller the order:
        a name, a category and a price are enough to sell it, and the product
        page can be completed later.
    -->
    <el-dialog
        :model-value="modelValue"
        width="min(560px, 94vw)"
        class="pqa-dialog"
        append-to-body
        destroy-on-close
        :close-on-click-modal="false"
        @update:model-value="emit('update:modelValue', $event)"
        @opened="onOpened"
    >
        <template #header>
            <div class="pqa-head">
                <span class="pqa-head-icon"><i class="fas fa-box-open"></i></span>
                <div>
                    <div class="pqa-title">{{ $t('pqa_title') }}</div>
                    <div class="pqa-sub">{{ $t('pqa_hint') }}</div>
                </div>
            </div>
        </template>

        <div class="pqa-body" @keydown.enter="onEnter">
            <!-- Name -->
            <div class="pqa-field">
                <label class="pqa-label">{{ $t('pqa_name') }} <span class="pqa-req">*</span></label>
                <el-input
                    ref="nameRef"
                    v-model="form.name_ar"
                    size="large"
                    maxlength="255"
                    :placeholder="$t('pqa_name_placeholder')"
                    :class="{ 'is-error': errors.name_ar }"
                    @input="onNameInput"
                />
                <small v-if="errors.name_ar" class="pqa-error">{{ errors.name_ar }}</small>
            </div>

            <!-- Similar products already in the catalogue: the cheapest duplicate is the one never made -->
            <div v-if="similar.length" class="pqa-similar">
                <div class="pqa-similar-head">
                    <i class="fas fa-lightbulb"></i> {{ $t('pqa_similar_title') }}
                </div>
                <button
                    v-for="row in similar"
                    :key="row.listing_key || row.id"
                    type="button"
                    class="pqa-similar-row"
                    @click="useExisting(row)"
                >
                    <span class="pqa-similar-name">
                        {{ row.product_name_ar || row.name_ar }}
                        <small v-if="row.variant_label" dir="ltr">· {{ row.variant_label }}</small>
                    </span>
                    <span class="pqa-similar-meta">
                        <span v-if="row.sku" class="pqa-mono" dir="ltr">{{ row.sku }}</span>
                        <strong dir="ltr">{{ formatCurrency(row.price) }}</strong>
                        <span class="pqa-use">{{ $t('pqa_use_this') }} <i class="fas fa-arrow-left-long pqa-flip"></i></span>
                    </span>
                </button>
            </div>

            <!-- Category -->
            <div class="pqa-field">
                <label class="pqa-label">{{ $t('category') }} <span class="pqa-req">*</span></label>
                <el-select
                    v-model="form.category_id"
                    filterable
                    size="large"
                    class="pqa-full"
                    :placeholder="$t('pqa_category_placeholder')"
                    :class="{ 'is-error': errors.category_id }"
                    @change="clearError('category_id')"
                >
                    <el-option v-for="c in categories" :key="c.id" :value="c.id" :label="c.label">
                        <span :class="{ 'pqa-sub-cat': c.parent_id }">{{ c.label }}</span>
                    </el-option>
                </el-select>
                <small v-if="errors.category_id" class="pqa-error">{{ errors.category_id }}</small>
            </div>

            <!-- Prices -->
            <div class="pqa-grid">
                <div class="pqa-field">
                    <label class="pqa-label">{{ $t('pqa_sale_price') }} <span class="pqa-req">*</span></label>
                    <el-input
                        ref="priceRef"
                        v-model="form.price"
                        size="large"
                        type="number"
                        min="0"
                        step="0.01"
                        dir="ltr"
                        inputmode="decimal"
                        :class="{ 'is-error': errors.price }"
                        @input="clearError('price')"
                    >
                        <template #append>{{ currency }}</template>
                    </el-input>
                    <small v-if="errors.price" class="pqa-error">{{ errors.price }}</small>
                </div>
                <div class="pqa-field">
                    <label class="pqa-label">{{ $t('pqa_cost_price') }} <span class="pqa-opt">{{ $t('optional') }}</span></label>
                    <el-input v-model="form.cost_price" size="large" type="number" min="0" step="0.01" dir="ltr" inputmode="decimal">
                        <template #append>{{ currency }}</template>
                    </el-input>
                </div>
            </div>
            <div v-if="margin" class="pqa-margin" :class="margin.tone">
                <i class="fas" :class="margin.tone === 'is-loss' ? 'fa-triangle-exclamation' : 'fa-chart-line'"></i>
                {{ margin.text }}
            </div>

            <!-- Unit and quantity -->
            <div class="pqa-grid">
                <div class="pqa-field">
                    <label class="pqa-label">{{ $t('so_unit') }}</label>
                    <div class="pqa-chips">
                        <button
                            v-for="u in UNITS"
                            :key="u"
                            type="button"
                            class="pqa-chip"
                            :class="{ 'is-on': form.unit === u }"
                            @click="form.unit = u"
                        >
                            {{ u }}
                        </button>
                    </div>
                    <el-input v-model="form.unit" maxlength="50" :placeholder="$t('pqa_unit_other')" />
                </div>
                <div class="pqa-field">
                    <label class="pqa-label">{{ $t('pqa_qty_to_add') }}</label>
                    <el-input-number v-model="form.quantity" :min="1" :max="99999" size="large" class="pqa-full" />
                </div>
            </div>

            <!-- Optional identifiers -->
            <button type="button" class="pqa-more-toggle" @click="showMore = !showMore">
                <i class="fas" :class="showMore ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                {{ $t('pqa_more') }}
                <span v-if="!showMore && (form.sku || form.barcode || form.name_en)" class="pqa-more-dot"></span>
            </button>
            <div v-show="showMore" class="pqa-grid pqa-grid-3">
                <div class="pqa-field">
                    <label class="pqa-label">SKU</label>
                    <el-input v-model="form.sku" maxlength="255" dir="ltr" :class="{ 'is-error': errors.sku }" @input="clearError('sku')" />
                    <small v-if="errors.sku" class="pqa-error">{{ errors.sku }}</small>
                </div>
                <div class="pqa-field">
                    <label class="pqa-label">{{ $t('barcode') }}</label>
                    <el-input v-model="form.barcode" maxlength="255" dir="ltr" />
                </div>
                <div class="pqa-field">
                    <label class="pqa-label">{{ $t('product_name_en') }}</label>
                    <el-input v-model="form.name_en" maxlength="255" dir="ltr" />
                </div>
            </div>

            <div class="pqa-note">
                <i class="fas fa-circle-info"></i>
                <span>{{ stockNote || $t('pqa_stock_note') }}</span>
            </div>
        </div>

        <template #footer>
            <div class="pqa-foot">
                <span class="pqa-kbd"><kbd>Enter</kbd> {{ $t('pqa_save') }} · <kbd>Esc</kbd> {{ $t('cancel') }}</span>
                <el-button @click="emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="save">
                    <i v-if="!saving" class="fas fa-plus"></i>&nbsp;{{ $t('pqa_save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { productsApi } from '@/api/products';
import { posApi } from '@/api/pos';
import { baseCurrencyCode } from '@/utils/currency';
import { apiErrorMessage, formatCurrency } from '@/utils/sales';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** What the seller searched for before giving up: a code seeds SKU/barcode, words seed the name. */
    seed: { type: String, default: '' },
    /** Category options as the form lists them: { id, label, parent_id }. */
    categories: { type: Array, default: () => [] },
    /** The category the search was filtered to, if any. */
    defaultCategoryId: { type: [Number, String], default: null },
    /** What the new product's empty stock means on this form, when the general note does not fit. */
    stockNote: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'created', 'use-existing']);

const { t } = useI18n();

// The units the catalogue actually uses, most common first.
const UNITS = ['قطعة', 'جوز', 'متر', 'كغ', 'علبة', 'كيس', 'صندوق', 'طقم'];
const LAST_CATEGORY_KEY = 'pqa_last_category';

const currency = baseCurrencyCode();
const nameRef = ref(null);
const priceRef = ref(null);
const saving = ref(false);
const showMore = ref(false);
const similar = ref([]);
const errors = reactive({});
const form = reactive({});

const blank = () => ({
    name_ar: '', name_en: '', category_id: null, price: '', cost_price: '',
    unit: UNITS[0], quantity: 1, sku: '', barcode: '',
});

const clearError = (key) => delete errors[key];

const readLastCategory = () => {
    try { return Number(localStorage.getItem(LAST_CATEGORY_KEY)) || null; } catch { return null; }
};
const writeLastCategory = (id) => {
    try { localStorage.setItem(LAST_CATEGORY_KEY, String(id)); } catch { /* storage is a convenience */ }
};

// A scanned or typed code has no spaces and carries digits.
const looksLikeCode = (text) => /^[\w\-./]+$/.test(text) && /\d/.test(text);

const reset = () => {
    Object.assign(form, blank());
    Object.keys(errors).forEach((k) => delete errors[k]);
    similar.value = [];
    showMore.value = false;

    const seed = String(props.seed || '').trim();
    if (seed && looksLikeCode(seed)) {
        if (/^\d{8,}$/.test(seed)) form.barcode = seed;
        else form.sku = seed;
        showMore.value = true;
    } else {
        form.name_ar = seed;
    }

    const known = (id) => id && props.categories.some((c) => c.id === id);
    form.category_id = [props.defaultCategoryId, readLastCategory()].find(known) || null;
    if (form.name_ar) findSimilar();
};

watch(() => props.modelValue, (open) => { if (open) reset(); }, { immediate: true });

const onOpened = () => nextTick(() => (form.name_ar && form.category_id ? priceRef.value : nameRef.value)?.focus());

const margin = computed(() => {
    const price = Number(form.price);
    const cost = Number(form.cost_price);
    if (!price || !cost) return null;
    const profit = price - cost;
    const pct = Math.round((profit / cost) * 100);
    if (profit < 0) return { tone: 'is-loss', text: t('pqa_margin_loss', { amount: formatCurrency(-profit) }) };
    return { tone: 'is-ok', text: t('pqa_margin', { amount: formatCurrency(profit), pct }) };
});

// ── Similar products, looked up as the name is typed ──
let similarTimer = null;
let similarSeq = 0;
const findSimilar = async () => {
    const q = form.name_ar.trim();
    if (q.length < 3) {
        similar.value = [];
        return;
    }
    const mine = ++similarSeq;
    try {
        const { data } = await posApi.productLookup({ q });
        if (mine !== similarSeq) return;
        const rows = Array.isArray(data?.data) ? data.data : [];
        const seen = new Set();
        similar.value = rows.filter((r) => !seen.has(r.id) && seen.add(r.id)).slice(0, 4);
    } catch {
        // The hint is a courtesy; creating still works without it.
    }
};
const onNameInput = () => {
    clearError('name_ar');
    clearTimeout(similarTimer);
    similarTimer = setTimeout(findSimilar, 350);
};
onUnmounted(() => clearTimeout(similarTimer));

const useExisting = (row) => {
    emit('use-existing', row, form.quantity);
    emit('update:modelValue', false);
};

/** Enter in a text field saves; on a button or inside a dropdown it keeps its own meaning. */
const onEnter = (event) => {
    if (event.target?.tagName !== 'INPUT' || event.target.closest('.el-select')) return;
    event.preventDefault();
    save();
};

const validate = () => {
    Object.keys(errors).forEach((k) => delete errors[k]);
    if (!form.name_ar.trim()) errors.name_ar = t('pqa_name_required');
    if (!form.category_id) errors.category_id = t('pqa_category_required');
    if (form.price === '' || Number(form.price) < 0 || Number.isNaN(Number(form.price))) errors.price = t('pqa_price_required');
    return !Object.keys(errors).length;
};

const save = async () => {
    if (saving.value) return;
    if (!validate()) {
        if (errors.name_ar) nameRef.value?.focus();
        else if (errors.price) priceRef.value?.focus();
        return;
    }
    saving.value = true;
    try {
        const { data } = await productsApi.create({
            name_ar: form.name_ar.trim(),
            name_en: form.name_en.trim() || null,
            category_id: form.category_id,
            price: Number(form.price),
            cost_price: form.cost_price === '' ? null : Number(form.cost_price),
            unit: form.unit.trim() || null,
            sku: form.sku.trim() || null,
            barcode: form.barcode.trim() || null,
            currency,
            stock_quantity: 0,
            is_active: true,
        });
        const product = data?.data || data;
        writeLastCategory(form.category_id);
        emit('created', product, form.quantity);
        emit('update:modelValue', false);
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        Object.entries(fieldErrors).forEach(([key, messages]) => {
            errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
        });
        if (errors.sku || errors.barcode || errors.name_en) showMore.value = true;
        if (!Object.keys(fieldErrors).length) ElMessage.error(apiErrorMessage(error, t('failed_to_create_product')));
    } finally {
        saving.value = false;
    }
};
</script>

<style scoped>
.pqa-dialog :global(.el-dialog__header) { padding-bottom: 0.5rem; }
.pqa-head { display: flex; align-items: center; gap: 0.75rem; }
.pqa-head-icon {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0; display: grid; place-items: center;
    background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-size: 1rem;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);
}
.pqa-title { font-size: 1rem; font-weight: 800; color: #0f172a; }
.pqa-sub { font-size: 0.78rem; color: #64748b; }

.pqa-body { display: flex; flex-direction: column; gap: 0.85rem; }
.pqa-field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.pqa-label { font-size: 0.8rem; font-weight: 600; color: #334155; }
.pqa-req { color: #dc2626; }
.pqa-opt { font-weight: 400; color: #94a3b8; font-size: 0.72rem; }
.pqa-error { font-size: 0.74rem; color: #dc2626; }
.pqa-field :deep(.is-error .el-input__wrapper),
.pqa-field :deep(.is-error .el-select__wrapper) { box-shadow: 0 0 0 1px #f87171 inset; }
.pqa-full { width: 100%; }
.pqa-sub-cat { padding-inline-start: 1rem; color: #475569; }

.pqa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.pqa-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }

.pqa-similar { border: 1px solid #fde68a; background: #fffbeb; border-radius: 10px; padding: 0.5rem; }
.pqa-similar-head { font-size: 0.78rem; font-weight: 700; color: #92400e; padding: 0 0.25rem 0.35rem; }
.pqa-similar-row {
    all: unset; box-sizing: border-box; width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;
    padding: 0.4rem 0.5rem; border-radius: 6px; cursor: pointer; font-size: 0.82rem; color: #1e293b;
}
.pqa-similar-row:hover, .pqa-similar-row:focus-visible { background: #fef3c7; }
.pqa-similar-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.pqa-similar-name small { color: #64748b; font-weight: 400; }
.pqa-similar-meta { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; color: #475569; }
.pqa-use { color: #b45309; font-weight: 700; font-size: 0.75rem; }
.pqa-flip { display: inline-block; }
[dir='ltr'] .pqa-flip { transform: scaleX(-1); }
.pqa-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; }

.pqa-margin { font-size: 0.78rem; margin-top: -0.4rem; display: flex; align-items: center; gap: 0.4rem; }
.pqa-margin.is-ok { color: #047857; }
.pqa-margin.is-loss { color: #dc2626; font-weight: 600; }

.pqa-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.pqa-chip {
    all: unset; cursor: pointer; font-size: 0.75rem; padding: 0.15rem 0.6rem; border-radius: 999px;
    border: 1px solid #cbd5e1; color: #475569; background: #fff; line-height: 1.6;
}
.pqa-chip:hover { border-color: #10b981; color: #047857; }
.pqa-chip.is-on { background: #ecfdf5; border-color: #10b981; color: #047857; font-weight: 700; }
.pqa-chip:focus-visible { outline: 2px solid #10b981; outline-offset: 1px; }

.pqa-more-toggle {
    all: unset; cursor: pointer; align-self: flex-start; font-size: 0.8rem; font-weight: 600; color: #2563eb;
    display: inline-flex; align-items: center; gap: 0.4rem;
}
.pqa-more-dot { width: 6px; height: 6px; border-radius: 50%; background: #2563eb; }

.pqa-note {
    display: flex; gap: 0.5rem; align-items: baseline; font-size: 0.76rem; color: #475569;
    background: #f1f5f9; border-radius: 8px; padding: 0.5rem 0.7rem;
}
.pqa-note i { color: #64748b; }

.pqa-foot { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; }
.pqa-kbd { margin-inline-end: auto; font-size: 0.74rem; color: #94a3b8; }
.pqa-kbd kbd {
    font-family: inherit; font-size: 0.68rem; padding: 0 0.35rem; border: 1px solid #cbd5e1; border-bottom-width: 2px;
    border-radius: 4px; background: #f8fafc; color: #64748b;
}

@media (max-width: 600px) {
    .pqa-grid, .pqa-grid-3 { grid-template-columns: 1fr; }
    .pqa-kbd { display: none; }
}
</style>
