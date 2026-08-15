<template>
    <el-card shadow="never" class="admin-filter-bar">
        <div class="filter-grid">
            <slot />
        </div>

        <div v-if="$slots.advanced && expanded" class="filter-grid filter-grid--advanced">
            <slot name="advanced" />
        </div>

        <div v-if="$slots.actions || $slots.advanced" class="filter-footer">
            <el-button
                v-if="$slots.advanced"
                text
                type="primary"
                class="advanced-toggle"
                @click="expanded = !expanded"
            >
                {{ expanded ? $t('hide_advanced_filters') : $t('show_advanced_filters') }}
            </el-button>
            <span class="filter-footer__spacer" />
            <slot name="actions" />
        </div>
    </el-card>
</template>

<script setup>
import { ref } from 'vue';

/**
 * The filter row every list and report screen starts with.
 *
 * Filters were laid out by hand with `:md="6"` columns, so a screen with five
 * of them ended in a row of one and a stretch of white space, and every page
 * chose a different width. This lays them on an auto-fitting grid instead: the
 * fields keep a readable minimum width and the row always fills, whatever the
 * count and whatever the window.
 *
 * Put the two or three filters people reach for every day in the default slot,
 * and the rest behind `advanced` so the common case stays one short row.
 */
defineProps({
    /** Narrower cells, for filters that are mostly short selects. */
    dense: { type: Boolean, default: false },
});

const expanded = ref(false);
</script>

<style scoped>
.admin-filter-bar {
    margin-bottom: 1.25rem;
    border: 1px solid #e8eef7;
    border-radius: 14px;
}

.filter-grid {
    display: grid;
    /* The one rule that fixes the ragged rows: as many equal columns as fit,
       never narrower than a legible field. */
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem 1.25rem;
    align-items: end;
}

.filter-grid--advanced {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed #e2e8f0;
}

.filter-footer {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.filter-footer__spacer {
    flex: 1 1 auto;
}

/* Fields are declared as plain blocks by the pages; the grid owns their width
   so nothing has to carry a span. */
:deep(.filter-field) {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

:deep(.filter-field > label) {
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
}

:deep(.filter-field .el-select),
:deep(.filter-field .el-input),
:deep(.filter-field .el-date-editor) {
    width: 100%;
}

@media (max-width: 640px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }

    .filter-footer > :deep(.el-button) {
        flex: 1 1 auto;
    }
}
</style>
