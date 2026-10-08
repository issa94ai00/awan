<template>
    <div class="variant-specs">
        <div class="variant-specs-head">
            <span class="variant-specs-title">
                {{ title || $t('vs_details_title') }}
                <span v-if="rows.length" class="variant-specs-count">{{ rows.length }}</span>
            </span>
            <div class="variant-specs-tools">
                <el-dropdown v-if="sources.length" trigger="click" @command="copyFrom">
                    <el-button size="small" :icon="CopyDocument">{{ $t('vs_copy_from') }}</el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item v-for="src in sources" :key="src.id" :command="src.id">
                                {{ src.label }}
                                <span class="variant-specs-src-count">({{ src.specs.length }})</span>
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <el-button size="small" :icon="DocumentAdd" @click="pasteOpen = !pasteOpen">
                    {{ $t('vs_paste') }}
                </el-button>
            </div>
        </div>

        <p v-if="hint" class="variant-specs-hint variant-specs-lead">{{ hint }}</p>

        <div v-if="pasteOpen" class="variant-specs-paste">
            <el-input
                v-model="pasteText"
                type="textarea"
                :rows="3"
                :placeholder="$t('vs_paste_placeholder')"
            />
            <div class="variant-specs-paste-actions">
                <span class="variant-specs-hint">{{ $t('vs_paste_help') }}</span>
                <el-button size="small" @click="pasteOpen = false">{{ $t('common.cancel') }}</el-button>
                <el-button size="small" type="primary" :disabled="!pasteText.trim()" @click="applyPaste">
                    {{ $t('vs_paste_apply') }}
                </el-button>
            </div>
        </div>

        <div v-if="rows.length" class="variant-specs-rows">
            <div v-for="(row, idx) in rows" :key="row.key" class="variant-specs-row">
                <el-autocomplete
                    :ref="(el) => setLabelRef(row.key, el)"
                    v-model="row.label"
                    class="variant-specs-label"
                    :class="{ 'is-invalid': labelProblem(row) }"
                    :title="labelProblem(row) || undefined"
                    :fetch-suggestions="suggestLabels"
                    :placeholder="$t('vs_label_placeholder')"
                    :trigger-on-focus="true"
                    clearable
                    @input="emitRows"
                    @select="emitRows"
                />
                <el-input
                    v-model="row.value"
                    class="variant-specs-value"
                    :class="{ 'is-invalid': valueProblem(row) }"
                    :title="valueProblem(row) || undefined"
                    :placeholder="$t('vs_value_placeholder')"
                    @input="emitRows"
                    @keydown.enter.prevent="addRow(idx + 1)"
                />
                <div class="variant-specs-row-actions">
                    <el-button
                        text
                        size="small"
                        :icon="ArrowUp"
                        :disabled="idx === 0"
                        :aria-label="$t('vs_move_up')"
                        @click="move(idx, -1)"
                    />
                    <el-button
                        text
                        size="small"
                        :icon="ArrowDown"
                        :disabled="idx === rows.length - 1"
                        :aria-label="$t('vs_move_down')"
                        @click="move(idx, 1)"
                    />
                    <el-button
                        text
                        size="small"
                        type="danger"
                        :icon="Delete"
                        :aria-label="$t('vs_remove_row')"
                        @click="removeRow(idx)"
                    />
                </div>
            </div>
        </div>
        <p v-else class="variant-specs-empty">{{ emptyText || $t('vs_empty') }}</p>
        <p v-if="firstProblem" class="variant-specs-problem">{{ firstProblem }}</p>

        <el-button class="variant-specs-add" size="small" :icon="Plus" @click="addRow()">
            {{ $t('vs_add_row') }}
        </el-button>
    </div>
</template>

<script setup>
/**
 * An ordered list of label/value details for one variant — "Power: 750W",
 * "Disc: 115mm". Rows can be typed, reordered, copied from a sibling variant,
 * or pasted in one go from a catalogue line ("Power: 750W • Disc: 115mm").
 *
 * v-model is the plain [{label, value}] array the API stores; empty rows are
 * kept while editing and dropped by the server.
 */
import { ref, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, Delete, ArrowUp, ArrowDown, CopyDocument, DocumentAdd } from '@element-plus/icons-vue';
import { matchesSearch } from '@/utils/search';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    // Labels used elsewhere in the catalogue, offered while typing a label.
    labelSuggestions: { type: Array, default: () => [] },
    // Other variants to copy from: [{ id, label, specs }].
    copySources: { type: Array, default: () => [] },
    // Heading, one-line explanation and empty-state text, when the default
    // "Variant details" wording does not fit (the product-wide list).
    title: { type: String, default: '' },
    hint: { type: String, default: '' },
    emptyText: { type: String, default: '' },
    // For a list stored as description text: neither half may hold the
    // characters that text is split on. A line may go without a label — it is
    // kept as a plain line, as supplier price lists write them.
    strict: { type: Boolean, default: false },
});
const { t } = useI18n();
const emit = defineEmits(['update:modelValue']);

let nextKey = 0;
const toRows = (list) => (Array.isArray(list) ? list : []).map((r) => ({
    key: nextKey++,
    label: String(r?.label ?? ''),
    value: String(r?.value ?? ''),
}));

const rows = ref(toRows(props.modelValue));

// Re-seed when the parent swaps in another variant's list, but not on our own
// echo — that would reset the keys and drop focus mid-typing.
let lastEmitted = null;
watch(() => props.modelValue, (val) => {
    if (val === lastEmitted) return;
    rows.value = toRows(val);
});

function emitRows() {
    lastEmitted = rows.value.map(({ label, value }) => ({ label, value }));
    emit('update:modelValue', lastEmitted);
}

const sources = computed(() => props.copySources.filter((s) => Array.isArray(s.specs) && s.specs.length));

// New rows take the cursor, so "Enter, type, Enter, type" fills a list.
const labelRefs = new Map();
function setLabelRef(key, el) {
    if (el) labelRefs.set(key, el);
    else labelRefs.delete(key);
}

function addRow(at = rows.value.length) {
    const key = nextKey++;
    rows.value.splice(at, 0, { key, label: '', value: '' });
    emitRows();
    nextTick(() => labelRefs.get(key)?.focus?.());
}

function labelProblem(row) {
    if (!props.strict) return '';
    const label = row.label.trim();
    if (/[:：•\n]/.test(label)) return t('vs_label_bad_chars');
    return '';
}

function valueProblem(row) {
    if (!props.strict) return '';
    return /[•\n]/.test(row.value) ? t('vs_value_bad_chars') : '';
}

const firstProblem = computed(() => {
    for (const row of rows.value) {
        const problem = labelProblem(row) || valueProblem(row);
        if (problem) return problem;
    }
    return '';
});

defineExpose({ hasProblems: () => firstProblem.value !== '' });

function removeRow(idx) {
    rows.value.splice(idx, 1);
    emitRows();
}

function move(idx, delta) {
    const to = idx + delta;
    if (to < 0 || to >= rows.value.length) return;
    const [row] = rows.value.splice(idx, 1);
    rows.value.splice(to, 0, row);
    emitRows();
}

function copyFrom(id) {
    const src = sources.value.find((s) => s.id === id);
    if (!src) return;
    rows.value = toRows(src.specs);
    emitRows();
}

function suggestLabels(query, cb) {
    const taken = new Set(rows.value.map((r) => r.label.trim()));
    cb(props.labelSuggestions
        .filter((l) => !taken.has(l) && matchesSearch(l, query))
        .slice(0, 12)
        .map((value) => ({ value })));
}

// "Power: 750W • Disc: 115mm", one pair per line, or tab-separated cells
// pasted from a spreadsheet. A part with no separator is a value alone.
const pasteOpen = ref(false);
const pasteText = ref('');

function parsePasted(text) {
    return text
        .split(/\s*[\n•|;]\s*/)
        .map((part) => part.trim())
        .filter(Boolean)
        .map((part) => {
            const m = part.match(/^([^:：\t]{1,100}?)\s*[:：\t]\s*(.+)$/);
            return m ? { label: m[1].trim(), value: m[2].trim() } : { label: '', value: part };
        });
}

function applyPaste() {
    const parsed = parsePasted(pasteText.value);
    if (!parsed.length) return;
    // Append after what's there, skipping labels already present.
    const have = new Set(rows.value.map((r) => r.label.trim()).filter(Boolean));
    const filled = rows.value.filter((r) => r.label.trim() || r.value.trim());
    rows.value = [
        ...filled,
        ...toRows(parsed.filter((r) => !r.label || !have.has(r.label))),
    ];
    pasteText.value = '';
    pasteOpen.value = false;
    emitRows();
}
</script>

<style scoped>
.variant-specs {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;
    padding: 12px;
    background: var(--el-fill-color-blank);
}

.variant-specs-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.variant-specs-title {
    font-weight: 600;
    color: var(--el-text-color-primary);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.variant-specs-count {
    font-size: 12px;
    font-weight: 600;
    padding: 0 7px;
    border-radius: 10px;
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
}

.variant-specs-tools {
    display: flex;
    gap: 6px;
}

.variant-specs-src-count {
    color: var(--el-text-color-secondary);
    margin-inline-start: 4px;
}

.variant-specs-paste {
    margin-bottom: 10px;
}

.variant-specs-paste-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}

.variant-specs-hint {
    flex: 1;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.variant-specs-rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.variant-specs-row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 3fr) auto;
    gap: 6px;
    align-items: center;
}

.variant-specs-label {
    width: 100%;
}

.variant-specs-row-actions {
    display: flex;
    gap: 0;
}

.variant-specs-row-actions :deep(.el-button + .el-button) {
    margin-left: 0;
}

.variant-specs-lead {
    display: block;
    margin: -4px 0 10px;
}

.variant-specs-label.is-invalid :deep(.el-input__wrapper),
.variant-specs-value.is-invalid :deep(.el-input__wrapper) {
    box-shadow: 0 0 0 1px var(--el-color-danger) inset;
}

.variant-specs-problem {
    margin: 8px 0 0;
    font-size: 12px;
    color: var(--el-color-danger);
}

.variant-specs-empty {
    margin: 0 0 8px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.variant-specs-add {
    margin-top: 8px;
}

@media (max-width: 560px) {
    .variant-specs-row {
        grid-template-columns: 1fr;
        padding-bottom: 6px;
        border-bottom: 1px dashed var(--el-border-color-lighter);
    }
}
</style>
