<template>
    <!-- A document's lines inside an expanded list row — what each line sold,
         with a variant line's size, colour, material, specs and codes. -->
    <div class="lines-panel">
        <div v-if="state.loading" class="lines-status">
            <i class="fas fa-spinner fa-spin"></i> {{ $t('inv_lines_loading') }}
        </div>
        <div v-else-if="state.error" class="lines-status is-error">
            <span>{{ $t('inv_lines_failed') }}</span>
            <el-button size="small" text type="primary" @click="load(true)">{{ $t('cat_admin_retry') }}</el-button>
        </div>
        <template v-else-if="state.lines">
            <div v-if="!state.lines.length" class="lines-status">{{ $t('inv_lines_empty') }}</div>
            <table v-else class="lines-table">
                <thead>
                    <tr>
                        <th class="col-idx">#</th>
                        <th>{{ $t('inv_line_product') }}</th>
                        <th class="col-num">{{ $t('inv_line_qty') }}</th>
                        <th class="col-num">{{ $t('inv_line_price') }}</th>
                        <th v-if="hasDiscount" class="col-num">{{ $t('inv_line_discount') }}</th>
                        <th class="col-num">{{ $t('inv_line_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, idx) in state.lines" :key="line.id || idx">
                        <td class="col-idx">{{ idx + 1 }}</td>
                        <td>
                            <div class="line-product">
                                <EntityImage :src="line.image" type="product" :size="40" shape="square" />
                                <div class="line-product-text">
                                    <span class="line-name">{{ line.name || '—' }}</span>
                                    <span v-if="!line.variant && line.sku" class="line-sku" dir="ltr">{{ line.sku }}</span>
                                    <VariantDetails v-if="line.variant" :variant="line.variant" show-codes />
                                    <span v-else-if="line.variantId" class="line-muted">{{ $t('inv_variant_removed') }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="col-num">
                            <strong>{{ line.quantity }}</strong>
                            <span v-if="line.unit" class="line-muted"> {{ line.unit }}</span>
                        </td>
                        <td class="col-num amount">{{ formatCurrency(line.unitPrice) }}</td>
                        <td v-if="hasDiscount" class="col-num amount discount">
                            {{ toNumber(line.discount) ? `− ${formatCurrency(line.discount)}` : '—' }}
                        </td>
                        <td class="col-num amount"><strong>{{ formatCurrency(line.total) }}</strong></td>
                    </tr>
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

const props = defineProps({
    // Unique per document, e.g. "invoice:12".
    cacheKey: { type: String, required: true },
    /**
     * Resolves to the lines in one shape:
     * { id, image, name, sku, variant, variantId, quantity, unit, unitPrice, discount, total }
     */
    loader: { type: Function, required: true },
});

const toNumber = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};

const state = reactive({ loading: false, error: false, lines: linesCache.get(props.cacheKey) || null });

const hasDiscount = computed(() => (state.lines || []).some((line) => toNumber(line.discount) > 0));

const load = async (force = false) => {
    if (state.loading || (state.lines && !force)) return;
    state.loading = true;
    state.error = false;
    try {
        state.lines = await props.loader();
        linesCache.set(props.cacheKey, state.lines);
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
</style>
