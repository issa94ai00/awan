<template>
    <div class="pline" :class="[`tone-${tone}`, { 'is-locked': locked }]">
        <span class="pline-idx">{{ index + 1 }}</span>

        <div class="pline-product">
            <EntityImage :src="imageOf(row)" type="product" :size="44" shape="square" class="pline-thumb" />
            <div class="pline-info">
                <div class="pline-title">
                    <strong class="pline-name" :title="title">{{ title }}</strong>
                    <VariantChip v-if="item.product_variant_id && variantLabel" :label="variantLabel" />
                </div>
                <div class="pline-meta">
                    <span v-if="sku" class="pline-sku" dir="ltr"><i class="fas fa-barcode"></i> {{ sku }}</span>
                    <span v-if="stock != null" class="pline-stock" :class="stock > 0 ? 'is-ok' : 'is-out'">
                        <i class="fas fa-warehouse"></i> {{ $t('available') }}: {{ stock }}
                    </span>
                </div>

                <!-- The product's other sizes, one click away: a supplier who
                     sent the 3" rather than the 4" is recorded without
                     searching again. Required when the line has no size. -->
                <div v-if="!locked && (needsSize || siblings.length > 1)" class="pline-sizes" :class="{ 'is-required': needsSize }">
                    <span class="pline-sizes-label">
                        <i class="fas" :class="needsSize ? 'fa-circle-exclamation' : 'fa-tag'"></i>
                        {{ $t(needsSize ? 'po_size_required' : 'po_switch_size') }}
                    </span>
                    <!-- A long size list as a dropdown: a dozen chips stacked
                         under a narrow product column pushed the line out of view. -->
                    <el-select
                        v-if="siblings.length > SIZE_CHIP_LIMIT"
                        :model-value="item.product_variant_id ? item.pick : ''"
                        size="small"
                        filterable
                        class="pline-size-select"
                        :placeholder="$t('po_pick_size')"
                        @update:model-value="(key) => pickSize(key)"
                    >
                        <el-option
                            v-for="v in siblings"
                            :key="optionKey(v)"
                            :value="optionKey(v)"
                            :label="sizeLabel(v)"
                        >
                            <span dir="ltr">{{ v.variant_label || v.sku }}</span>
                            <small v-if="optionKey(v) !== item.pick && takenBy(v)" class="pline-size-tag"> · #{{ takenBy(v) }}</small>
                            <small v-else-if="flaggedPicks && flaggedPicks.has(optionKey(v))" class="pline-size-tag"> · {{ flaggedShort }}</small>
                        </el-option>
                    </el-select>
                    <template v-else>
                        <button
                            v-for="v in siblings"
                            :key="optionKey(v)"
                            type="button"
                            class="pline-size"
                            :class="{
                                'is-active': optionKey(v) === item.pick,
                                'is-flagged': flaggedPicks && flaggedPicks.has(optionKey(v)),
                                'is-taken': optionKey(v) !== item.pick && takenBy(v),
                            }"
                            :title="[v.sku, $t('po_cost_label', { price: money(v.cost_price || v.price) })].filter(Boolean).join(' · ')"
                            @click="$emit('switch-variant', v)"
                        >
                            <span dir="ltr">{{ v.variant_label || v.sku }}</span>
                            <small v-if="optionKey(v) !== item.pick && takenBy(v)" class="pline-size-tag">#{{ takenBy(v) }}</small>
                            <small v-else-if="flaggedPicks && flaggedPicks.has(optionKey(v))" class="pline-size-tag">{{ flaggedShort }}</small>
                        </button>
                    </template>
                </div>

                <slot name="notes" />
            </div>
        </div>

        <div class="pline-field pline-qty">
            <label>{{ $t('quantity') }}</label>
            <div class="pline-stepper">
                <button v-if="!locked" type="button" :disabled="num(item.quantity) <= 1" :aria-label="$t('decrease')" @click="step(-1)">
                    <i class="fas fa-minus"></i>
                </button>
                <input
                    :value="item.quantity"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    step="1"
                    dir="ltr"
                    :disabled="locked"
                    :aria-label="$t('quantity')"
                    @focus="$event.target.select()"
                    @input="onQty"
                    @blur="onQtyBlur"
                />
                <button v-if="!locked" type="button" :aria-label="$t('increase')" @click="step(1)">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>

        <div class="pline-field pline-cost">
            <label>{{ $t('purchase_cost') }}</label>
            <div class="pline-money" :class="{ 'is-empty': item.unit_price === '' || item.unit_price == null }">
                <input
                    v-model="item.unit_price"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    dir="ltr"
                    placeholder="0.00"
                    :disabled="locked"
                    @focus="$event.target.select()"
                />
                <span class="pline-cur">{{ currency }}</span>
            </div>
            <small v-if="lastCost != null && Math.abs(num(item.unit_price) - num(lastCost)) >= 0.005 && item.unit_price !== ''" class="pline-hint" :class="num(item.unit_price) > num(lastCost) ? 'is-up' : 'is-down'">
                <i class="fas" :class="num(item.unit_price) > num(lastCost) ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                {{ lastCostLabel }} {{ money(lastCost) }}
            </small>
        </div>

        <div class="pline-field pline-sale">
            <label>{{ $t('sale_price') }}</label>
            <div class="pline-money">
                <input
                    v-model="item.sale_price"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    dir="ltr"
                    placeholder="0.00"
                    :disabled="locked"
                    @focus="$event.target.select()"
                />
                <span class="pline-cur">{{ currency }}</span>
            </div>
            <small v-if="margin != null" class="pline-hint" :class="margin < 0 ? 'is-loss' : 'is-margin'">
                {{ margin < 0 ? $t('pf_below_cost') : $t('pf_margin', { pct: margin }) }}
            </small>
        </div>

        <div class="pline-field pline-total">
            <label>{{ $t('po_line_total') }}</label>
            <strong>{{ money(num(item.quantity) * num(item.unit_price)) }}</strong>
        </div>

        <div v-if="!locked" class="pline-actions">
            <button type="button" class="pline-remove" :title="$t('delete')" @click="$emit('remove')">
                <i class="fas fa-trash-can"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
/**
 * One line of a purchase order or goods receipt: the product and its size,
 * quantity, buying cost, the price it will sell at, and the line's value.
 * The line object is the form's own and is edited in place; anything that
 * needs the rest of the document (switching size, removing) is an event.
 */
import { computed } from 'vue';
import { formatMoney } from '@/utils/currency';
import { resolveImageUrl } from '@/utils/productImages';
import { optionKey, baseName } from '@/utils/productPick';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';

const props = defineProps({
    item: { type: Object, required: true },
    index: { type: Number, required: true },
    // The search row the line's pick names (name, image, code, stock).
    row: { type: Object, default: null },
    siblings: { type: Array, default: () => [] },
    needsSize: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    currency: { type: String, default: '' },
    // ok | warn | danger | info — the line's state against the document.
    tone: { type: String, default: 'ok' },
    // (variantRow) => 1-based number of another line holding it, or 0.
    takenBy: { type: Function, default: () => 0 },
    flaggedPicks: { type: Set, default: null },
    flaggedShort: { type: String, default: '' },
    // A reference cost to compare the typed one with (e.g. the request's price).
    lastCost: { type: [Number, String], default: null },
    lastCostLabel: { type: String, default: '' },
});

const emit = defineEmits(['switch-variant', 'remove']);

const SIZE_CHIP_LIMIT = 6;
const sizeLabel = (v) => [v.variant_label || v.sku, v.sku && v.variant_label ? v.sku : ''].filter(Boolean).join(' · ');
const pickSize = (key) => {
    const v = props.siblings.find((row) => optionKey(row) === key);
    if (v) emit('switch-variant', v);
};

const num = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};
const money = (v) => formatMoney(v);
const imageOf = (p) => resolveImageUrl(p?.image_main || p?.image || p?.primary_image_url || p?.image_url || '');

const title = computed(() => (props.row ? baseName(props.row) : '') || props.item.product_name || props.item.name || '—');
const variantLabel = computed(() => props.row?.variant_label || '');
const sku = computed(() => props.row?.sku || props.item.sku || '');
const stock = computed(() => props.row?.stock_quantity ?? props.row?.stock ?? null);

// Mark-up of the sale price over the cost, as a whole percentage.
const margin = computed(() => {
    const cost = num(props.item.unit_price);
    const sale = props.item.sale_price === '' || props.item.sale_price == null ? null : num(props.item.sale_price);
    if (sale == null || cost <= 0 || sale <= 0) return null;
    return Math.round(((sale - cost) / cost) * 100);
});

// Whole units only: the API takes an integer quantity of at least one
// (weight products are counted in grams).
const step = (delta) => {
    props.item.quantity = Math.max(1, Math.floor(num(props.item.quantity)) + delta);
};

const onQty = (e) => {
    const n = Math.floor(Number(e.target.value));
    if (Number.isFinite(n) && n >= 1) props.item.quantity = n;
};

const onQtyBlur = (e) => {
    const n = Math.floor(Number(e.target.value));
    props.item.quantity = Number.isFinite(n) && n >= 1 ? n : Math.max(1, Math.floor(num(props.item.quantity)) || 1);
    e.target.value = props.item.quantity;
};

</script>

<style scoped>
.pline {
    display: grid;
    grid-template-columns: 28px minmax(220px, 1fr) 128px 132px 132px 110px 36px;
    gap: 0.75rem;
    align-items: start;
    padding: 0.8rem 0.9rem;
    border-bottom: 1px solid var(--el-border-color-lighter, #eef0f3);
    background: #fff;
    position: relative;
    transition: background 0.15s;
}

.pline:hover {
    background: #fcfcfd;
}

.pline::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    width: 3px;
    background: transparent;
}

.pline.tone-warn::before {
    background: #f59e0b;
}

.pline.tone-warn {
    background: #fffbeb;
}

.pline.tone-danger::before {
    background: #ef4444;
}

.pline.tone-danger {
    background: #fef2f2;
}

.pline.tone-info::before {
    background: #6366f1;
}

.pline.is-locked {
    grid-template-columns: 28px minmax(220px, 1fr) 110px 120px 120px 110px;
}

.pline-idx {
    margin-top: 0.45rem;
    width: 24px;
    height: 24px;
    border-radius: 999px;
    background: var(--el-fill-color, #f0f2f5);
    color: var(--el-text-color-secondary);
    font-size: 0.72rem;
    font-weight: 700;
    display: grid;
    place-items: center;
}

.pline-product {
    display: flex;
    gap: 0.65rem;
    min-width: 0;
}

.pline-thumb {
    flex-shrink: 0;
}

.pline-info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pline-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pline-name {
    font-size: 0.9rem;
    color: var(--el-text-color-primary);
    line-height: 1.35;
}

.pline-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    font-size: 0.74rem;
    color: var(--el-text-color-secondary);
}

.pline-sku {
    font-family: ui-monospace, monospace;
}

.pline-stock.is-ok {
    color: #047857;
}

.pline-stock.is-out {
    color: #b91c1c;
}

.pline-sizes {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.15rem;
}

.pline-sizes.is-required {
    padding: 0.35rem 0.45rem;
    border-radius: 8px;
    background: #fef3c7;
}

.pline-sizes-label {
    font-size: 0.72rem;
    color: var(--el-text-color-secondary);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pline-sizes.is-required .pline-sizes-label {
    color: #92400e;
    font-weight: 700;
}

.pline-size {
    all: unset;
    cursor: pointer;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.12rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--el-border-color, #dcdfe6);
    background: #fff;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pline-size:hover {
    border-color: var(--el-color-primary);
    color: var(--el-color-primary);
}

.pline-size.is-active {
    background: var(--el-color-primary);
    border-color: var(--el-color-primary);
    color: #fff;
}

.pline-size.is-flagged:not(.is-active) {
    border-color: #a5b4fc;
    background: #eef2ff;
}

.pline-size.is-taken:not(.is-active) {
    border-style: dashed;
    opacity: 0.8;
}

.pline-size-select {
    width: 200px;
    max-width: 100%;
}

.pline-size-tag {
    font-size: 0.64rem;
    opacity: 0.8;
}

.pline-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.pline-field > label {
    display: none;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    text-transform: none;
}

.pline-stepper {
    display: flex;
    align-items: stretch;
    height: 36px;
    border: 1.5px solid var(--el-border-color, #dcdfe6);
    border-radius: 9px;
    overflow: hidden;
    background: #fff;
}

.pline-stepper:focus-within {
    border-color: var(--el-color-primary);
}

.pline-stepper button {
    all: unset;
    cursor: pointer;
    width: 30px;
    display: grid;
    place-items: center;
    color: var(--el-text-color-regular);
    background: var(--el-fill-color-lighter, #fafafa);
    font-size: 0.7rem;
}

.pline-stepper button:hover:not(:disabled) {
    background: color-mix(in srgb, var(--el-color-primary) 10%, white);
    color: var(--el-color-primary);
}

.pline-stepper button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.pline-stepper input,
.pline-money input {
    all: unset;
    flex: 1;
    min-width: 0;
    text-align: center;
    font-weight: 700;
    font-size: 0.9rem;
    font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
}

.pline-stepper input::-webkit-outer-spin-button,
.pline-stepper input::-webkit-inner-spin-button,
.pline-money input::-webkit-outer-spin-button,
.pline-money input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pline-money {
    display: flex;
    align-items: center;
    height: 36px;
    padding-inline: 0.6rem 0.5rem;
    border: 1.5px solid var(--el-border-color, #dcdfe6);
    border-radius: 9px;
    background: #fff;
    gap: 0.35rem;
}

.pline-money:focus-within {
    border-color: var(--el-color-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 15%, transparent);
}

.pline-money.is-empty {
    border-color: #fca5a5;
}

.pline-money input {
    text-align: start;
}

.pline-cur {
    font-size: 0.68rem;
    color: var(--el-text-color-secondary);
    font-weight: 600;
}

.pline-hint {
    font-size: 0.68rem;
    display: inline-flex;
    gap: 0.2rem;
    align-items: center;
}

.pline-hint.is-margin {
    color: #047857;
}

.pline-hint.is-loss {
    color: #b91c1c;
    font-weight: 700;
}

.pline-hint.is-up {
    color: #b45309;
}

.pline-hint.is-down {
    color: #047857;
}

.pline-total strong {
    height: 36px;
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    font-variant-numeric: tabular-nums;
    color: var(--el-text-color-primary);
}

.pline-actions {
    padding-top: 0.15rem;
}

.pline-remove {
    all: unset;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: #9ca3af;
}

.pline-remove:hover {
    background: #fee2e2;
    color: #dc2626;
}

.pline input:disabled {
    color: var(--el-text-color-regular);
    cursor: default;
}

.pline.is-locked .pline-money,
.pline.is-locked .pline-stepper {
    background: var(--el-fill-color-lighter, #fafafa);
    border-color: transparent;
}

/* Narrower: the product takes the full width, its figures on a row below.
   Measured on the lines' own width (the card is a container), since the
   catalogue beside it decides how much room they get. */
@container (max-width: 900px) {
    .pline,
    .pline.is-locked {
        grid-template-columns: 28px repeat(4, minmax(0, 1fr)) 36px;
    }

    .pline-product {
        grid-column: 2 / 6;
    }

    .pline-actions {
        grid-column: 6;
        grid-row: 1;
        padding-top: 0.4rem;
    }

    .pline-qty {
        grid-column: 2;
    }

    .pline-idx {
        grid-row: 1;
    }

    .pline-field > label {
        display: block;
    }

    .pline-actions {
        padding-top: 0.4rem;
    }
}

@container (max-width: 520px) {
    .pline,
    .pline.is-locked {
        grid-template-columns: 24px repeat(2, minmax(0, 1fr)) 32px;
        gap: 0.55rem;
    }

    .pline-product {
        grid-column: 2 / 4;
    }

    .pline-actions {
        grid-column: 4;
    }

    .pline-qty,
    .pline-sale {
        grid-column: 2;
    }

    .pline-cost,
    .pline-total {
        grid-column: 3;
    }
}
</style>
