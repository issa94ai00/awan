<template>
    <div class="accounting-page accounting-journal">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-book text-primary"></i> {{ $t('journal') || 'دفتر اليومية العامة' }}</h1>
                <p>تسجيل ومتابعة القيود المحاسبية اليومية المزدوجة وضمان توازن الدورة الدفترية.</p>
            </div>
            <div class="header-actions">
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> تسجيل قيد يدوي
                </el-button>
            </div>
        </div>

        <!-- Filters Bar Panel -->
        <el-card shadow="hover" class="filters-panel mb-4">
            <div class="filters-row">
                <div class="filter-item">
                    <label>حساب دفتر الأستاذ</label>
                    <el-select v-model="filters.ledger_account_id" placeholder="الكل" clearable style="width: 220px;" filterable @change="applyFilters">
                        <el-option 
                            v-for="acc in ledgerStore.accounts" 
                            :key="acc.id" 
                            :label="acc.code + ' - ' + acc.name" 
                            :value="acc.id" 
                        />
                    </el-select>
                </div>
                <div class="filter-item">
                    <label>الفترة من</label>
                    <el-date-picker v-model="filters.date_from" type="date" placeholder="من تاريخ" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 160px;" @change="applyFilters" />
                </div>
                <div class="filter-item">
                    <label>إلى</label>
                    <el-date-picker v-model="filters.date_to" type="date" placeholder="إلى تاريخ" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 160px;" @change="applyFilters" />
                </div>
                <el-button type="info" plain @click="resetFilters" style="margin-top: 1.25rem;">إعادة تعيين</el-button>
            </div>
        </el-card>

        <!-- Main Card & Entries Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list-alt text-muted"></i> قيود دفتر اليومية</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="store.entries.length" 
                    :data="store.entries" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="entry_date" :label="$t('the_date') || 'التاريخ'" width="130" align="center" />
                    <el-table-column prop="ledger_account.name" :label="$t('account') || 'الحساب'" min-width="180">
                        <template #default="{ row }">
                            <span style="font-weight: 700; color: var(--text-dark);">
                                {{ row.ledger_account?.code }} - {{ row.ledger_account?.name }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="description" :label="$t('description') || 'البيان/الوصف'" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="debit" :label="$t('debtor') || 'مدين ($)'" width="140" align="right">
                        <template #default="{ row }">
                            <strong v-if="row.debit > 0" class="text-success">${{ parseFloat(row.debit).toFixed(2) }}</strong>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="credit" :label="$t('creditor') || 'دائن ($)'" width="140" align="right">
                        <template #default="{ row }">
                            <strong v-if="row.credit > 0" class="text-warning">${{ parseFloat(row.credit).toFixed(2) }}</strong>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="reference" :label="$t('reference') || 'المرجع'" width="160" show-overflow-tooltip />
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.entries.length" class="empty-state-box">
                    <i class="fas fa-book empty-icon"></i>
                    <p>لا توجد قيود يومية مطابقة للبحث حالياً.</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> تسجيل قيد يدوي
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Create Journal Entry Drawer -->
        <el-drawer
            v-model="createDrawerVisible"
            title="تسجيل قيد محاسبي يدوي جديد"
            size="45%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-form-item label="تاريخ الاستحقاق/القيد" required>
                    <el-date-picker v-model="form.entry_date" type="date" placeholder="اختر التاريخ" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                </el-form-item>

                <el-form-item label="حساب دفتر الأستاذ المرتبط" required>
                    <el-select v-model="form.ledger_account_id" placeholder="اختر الحساب المحاسبي" style="width: 100%" filterable>
                        <el-option 
                            v-for="acc in ledgerStore.accounts" 
                            :key="acc.id" 
                            :label="acc.code + ' - ' + acc.name + ' ($' + acc.balance + ')'" 
                            :value="acc.id" 
                        />
                    </el-select>
                </el-form-item>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="قيمة المدين (Debit)">
                            <el-input v-model="form.debit" type="number" placeholder="0.00" style="width: 100%">
                                <template #suffix>$</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="قيمة الدائن (Credit)">
                            <el-input v-model="form.credit" type="number" placeholder="0.00" style="width: 100%">
                                <template #suffix>$</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="مرجع المعاملة (الرمز/الفاتورة)">
                    <el-input v-model="form.reference" placeholder="مثال: INV-2026-009" />
                </el-form-item>

                <el-form-item label="البيان / الوصف التفصيلي" required>
                    <el-input v-model="form.description" type="textarea" :rows="4" placeholder="اكتب بياناً واضحاً ومفصلاً للقيد المالي..." />
                </el-form-item>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="createDrawerVisible = false">إلغاء</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveEntry">تسجيل وإثبات القيد</el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useJournalEntriesStore } from '@/stores/journalEntries';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { journalEntriesApi } from '@/api/journalEntries';
import { ElMessage } from 'element-plus';

const store = useJournalEntriesStore();
const ledgerStore = useLedgerAccountsStore();

// Filters state
const filters = reactive({
    ledger_account_id: '',
    date_from: '',
    date_to: ''
});

// Form state
const createDrawerVisible = ref(false);
const submittingForm = ref(false);
const form = reactive({
    entry_date: '',
    ledger_account_id: '',
    debit: 0,
    credit: 0,
    reference: '',
    description: ''
});

const resetForm = () => {
    form.entry_date = new Date().toISOString().split('T')[0];
    form.ledger_account_id = '';
    form.debit = 0;
    form.credit = 0;
    form.reference = '';
    form.description = '';
};

const applyFilters = () => {
    const params = {};
    if (filters.ledger_account_id) params.ledger_account_id = filters.ledger_account_id;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    store.fetchEntries(params).catch(() => {});
};

const resetFilters = () => {
    filters.ledger_account_id = '';
    filters.date_from = '';
    filters.date_to = '';
    store.fetchEntries().catch(() => {});
};

const openCreateDrawer = () => {
    resetForm();
    createDrawerVisible.value = true;
};

const saveEntry = async () => {
    if (!form.ledger_account_id) {
        ElMessage.warning('يرجى تحديد حساب دفتر الأستاذ.');
        return;
    }
    if (!form.description.trim()) {
        ElMessage.warning('يرجى كتابة بيان ووصف القيد.');
        return;
    }
    
    const deb = parseFloat(form.debit || 0);
    const cred = parseFloat(form.credit || 0);
    
    if (deb <= 0 && cred <= 0) {
        ElMessage.warning('يجب أن تكون قيمة مدين أو دائن أكبر من صفر.');
        return;
    }
    if (deb > 0 && cred > 0) {
        ElMessage.warning('لا يمكن تسجيل قيمة مدين ودائن معاً لنفس الحساب في سطر واحد للقيد.');
        return;
    }

    submittingForm.value = true;
    try {
        await journalEntriesApi.create(form);
        ElMessage.success('تم تسجيل وإثبات القيد بنجاح.');
        createDrawerVisible.value = false;
        await store.fetchEntries();
        await ledgerStore.fetchAccounts({ per_page: 100 });
    } catch (e) {
        ElMessage.error('خطأ أثناء حفظ القيد المحاسبي.');
    } finally {
        submittingForm.value = false;
    }
};

onMounted(() => {
    store.fetchEntries().catch(() => {});
    ledgerStore.fetchAccounts({ per_page: 100 }).catch(() => {});
});
</script>

<style scoped>
.accounting-page {
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.filters-panel {
    border-radius: var(--radius-md);
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    align-items: flex-end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
}

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.loading-state {
    padding: 2rem;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-state-box p {
    font-weight: 500;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
}
</style>
