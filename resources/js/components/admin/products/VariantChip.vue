<template>
    <!-- Which size/colour of a product a line is for, set apart from the
         product's name so two lines of the same product read as different
         at a glance. -->
    <span v-if="label" class="variant-chip" :title="sku ? `${label} · ${sku}` : label">
        <i class="fas fa-tag"></i>
        <span class="variant-chip-label">{{ label }}</span>
        <span v-if="sku && showSku" class="variant-chip-sku">{{ sku }}</span>
    </span>
</template>

<script setup>
defineProps({
    label: { type: String, default: '' },
    sku: { type: String, default: '' },
    showSku: { type: Boolean, default: false },
});
</script>

<style scoped>
.variant-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    max-width: 100%;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 12px;
    line-height: 18px;
    white-space: nowrap;
    vertical-align: middle;
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    border: 1px solid var(--el-color-primary-light-7);
}

.variant-chip i {
    font-size: 10px;
    opacity: 0.8;
}

.variant-chip-label {
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 600;
    direction: ltr;
    unicode-bidi: isolate;
}

.variant-chip-sku {
    color: var(--el-text-color-secondary);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
}
</style>
