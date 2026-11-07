<template>
    <div v-if="Array.isArray(stock)" class="wh-stock" :class="{ 'is-compact': compact }">
        <span v-if="!stock.length" class="wh-stock-none">
            <i class="fas fa-warehouse"></i> {{ t('sinv_not_in_any_warehouse') }}
        </span>

        <template v-else>
            <span
                v-for="w in visible"
                :key="w.warehouse_id"
                class="wh-stock-chip"
                :class="{ 'is-default': w.warehouse_id === defaultId }"
                :title="`${w.name}: ${w.available}`"
            >
                <i class="fas" :class="w.warehouse_id === defaultId ? 'fa-star' : 'fa-warehouse'"></i>
                <span class="wh-stock-name">{{ w.name }}</span>
                <strong class="wh-stock-qty">{{ w.available }}</strong>
            </span>
            <span v-if="hidden.length" class="wh-stock-more" :title="hiddenTitle">+{{ hidden.length }}</span>
            <span v-if="defaultId && !inDefault" class="wh-stock-hint">
                {{ t('sinv_not_in_default_warehouse') }}
            </span>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Where a search result can ship from: one chip per warehouse holding sellable
 * stock, largest first, the invoice's default warehouse starred. Renders
 * nothing when the breakdown was not requested (stock undefined).
 */
const props = defineProps({
    // [{ warehouse_id, name, available }] from /pos/products/lookup?with_warehouses=1
    stock: { type: Array, default: undefined },
    defaultId: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
});

const { t: i18nT } = useI18n();
const t = (key) => {
    const v = i18nT(key);
    return v === key ? '' : v;
};

const limit = computed(() => (props.compact ? 2 : 4));

// Default warehouse first, then the rest by quantity (API order).
const ordered = computed(() => {
    const list = [...(props.stock || [])];
    const i = list.findIndex((w) => w.warehouse_id === props.defaultId);
    if (i > 0) list.unshift(...list.splice(i, 1));
    return list;
});

const visible = computed(() => ordered.value.slice(0, limit.value));
const hidden = computed(() => ordered.value.slice(limit.value));
const hiddenTitle = computed(() => hidden.value.map((w) => `${w.name}: ${w.available}`).join('\n'));
const inDefault = computed(() => (props.stock || []).some((w) => w.warehouse_id === props.defaultId));
</script>

<style scoped>
.wh-stock {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem;
    margin: 0.3rem 0;
    font-size: 0.72rem;
}

.wh-stock-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 100%;
    padding: 1px 7px;
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    line-height: 1.6;
}

.wh-stock-chip i {
    font-size: 0.62rem;
    opacity: 0.75;
}

.wh-stock-chip.is-default {
    border-color: #86efac;
    background: #f0fdf4;
    color: #166534;
}

.wh-stock-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
}

.wh-stock-qty {
    font-variant-numeric: tabular-nums;
}

.wh-stock-more {
    padding: 1px 6px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-weight: 700;
    cursor: help;
}

.wh-stock-hint {
    color: #b45309;
    font-weight: 600;
}

.wh-stock-none {
    color: #b91c1c;
    font-weight: 600;
}

.is-compact {
    margin: 0.2rem 0;
    font-size: 0.68rem;
}

.is-compact .wh-stock-name {
    max-width: 80px;
}
</style>
