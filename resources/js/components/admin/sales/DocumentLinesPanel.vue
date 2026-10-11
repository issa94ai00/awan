<template>
    <!-- A document's lines inside an expanded list row — what each line is
         for, with a variant line's size, colour, material, specs and codes.
         Several sizes of one product sit under one product heading. -->
    <div class="lines-panel">
        <div v-if="state.loading" class="lines-status">
            <i class="fas fa-spinner fa-spin"></i> {{ $t('inv_lines_loading') }}
        </div>
        <div v-else-if="state.error" class="lines-status is-error">
            <span>{{ $t('inv_lines_failed') }}</span>
            <el-button size="small" text type="primary" @click="load(true)">{{ $t('cat_admin_retry') }}</el-button>
        </div>
        <template v-else-if="currentLines">
            <div v-if="!currentLines.length" class="lines-status">{{ $t('inv_lines_empty') }}</div>
            <table v-else class="lines-table">
                <thead>
                    <tr>
                        <th class="col-idx">#</th>
                        <th>{{ $t('inv_line_product') }}</th>
                        <th class="col-num">{{ $t('inv_line_qty') }}</th>
                        <th v-if="hasReceived" class="col-num">{{ $t('inv_line_received') }}</th>
                        <th class="col-num">{{ $t('inv_line_price') }}</th>
                        <th v-if="hasDiscount" class="col-num">{{ $t('inv_line_discount') }}</th>
                        <th class="col-num">{{ $t('inv_line_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in rows" :key="row.key">
                        <!-- One product ordered in several sizes. -->
                        <tr v-if="row.type === 'group'" class="group-row">
                            <td class="col-idx"><i class="fas fa-layer-group"></i></td>
                            <td :colspan="columnCount - 1">
                                <div class="line-product">
                                    <EntityImage :src="resolveImageUrl(row.image)" type="product" :size="36" shape="square" />
                                    <div class="group-head">
                                        <span class="line-name">{{ row.name || '—' }}</span>
                                        <span class="group-meta">
                                            {{ $t('inv_group_sizes', { count: row.count }) }} · ×{{ row.quantity }}
                                            · <strong>{{ formatCurrency(row.total) }}</strong>
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr v-else :class="{ 'is-nested': row.nested }">
                            <td class="col-idx">{{ row.number }}</td>
                            <td>
                                <div class="line-product">
                                    <EntityImage
                                        v-if="!row.nested"
                                        :src="resolveImageUrl(row.line.image)"
                                        type="product"
                                        :size="40"
                                        shape="square"
                                    />
                                    <div class="line-product-text">
                                        <span v-if="!row.nested" class="line-name">{{ row.line.name || '—' }}</span>
                                        <span v-if="!row.line.variant && row.line.sku" class="line-sku" dir="ltr">{{ row.line.sku }}</span>
                                        <VariantDetails v-if="row.line.variant" :variant="row.line.variant" show-codes />
                                        <span v-else-if="row.line.variantId" class="line-muted">{{ $t('inv_variant_removed') }}</span>
                                        <span v-else-if="row.nested" class="line-muted">{{ $t('po_base_product') }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="col-num">
                                <strong>{{ row.line.quantity }}</strong>
                                <span v-if="row.line.unit" class="line-muted"> {{ row.line.unit }}</span>
                            </td>
                            <td v-if="hasReceived" class="col-num">
                                <template v-if="row.line.received != null">
                                    <span v-if="toNumber(row.line.received) >= toNumber(row.line.quantity)" class="received-ok">
                                        <i class="fas fa-check"></i> {{ row.line.received }}
                                    </span>
                                    <span v-else class="received-short">
                                        {{ row.line.received }} / {{ row.line.quantity }}
                                        <small>{{ $t('inv_line_short', { count: toNumber(row.line.quantity) - toNumber(row.line.received) }) }}</small>
                                    </span>
                                </template>
                                <span v-else class="line-muted">—</span>
                            </td>
                            <td class="col-num amount">{{ formatCurrency(row.line.unitPrice) }}</td>
                            <td v-if="hasDiscount" class="col-num amount discount">
                                {{ toNumber(row.line.discount) ? `− ${formatCurrency(row.line.discount)}` : '—' }}
                            </td>
                            <td class="col-num amount"><strong>{{ formatCurrency(row.line.total) }}</strong></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>
</template>

<script>
// Lines already fetched, by cacheKey, so collapsing and reopening a row (which
// remounts this panel) does not refetch.
const linesCache = new Map();
</script>

<script setup>
import { computed, onMounted, reactive } from 'vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantDetails from '@/components/admin/products/VariantDetails.vue';
import { formatCurrency } from '@/utils/sales';
import { resolveImageUrl } from '@/utils/productImages';

/**
 * Each line in one shape:
 * { id, productId, image, name, sku, variant, variantId, quantity, unit,
 *   unitPrice, discount, total, received }
 * `received` is optional: when any line has one, a received column shows.
 */
const props = defineProps({
    // Lines already in hand (the list row carries them) — shown as given.
    lines: { type: Array, default: null },
    // Otherwise fetched once with `loader` and cached under `cacheKey`.
    cacheKey: { type: String, default: '' },
    loader: { type: Function, default: null },
});

const toNumber = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};

const state = reactive({
    loading: false,
    error: false,
    lines: props.cacheKey ? linesCache.get(props.cacheKey) || null : null,
});

const currentLines = computed(() => props.lines || state.lines);

const hasDiscount = computed(() => (currentLines.value || []).some((line) => toNumber(line.discount) > 0));
const hasReceived = computed(() => (currentLines.value || []).some((line) => line.received != null));
const columnCount = computed(() => 5 + (hasReceived.value ? 1 : 0) + (hasDiscount.value ? 1 : 0));

// Lines grouped by product in order of first appearance; a product with more
// than one line gets a heading row and its lines nest beneath it.
const rows = computed(() => {
    const groups = new Map();
    (currentLines.value || []).forEach((line, index) => {
        const key = line.productId ? `p${line.productId}` : `l${line.id ?? index}`;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(line);
    });

    const out = [];
    let number = 0;
    groups.forEach((lines, key) => {
        const nested = lines.length > 1;
        if (nested) {
            out.push({
                type: 'group',
                key: `g-${key}`,
                name: lines[0].name,
                image: lines[0].image,
                count: lines.length,
                quantity: lines.reduce((sum, line) => sum + toNumber(line.quantity), 0),
                total: lines.reduce((sum, line) => sum + toNumber(line.total), 0),
            });
        }
        lines.forEach((line, index) => {
            number += 1;
            out.push({ type: 'line', key: `l-${key}-${line.id ?? index}`, line, nested, number });
        });
    });
    return out;
});

const load = async (force = false) => {
    if (props.lines || !props.loader) return;
    if (state.loading || (state.lines && !force)) return;
    state.loading = true;
    state.error = false;
    try {
        state.lines = await props.loader();
        if (props.cacheKey) linesCache.set(props.cacheKey, state.lines);
    } catch (error) {
        console.error('Failed to load document lines:', error);
        state.error = true;
    } finally {
        state.loading = false;
    }
};

onMounted(() => load());
</script>

<style scoped>
.lines-panel { padding: 0.5rem 1rem 0.85rem; background: #f8fafc; overflow-x: auto; }
.lines-status { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 0.25rem; color: #64748b; font-size: 0.85rem; }
.lines-status.is-error { color: #b91c1c; }
.lines-table { width: 100%; min-width: 620px; border-collapse: separate; border-spacing: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; font-size: 0.85rem; }
.lines-table th { background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.75rem; text-align: start; padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; }
.lines-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.lines-table tbody tr:last-child td { border-bottom: none; }
.lines-table .col-idx { width: 32px; text-align: center; color: #94a3b8; font-weight: 700; }
.lines-table .col-num { text-align: end; white-space: nowrap; }
.line-product { display: flex; align-items: flex-start; gap: 0.65rem; }
.line-product-text { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.line-name { font-weight: 700; color: #0f172a; unicode-bidi: plaintext; }
.line-sku { font-family: ui-monospace, monospace; font-size: 0.75rem; color: #64748b; }
.line-muted { font-size: 0.78rem; color: #64748b; }
.amount { font-variant-numeric: tabular-nums; }
.amount.discount { color: #dc2626; }

/* A product heading over its sizes, and the sizes indented beneath it. */
.group-row td { background: #f8fafc; border-bottom-color: #e2e8f0; padding-block: 0.5rem; }
.group-row .col-idx { color: #64748b; }
.group-head { display: flex; flex-direction: column; gap: 0.15rem; justify-content: center; min-height: 36px; }
.group-meta { font-size: 0.78rem; color: #64748b; font-variant-numeric: tabular-nums; }
.group-meta strong { color: #0f172a; }
.is-nested .line-product { padding-inline-start: 1.1rem; border-inline-start: 2px solid #e2e8f0; margin-inline-start: 0.6rem; }

.received-ok { color: #15803d; font-weight: 700; }
.received-ok i { font-size: 0.7rem; }
.received-short { display: inline-flex; flex-direction: column; align-items: flex-end; color: #b45309; font-weight: 700; }
.received-short small { font-weight: 600; font-size: 0.72rem; }
</style>
