<template>
    <!-- Everything that tells a sold variant apart: its size, colour and
         material by name, the price list's extra details, and optionally
         its own SKU and barcode. -->
    <div v-if="hasContent" class="variant-details" :class="{ 'is-print': print }">
        <div v-if="attributes.length" class="vd-attrs">
            <span v-for="attr in attributes" :key="attr.key" class="vd-attr">
                <span class="vd-attr-label">{{ attr.label }}</span>
                <span class="vd-attr-value">{{ attr.value }}</span>
            </span>
        </div>
        <ul v-if="specs.length" class="vd-specs">
            <li v-for="(spec, index) in specs" :key="index">
                <span v-if="spec.label" class="vd-spec-label">{{ spec.label }}:</span>
                <span class="vd-spec-value">{{ spec.value }}</span>
            </li>
        </ul>
        <div v-if="showCodes && (variant.sku || variant.barcode)" class="vd-codes">
            <span v-if="variant.sku" class="vd-code"><i class="fas fa-hashtag"></i><code>{{ variant.sku }}</code></span>
            <span v-if="variant.barcode" class="vd-code"><i class="fas fa-barcode"></i><code>{{ variant.barcode }}</code></span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    // A line's `variant` relation: size, color, material, specs, sku, barcode.
    variant: { type: Object, default: null },
    showCodes: { type: Boolean, default: false },
    // Smaller, ink-friendly styling for printed documents.
    print: { type: Boolean, default: false },
});

const { t } = useI18n();

const clean = (value) => (value == null ? '' : String(value).trim());

const attributes = computed(() => {
    const v = props.variant || {};
    return [
        { key: 'size', label: t('inv_var_size'), value: clean(v.size) },
        { key: 'color', label: t('inv_var_color'), value: clean(v.color) },
        { key: 'material', label: t('inv_var_material'), value: clean(v.material) },
    ].filter((attr) => attr.value);
});

// Price lists often repeat the size as a detail ("القياس: 2\""); skip rows
// that only restate an attribute already shown.
const specs = computed(() => {
    const shown = new Set(attributes.value.map((attr) => attr.value));
    return (Array.isArray(props.variant?.specs) ? props.variant.specs : [])
        .map((spec) => ({ label: clean(spec?.label), value: clean(spec?.value) }))
        .filter((spec) => spec.value && !shown.has(spec.value));
});

const hasContent = computed(() => attributes.value.length > 0
    || specs.value.length > 0
    || (props.showCodes && (props.variant?.sku || props.variant?.barcode)));
</script>

<style scoped>
.variant-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    line-height: 1.5;
}

.vd-attrs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.vd-attr {
    display: inline-flex;
    align-items: center;
    border: 1px solid var(--el-color-primary-light-7);
    border-radius: 6px;
    overflow: hidden;
    white-space: nowrap;
}

.vd-attr-label {
    padding: 0 6px;
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-weight: 600;
}

.vd-attr-value {
    padding: 0 6px;
    font-weight: 700;
    color: var(--el-text-color-primary);
    /* Sizes like 1/2" read left to right inside Arabic text. */
    unicode-bidi: isolate;
}

.vd-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 2px 12px;
    margin: 0;
    padding: 0;
    list-style: none;
    color: var(--el-text-color-regular);
}

.vd-specs li {
    unicode-bidi: plaintext;
}

.vd-spec-label {
    color: var(--el-text-color-secondary);
    margin-inline-end: 3px;
}

.vd-codes {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    color: var(--el-text-color-secondary);
}

.vd-code {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.vd-code i {
    font-size: 10px;
}

.vd-code code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
    direction: ltr;
    unicode-bidi: isolate;
}

/* Printed sheet: smaller, no tinted fills that waste ink. */
.variant-details.is-print {
    font-size: 9.5px;
    gap: 2px;
}

.is-print .vd-attr {
    border-color: #cbd5e1;
    border-radius: 3px;
}

.is-print .vd-attr-label {
    background: #f1f5f9;
    color: #475569;
    padding: 0 4px;
}

.is-print .vd-attr-value {
    color: #0f172a;
    padding: 0 4px;
}

.is-print .vd-specs {
    color: #334155;
    gap: 0 10px;
}

.is-print .vd-spec-label {
    color: #64748b;
}
</style>
