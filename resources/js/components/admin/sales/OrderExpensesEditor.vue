<template>
    <div class="order-expenses-editor" :class="{ 'is-compact': compact }">
        <div class="editor-header">
            <div class="header-main">
                <div class="header-icon">
                    <i class="fas fa-truck-fast"></i>
                </div>
                <div class="header-text">
                    <h3 class="header-title">{{ title || $t('additional_expenses_and_shipping') }}</h3>
                    <p class="header-subtitle">{{ $t('ledger_expense_link_note') }}</p>
                </div>
            </div>

            <div class="header-badges">
                <span v-if="expensesCount > 0" class="count-badge">
                    {{ expensesCount }} {{ $t('item') }}
                </span>
                <span class="total-badge" :class="{ 'has-amount': totalExpenses > 0 }">
                    <small>{{ $t('total') }}:</small>
                    <strong>{{ formatCurrency(totalExpenses) }}</strong>
                </span>
            </div>
        </div>

        <!-- Quick preset buttons bar -->
        <div class="quick-presets">
            <span class="preset-label">{{ $t('add') }}:</span>
            <div class="preset-buttons">
                <button
                    type="button"
                    class="preset-btn btn-shipping"
                    @click="addPreset('shipping', $t('quick_add_shipping'))"
                >
                    <i class="fas fa-truck"></i>
                    <span>{{ $t('quick_add_shipping') }}</span>
                </button>
                <button
                    type="button"
                    class="preset-btn btn-packaging"
                    @click="addPreset('packaging', $t('quick_add_packaging'))"
                >
                    <i class="fas fa-box-open"></i>
                    <span>{{ $t('quick_add_packaging') }}</span>
                </button>
                <button
                    type="button"
                    class="preset-btn btn-handling"
                    @click="addPreset('handling', $t('quick_add_handling'))"
                >
                    <i class="fas fa-dolly"></i>
                    <span>{{ $t('quick_add_handling') }}</span>
                </button>
                <button
                    type="button"
                    class="preset-btn btn-other"
                    @click="addPreset('other', $t('quick_add_other'))"
                >
                    <i class="fas fa-plus"></i>
                    <span>{{ $t('quick_add_other') }}</span>
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!expenses.length" class="empty-expenses" @click="addPreset('shipping', $t('quick_add_shipping'))">
            <div class="empty-icon-wrap">
                <i class="fas fa-receipt"></i>
            </div>
            <p class="empty-title">{{ $t('no_expenses_recorded') }}</p>
            <p class="empty-action">{{ $t('click_to_add_quick_shipping') || 'انقر على أحد الأزرار السريعة أعلاه لإضافة مصاريف شحن أو تغليف' }}</p>
        </div>

        <!-- Expense list items -->
        <div v-else class="expenses-list">
            <div
                v-for="(expense, index) in expenses"
                :key="expense._uid || index"
                class="expense-row-card"
                :class="[`cat-${expense.category}`, { 'is-expanded': expense._showNotes }]"
            >
                <div class="row-main">
                    <!-- Category Selector with Badge -->
                    <div class="category-select-wrapper">
                        <el-select
                            v-model="expense.category"
                            size="default"
                            class="cat-select"
                            @change="onCategoryChange(expense)"
                        >
                            <el-option
                                v-for="cat in CATEGORIES"
                                :key="cat.value"
                                :value="cat.value"
                                :label="cat.label"
                            >
                                <div class="cat-option">
                                    <i :class="cat.icon"></i>
                                    <span>{{ cat.label }}</span>
                                </div>
                            </el-option>
                        </el-select>
                    </div>

                    <!-- Description Input -->
                    <div class="desc-wrapper">
                        <el-input
                            ref="descInputRef"
                            v-model="expense.description"
                            :placeholder="$t('description') + ' (مثال: أجور شحن مع شركة القدموس)'"
                            size="default"
                            clearable
                        />
                    </div>

                    <!-- Amount Input -->
                    <div class="amount-wrapper">
                        <el-input-number
                            v-model="expense.amount"
                            :min="0"
                            :precision="2"
                            :step="10"
                            :controls="false"
                            size="default"
                            placeholder="0.00"
                            class="expense-amount-input"
                            @change="emitUpdate"
                        />
                    </div>

                    <!-- Status Selector: Paid vs Pending -->
                    <div class="status-wrapper">
                        <div class="status-pills">
                            <button
                                type="button"
                                class="status-pill pill-paid"
                                :class="{ active: expense.status === 'paid' }"
                                :title="$t('expense_paid_immediate')"
                                @click="expense.status = 'paid'; emitUpdate()"
                            >
                                <i class="fas fa-check-circle"></i>
                                <span>{{ $t('expense_paid') }}</span>
                            </button>
                            <button
                                type="button"
                                class="status-pill pill-pending"
                                :class="{ active: expense.status === 'pending' }"
                                :title="$t('expense_pending_due')"
                                @click="expense.status = 'pending'; emitUpdate()"
                            >
                                <i class="fas fa-clock"></i>
                                <span>{{ $t('expense_pending') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Row Action Buttons -->
                    <div class="row-actions">
                        <button
                            type="button"
                            class="action-btn note-btn"
                            :class="{ active: expense._showNotes || expense.notes }"
                            :title="$t('notes')"
                            @click="toggleNotes(expense)"
                        >
                            <i class="fas fa-note-sticky"></i>
                        </button>
                        <button
                            type="button"
                            class="action-btn delete-btn"
                            :title="$t('delete')"
                            @click="removeExpense(index)"
                        >
                            <i class="fas fa-trash-can"></i>
                        </button>
                    </div>
                </div>

                <!-- Expandable Notes / Carrier Details -->
                <transition name="slide-down">
                    <div v-if="expense._showNotes || expense.notes" class="row-notes-drawer">
                        <el-input
                            v-model="expense.notes"
                            type="text"
                            size="small"
                            :placeholder="$t('expense_carrier_note_placeholder')"
                            clearable
                        >
                            <template #prefix>
                                <i class="fas fa-info-circle text-muted"></i>
                            </template>
                        </el-input>
                    </div>
                </transition>
            </div>
        </div>

        <!-- Footer Breakdown -->
        <div v-if="expenses.length > 0" class="editor-footer">
            <div class="breakdown-chips">
                <span
                    v-for="(catTotal, catKey) in categoryTotals"
                    v-show="catTotal > 0"
                    :key="catKey"
                    class="breakdown-chip"
                    :class="`chip-${catKey}`"
                >
                    <i :class="categoryIcon(catKey)"></i>
                    <span class="chip-label">{{ categoryLabel(catKey) }}:</span>
                    <strong>{{ formatCurrency(catTotal) }}</strong>
                </span>
            </div>

            <div class="footer-add-btn">
                <el-button size="small" text type="primary" @click="addPreset('shipping', '')">
                    <i class="fas fa-plus"></i>&nbsp;{{ $t('add_expense') }}
                </el-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatCurrency } from '@/utils/sales';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'total-change']);
const { t } = useI18n();

let uidCounter = 0;
const nextUid = () => `exp_${Date.now()}_${++uidCounter}`;

const CATEGORIES = computed(() => [
    { value: 'shipping', label: t('shipping'), icon: 'fas fa-truck' },
    { value: 'packaging', label: t('packaging'), icon: 'fas fa-box-open' },
    { value: 'handling', label: t('process'), icon: 'fas fa-dolly' },
    { value: 'other', label: t('subject_other'), icon: 'fas fa-receipt' },
]);

// Internal reactive representation
const expenses = ref([]);

const initFromModel = (incoming) => {
    if (!Array.isArray(incoming)) {
        expenses.value = [];
        return;
    }
    expenses.value = incoming.map((item) => ({
        _uid: item._uid || nextUid(),
        id: item.id || null,
        category: item.category || 'shipping',
        description: item.description || '',
        amount: Number(item.amount) || 0,
        status: item.status || 'paid',
        notes: item.notes || '',
        _showNotes: !!item.notes,
    }));
};

watch(
    () => props.modelValue,
    (newVal) => {
        // Only update internal if external length or contents differs
        const currentClean = expenses.value.map((e) => ({
            category: e.category,
            description: e.description,
            amount: Number(e.amount),
            status: e.status,
            notes: e.notes,
        }));
        const newClean = (newVal || []).map((e) => ({
            category: e.category || 'shipping',
            description: e.description || '',
            amount: Number(e.amount) || 0,
            status: e.status || 'paid',
            notes: e.notes || '',
        }));

        if (JSON.stringify(currentClean) !== JSON.stringify(newClean)) {
            initFromModel(newVal);
        }
    },
    { immediate: true, deep: true }
);

const expensesCount = computed(() => expenses.value.length);

const totalExpenses = computed(() =>
    expenses.value.reduce((sum, item) => sum + (Number(item.amount) || 0), 0)
);

const categoryTotals = computed(() => {
    const totals = { shipping: 0, packaging: 0, handling: 0, other: 0 };
    for (const exp of expenses.value) {
        const cat = exp.category || 'other';
        if (totals[cat] !== undefined) {
            totals[cat] += Number(exp.amount) || 0;
        } else {
            totals.other += Number(exp.amount) || 0;
        }
    }
    return totals;
});

const categoryIcon = (cat) => {
    switch (cat) {
        case 'shipping': return 'fas fa-truck';
        case 'packaging': return 'fas fa-box-open';
        case 'handling': return 'fas fa-dolly';
        default: return 'fas fa-receipt';
    }
};

const categoryLabel = (cat) => {
    const match = CATEGORIES.value.find((c) => c.value === cat);
    return match ? match.label : cat;
};

const emitUpdate = () => {
    const cleanList = expenses.value.map((exp) => ({
        id: exp.id || null,
        category: exp.category || 'shipping',
        description: exp.description || '',
        amount: Number(exp.amount) || 0,
        status: exp.status || 'paid',
        notes: exp.notes || '',
    }));
    emit('update:modelValue', cleanList);
    emit('total-change', totalExpenses.value);
};

// Watch internal changes and emit
watch(
    expenses,
    () => {
        emitUpdate();
    },
    { deep: true }
);

const addPreset = (category, defaultDesc) => {
    const newExp = {
        _uid: nextUid(),
        id: null,
        category,
        description: defaultDesc,
        amount: 0,
        status: 'paid',
        notes: '',
        _showNotes: false,
    };
    expenses.value.push(newExp);
    emitUpdate();
};

const onCategoryChange = (expense) => {
    // If description is empty or default, suggest matching label
    if (!expense.description) {
        switch (expense.category) {
            case 'shipping': expense.description = t('quick_add_shipping'); break;
            case 'packaging': expense.description = t('quick_add_packaging'); break;
            case 'handling': expense.description = t('quick_add_handling'); break;
        }
    }
    emitUpdate();
};

const removeExpense = (index) => {
    expenses.value.splice(index, 1);
    emitUpdate();
};

const toggleNotes = (expense) => {
    expense._showNotes = !expense._showNotes;
};
</script>

<style scoped>
.order-expenses-editor {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 1.25rem;
    transition: all 0.2s ease;
}

.order-expenses-editor:hover {
    border-color: #cbd5e1;
}

/* Header */
.editor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.header-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #eff6ff;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.header-text {
    display: flex;
    flex-direction: column;
}

.header-title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 700;
    color: #1e293b;
}

.header-subtitle {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: #64748b;
}

.header-badges {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.count-badge {
    background: #f1f5f9;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}

.total-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.82rem;
    color: #64748b;
    transition: all 0.2s ease;
}

.total-badge.has-amount {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #065f46;
}

.total-badge strong {
    font-size: 0.95rem;
    font-weight: 800;
}

/* Quick Presets */
.quick-presets {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
}

.preset-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
}

.preset-buttons {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.preset-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 8px;
    border: 1px solid transparent;
    cursor: pointer;
    background: #f8fafc;
    color: #334155;
    transition: all 0.18s ease;
}

.preset-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.preset-btn.btn-shipping {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.preset-btn.btn-shipping:hover {
    background: #dbeafe;
}

.preset-btn.btn-packaging {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}
.preset-btn.btn-packaging:hover {
    background: #fef3c7;
}

.preset-btn.btn-handling {
    background: #f5f3ff;
    color: #6d28d9;
    border-color: #ddd6fe;
}
.preset-btn.btn-handling:hover {
    background: #ede9fe;
}

.preset-btn.btn-other {
    background: #f1f5f9;
    color: #475569;
    border-color: #e2e8f0;
}
.preset-btn.btn-other:hover {
    background: #e2e8f0;
}

/* Empty State */
.empty-expenses {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.empty-expenses:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.empty-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.empty-title {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
}

.empty-action {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #94a3b8;
}

/* Expenses List */
.expenses-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.expense-row-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.5rem 0.65rem;
    transition: all 0.2s ease;
}

.expense-row-card:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
}

.expense-row-card.cat-shipping {
    border-inline-start: 3px solid #3b82f6;
}

.expense-row-card.cat-packaging {
    border-inline-start: 3px solid #f59e0b;
}

.expense-row-card.cat-handling {
    border-inline-start: 3px solid #8b5cf6;
}

.expense-row-card.cat-other {
    border-inline-start: 3px solid #64748b;
}

.row-main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.category-select-wrapper {
    width: 130px;
    flex-shrink: 0;
}

.cat-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.desc-wrapper {
    flex: 1;
    min-width: 180px;
}

.amount-wrapper {
    width: 120px;
    flex-shrink: 0;
}

.expense-amount-input {
    width: 100% !important;
}

/* Status Pills */
.status-wrapper {
    flex-shrink: 0;
}

.status-pills {
    display: inline-flex;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 2px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.55rem;
    font-size: 0.74rem;
    font-weight: 600;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.status-pill.pill-paid.active {
    background: #ecfdf5;
    color: #059669;
}

.status-pill.pill-pending.active {
    background: #fffbeb;
    color: #d97706;
}

/* Actions */
.row-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.18s ease;
}

.action-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.action-btn.note-btn.active {
    color: #2563eb;
    background: #eff6ff;
}

.action-btn.delete-btn:hover {
    background: #fef2f2;
    color: #dc2626;
}

/* Notes Drawer */
.row-notes-drawer {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed #e2e8f0;
}

/* Footer Breakdown */
.editor-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.85rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.breakdown-chips {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.breakdown-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.breakdown-chip.chip-shipping {
    background: #eff6ff;
    color: #1e40af;
}

.breakdown-chip.chip-packaging {
    background: #fffbeb;
    color: #92400e;
}

.breakdown-chip.chip-handling {
    background: #f5f3ff;
    color: #5b21b6;
}

.breakdown-chip.chip-other {
    background: #f1f5f9;
    color: #334155;
}

/* Compact layout mode */
.is-compact {
    padding: 0.75rem;
}

.is-compact .editor-header {
    margin-bottom: 0.6rem;
    padding-bottom: 0.5rem;
}

.is-compact .header-icon {
    width: 30px;
    height: 30px;
    font-size: 0.95rem;
}

@media (max-width: 768px) {
    .row-main {
        flex-direction: column;
        align-items: stretch;
    }
    .category-select-wrapper,
    .amount-wrapper {
        width: 100%;
    }
    .row-actions {
        justify-content: flex-end;
    }
}
</style>
