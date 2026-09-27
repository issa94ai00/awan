<template>
    <el-dialog
        :model-value="modelValue"
        width="min(980px, 96vw)"
        top="5vh"
        class="specs-edit-dialog"
        append-to-body
        :close-on-click-modal="false"
        :before-close="requestClose"
        @opened="onOpened"
    >
        <template #header>
            <div class="sed-head">
                <span class="sed-title">{{ $t('specifications') }}</span>
                <span class="sed-subtitle">
                    {{ productName }}
                    <span v-if="variantLabel" class="sed-variant">{{ variantLabel }}</span>
                </span>
            </div>
        </template>

        <div class="sed-body" @keydown.ctrl.enter.prevent="save" @keydown.meta.enter.prevent="save">
            <div class="sed-editors">
                <template v-if="isVariant">
                    <VariantSpecsEditor
                        ref="ownRef"
                        v-model="ownRows"
                        :title="$t('sed_own_title')"
                        :hint="$t('sed_own_hint')"
                        :empty-text="$t('sed_own_empty')"
                        :label-suggestions="labelSuggestions"
                        :copy-sources="copySources"
                    />
                    <div v-if="siblingCount > 0" class="sed-siblings">
                        <el-checkbox v-model="applyToSiblings">
                            {{ $t('vs_apply_to_siblings', { n: siblingCount }) }}
                        </el-checkbox>
                    </div>
                </template>

                <div class="sed-product">
                    <!-- A description written as prose is not a list this
                         editor can round-trip. It is shown, and only replaced
                         if the user actually writes specifications here. -->
                    <el-alert
                        v-if="proseDescription"
                        type="warning"
                        :closable="false"
                        show-icon
                        class="sed-prose"
                        :title="$t('sed_prose_title')"
                    >
                        <p class="sed-prose-text">{{ proseDescription }}</p>
                    </el-alert>
                    <VariantSpecsEditor
                        ref="productRef"
                        v-model="productRows"
                        strict
                        :title="isVariant ? $t('sed_product_title_all', { n: optionCount }) : $t('sed_product_title')"
                        :hint="$t('sed_product_hint')"
                        :empty-text="$t('sed_product_empty')"
                        :label-suggestions="labelSuggestions"
                    />
                </div>
                <p v-if="serverError" class="sed-error">{{ serverError }}</p>
            </div>

            <aside class="sed-preview" :aria-label="$t('sed_preview_title')">
                <div class="sed-preview-head">
                    <span>{{ $t('sed_preview_title') }}</span>
                    <span class="sed-preview-note">{{ $t('sed_preview_note') }}</span>
                </div>
                <table v-if="previewRows.length" class="sed-preview-table">
                    <tbody>
                        <tr v-for="(row, idx) in previewRows" :key="idx" :class="{ own: row.own }">
                            <th v-if="row.label" scope="row">{{ row.label }}</th>
                            <td :colspan="row.label ? 1 : 2">
                                <span class="sed-preview-value">{{ row.value }}</span>
                                <span v-if="row.own" class="sed-own-tag">{{ row.replaces ? $t('sed_tag_replaces') : $t('sed_tag_own') }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="sed-preview-empty">{{ $t('sed_preview_empty') }}</p>
            </aside>
        </div>

        <template #footer>
            <div class="sed-footer">
                <span class="sed-shortcut">{{ $t('sed_shortcut') }}</span>
                <el-button @click="requestClose()">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!dirty || invalid" @click="save">
                    {{ $t('common.save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
/**
 * Edits one price-list row's specifications where they are read: the row's
 * own details (a variant's `specs`) and the product's, which live in its
 * description as "Label: value • …" and show under every option. A live
 * preview shows the merged table the price list and the storefront draw.
 *
 * The parent does the saving: `save` hands it { productRows, ownRows,
 * applyToSiblings }, with null for a list that did not change.
 */
import { ref, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessageBox } from 'element-plus';
import VariantSpecsEditor from '@/components/admin/products/VariantSpecsEditor.vue';
import { parseDescriptionSpecs, mergeSpecs } from '@/utils/productSpecs';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    productName: { type: String, default: '' },
    variantLabel: { type: String, default: '' },
    isVariant: { type: Boolean, default: false },
    description: { type: String, default: '' },
    ownSpecs: { type: Array, default: () => [] },
    optionCount: { type: Number, default: 0 },
    siblingCount: { type: Number, default: 0 },
    labelSuggestions: { type: Array, default: () => [] },
    copySources: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    serverError: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'save']);
const { t } = useI18n();

const clean = (list) => (Array.isArray(list) ? list : [])
    .map((r) => ({ label: String(r?.label ?? '').trim(), value: String(r?.value ?? '').trim() }))
    .filter((r) => r.value !== '');
const keyOf = (list) => clean(list).map((r) => `${r.label}:${r.value}`).join('¦');

const productRows = ref([]);
const ownRows = ref([]);
const applyToSiblings = ref(false);
const productBaseline = ref('');
const ownBaseline = ref('');
const productRef = ref(null);
const ownRef = ref(null);

const parsedDescription = computed(() => parseDescriptionSpecs(props.description));
const proseDescription = computed(() => {
    const text = String(props.description || '').trim();
    return text && !parsedDescription.value.length ? text : '';
});

// Seeded each time the dialog opens, from whatever row it was opened on.
watch(() => props.modelValue, (open) => {
    if (!open) return;
    productRows.value = parsedDescription.value.map((r) => ({ ...r }));
    ownRows.value = clean(props.ownSpecs);
    productBaseline.value = keyOf(productRows.value);
    ownBaseline.value = keyOf(ownRows.value);
    applyToSiblings.value = false;
}, { immediate: true });

const productChanged = computed(() => keyOf(productRows.value) !== productBaseline.value);
const ownChanged = computed(() => props.isVariant && keyOf(ownRows.value) !== ownBaseline.value);
const dirty = computed(() => productChanged.value || ownChanged.value || applyToSiblings.value);
const invalid = computed(() => !!productRef.value?.hasProblems?.());

// What the row will read as once saved, each of its own lines marked so the
// user can see which product line it stands in for.
const previewRows = computed(() => {
    const base = clean(productRows.value).filter((r) => r.label);
    const own = props.isVariant ? clean(ownRows.value) : [];
    const baseLabels = new Set(base.map((r) => r.label));
    const ownLabels = new Set(own.map((r) => r.label).filter(Boolean));
    return mergeSpecs(base, own).map((r) => ({
        ...r,
        own: !r.label || ownLabels.has(r.label),
        replaces: !!r.label && ownLabels.has(r.label) && baseLabels.has(r.label),
    }));
});

function onOpened() {
    // Straight into typing: the first empty list's add button would be one
    // more click, so focus lands on the first field of the list that matters.
    nextTick(() => {
        const root = document.querySelector('.specs-edit-dialog .variant-specs input');
        root?.focus();
    });
}

function save() {
    if (!dirty.value || invalid.value || props.saving) return;
    emit('save', {
        productRows: productChanged.value ? clean(productRows.value) : null,
        ownRows: ownChanged.value || applyToSiblings.value ? clean(ownRows.value) : null,
        applyToSiblings: props.isVariant && applyToSiblings.value,
    });
}

// Every way out (the X, Escape, Cancel) comes through here; the parent owns
// `modelValue`, so closing is asking it to, once any unsaved edit is let go.
async function requestClose() {
    if (dirty.value && !props.saving) {
        try {
            await ElMessageBox.confirm(t('sed_discard_body'), t('sed_discard_title'), {
                confirmButtonText: t('sed_discard_confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning',
            });
        } catch {
            return;
        }
    }
    emit('update:modelValue', false);
}
</script>

<style scoped>
.sed-head {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sed-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}

.sed-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.sed-variant {
    padding: 1px 8px;
    border-radius: 10px;
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-weight: 600;
}

.sed-body {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.sed-editors {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}

.sed-siblings {
    margin-top: -4px;
}

.sed-product {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sed-prose-text {
    margin: 4px 0 0;
    white-space: pre-wrap;
    max-height: 90px;
    overflow: auto;
}

.sed-error {
    margin: 0;
    font-size: 13px;
    color: var(--el-color-danger);
}

/* The preview stays in view while a long list is edited beside it. */
.sed-preview {
    position: sticky;
    top: 0;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;
    padding: 12px;
    background: var(--el-fill-color-light);
}

.sed-preview-head {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}

.sed-preview-note {
    font-size: 12px;
    font-weight: 400;
    color: var(--el-text-color-secondary);
}

.sed-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background: var(--el-fill-color-blank);
    border: 1px solid var(--el-border-color);
}

.sed-preview-table th,
.sed-preview-table td {
    padding: 5px 8px;
    border-bottom: 1px solid var(--el-border-color);
    text-align: start;
    vertical-align: middle;
}

.sed-preview-table tr:last-child th,
.sed-preview-table tr:last-child td {
    border-bottom: none;
}

.sed-preview-table th {
    width: 42%;
    font-weight: 600;
    color: var(--el-text-color-regular);
    background: var(--el-fill-color);
    border-inline-end: 1px solid var(--el-border-color);
}

.sed-preview-table td {
    font-weight: 600;
    color: var(--el-text-color-primary);
}

.sed-preview-value {
    unicode-bidi: plaintext;
}

.sed-preview-table tr.own td {
    background: var(--el-color-primary-light-9);
}

.sed-own-tag {
    margin-inline-start: 6px;
    padding: 0 6px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    color: var(--el-color-primary);
    border: 1px solid var(--el-color-primary-light-5);
}

.sed-preview-empty {
    margin: 0;
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.sed-footer {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sed-shortcut {
    flex: 1;
    text-align: start;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

@media (max-width: 760px) {
    .sed-body {
        grid-template-columns: 1fr;
    }

    .sed-preview {
        position: static;
        order: -1;
    }

    .sed-shortcut {
        display: none;
    }
}
</style>
