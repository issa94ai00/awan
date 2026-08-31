<template>
    <div class="bar-list">
        <div v-if="items.length" class="bar-list__rows">
            <div v-for="(item, index) in items" :key="`${item.label}-${index}`" class="bar-row">
                <span class="bar-row__label" :class="{ 'bar-row__label--ltr': ltr }" :title="item.label">
                    {{ item.label }}
                </span>
                <el-progress
                    class="bar-row__progress"
                    :percentage="item.percentage"
                    :stroke-width="8"
                    :show-text="false"
                    :color="color"
                />
                <span class="bar-row__value">{{ formatNumber(item.value) }}</span>
            </div>
        </div>

        <el-empty v-else :description="$t('analytics.no_data_for_period')" :image-size="56">
            <template v-if="emptyIcon" #image>
                <i :class="emptyIcon" class="bar-list__empty-icon"></i>
            </template>
        </el-empty>
    </div>
</template>

<script setup>
import { useCurrency } from '@/Composables/useCurrency';

/**
 * A ranked list of label/value rows, each drawn as a bar against the row's own
 * share of the list's maximum — the reading a browser or top-pages breakdown
 * wants, without the legend and hover chrome a full chart carries for what is
 * really just five or six numbers in order.
 */
defineProps({
    /** `{ label: string, value: number, percentage: number }[]`, pre-sorted. */
    items: { type: Array, default: () => [] },
    /** FontAwesome classes for the empty-state icon, e.g. "fab fa-chrome". */
    emptyIcon: { type: String, default: '' },
    /** Right-aligns labels that are URLs/hostnames so they read left-to-right in RTL. */
    ltr: { type: Boolean, default: false },
    color: { type: String, default: '#667eea' },
});

const { formatNumber } = useCurrency();
</script>

<style scoped>
.bar-list__rows {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.bar-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.bar-row__label {
    flex: 0 0 auto;
    width: 35%;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.85rem;
    color: #475569;
}

.bar-row__label--ltr {
    direction: ltr;
    text-align: left;
}

.bar-row__progress {
    flex: 1 1 auto;
    min-width: 0;
}

.bar-row__value {
    flex: 0 0 auto;
    min-width: 2.5rem;
    text-align: end;
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2d3d;
}

.bar-list__empty-icon {
    font-size: 2.25rem;
    color: #cbd5e0;
}

@media (max-width: 480px) {
    .bar-row__label {
        width: 45%;
    }
}
</style>
