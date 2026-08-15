<template>
    <div class="admin-stat-grid" :style="gridStyle">
        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue';

/**
 * The row of KPI cards that opens most admin screens.
 *
 * Written with `:md="6"` columns per page, six cards became four-and-two and
 * five became four-and-one, with the orphans stretched to half the screen. A
 * grid with a minimum card width wraps evenly instead, and the cards keep the
 * same height whatever the label length.
 */
const props = defineProps({
    /** Smallest card width before wrapping; raise it for long figures. */
    min: { type: Number, default: 210 },
});

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(auto-fit, minmax(${props.min}px, 1fr))`,
}));
</script>

<style scoped>
.admin-stat-grid {
    display: grid;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

/* Equal heights whatever a card holds, so a two-line label cannot leave a
   neighbour floating half a card short. */
.admin-stat-grid > :deep(*) {
    height: 100%;
}

.admin-stat-grid > :deep(.el-card) {
    border-radius: 14px;
}

.admin-stat-grid > :deep(.el-card .el-card__body) {
    height: 100%;
}
</style>
