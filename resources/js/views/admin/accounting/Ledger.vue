<template>
    <div class="accounting-page accounting-ledger">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-list-ol text-primary"></i> {{ $t('ledger') || 'شجرة ودليل الحسابات' }}</h1>
                <p>إدارة وتصنيف الحسابات المحاسبية العامة (الأصول، الخصوم، حقوق الملكية، الإيرادات، والمصروفات).</p>
            </div>
            <div class="header-actions">
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> إضافة حساب جديد
                </el-button>
            </div>
        </div>

        <!-- Metric summaries of account types -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="type-stat-card">
                    <div class="type-stat-inner">
                        <span class="label text-muted">حسابات الأصول</span>
                        <strong class="count-val">{{ assetsCount }} حساب</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="type-stat-card">
                    <div class="type-stat-inner">
                        <span class="label text-muted">حسابات الخصوم</span>
                        <strong class="count-val">{{ liabilitiesCount }} حساب</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="type-stat-card">
                    <div class="type-stat-inner">
                        <span class="label text-muted">حسابات الإيرادات</span>
                        <strong class="count-val">{{ revenueCount }} حساب</strong>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="type-stat-card">
                    <div class="type-stat-inner">
                        <span class="label text-muted">حسابات المصروفات</span>
                        <strong class="count-val">{{ expenseCount }} حساب</strong>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Main Card & Accounts Table -->
        <el-card shadow="hover" class="table-panel mt-4">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-sitemap text-muted"></i> دليل حسابات الأستاذ العام</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="store.accounts.length" 
                    :data="store.accounts" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="code" :label="$t('code') || 'رمز الحساب'" width="130" align="center">
                        <template #default="{ row }">
                            <span class="code-badge">{{ row.code }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" :label="$t('the_account') || 'اسم الحساب'" min-width="180">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark); cursor: pointer;" @click="openStatementDrawer(row)">{{ row.name }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" :label="$t('type') || 'التصنيف'" width="160" align="center">
                        <template #default="{ row }">
                            <el-tag :type="typeTagType(row.type)" effect="light">{{ getArabicType(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="balance" :label="$t('balance') || 'الرصيد الحالي'" width="160" align="right">
                        <template #default="{ row }">
                            <strong :class="parseFloat(row.balance) >= 0 ? 'text-success' : 'text-danger'">
                                ${{ parseFloat(row.balance || 0).toFixed(2) }}
                            </strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('active') || 'الحالة'" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? 'نشط' : 'معطل' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <!-- Actions Column -->
                    <el-table-column label="الإجراءات" width="260" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button size="small" type="info" plain @click="openStatementDrawer(row)" title="كشف الحساب">
                                    <i class="fas fa-file-invoice"></i> كشف
                                </el-button>
                                <el-button size="small" type="warning" plain @click="openEditDrawer(row)" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain @click="deleteAccount(row.id)" :disabled="row.is_system" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.accounts.length" class="empty-state-box">
                    <i class="fas fa-sitemap empty-icon"></i>
                    <p>لا توجد حسابات مسجلة في دليل الحسابات حالياً.</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> إضافة حساب
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Create / Edit Account Drawer -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? 'تعديل حساب محاسبي' : 'إضافة حساب جديد لدليل الحسابات'"
            size="40%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-form-item label="رمز الحساب (Code)" required>
                    <el-input v-model="form.code" placeholder="مثال: 101001" :disabled="isEditMode" />
                </el-form-item>

                <el-form-item label="اسم الحساب" required>
                    <el-input v-model="form.name" placeholder="مثال: الصندوق الرئيسي، بنك البلاد..." />
                </el-form-item>

                <el-form-item label="تصنيف نوع الحساب الرئيسي" required>
                    <el-select v-model="form.type" placeholder="اختر نوع الحساب" style="width: 100%">
                        <el-option label="أصول (Asset)" value="Asset" />
                        <el-option label="خصوم / التزامات (Liability)" value="Liability" />
                        <el-option label="حقوق الملكية (Equity)" value="Equity" />
                        <el-option label="إيرادات (Revenue)" value="Revenue" />
                        <el-option label="مصروفات (Expense)" value="Expense" />
                    </el-select>
                </el-form-item>

                <el-form-item label="الرصيد الافتتاحي (Opening Balance)" v-if="!isEditMode">
                    <el-input v-model="form.balance" type="number" placeholder="0.00" style="width: 100%">
                        <template #suffix>$</template>
                    </el-input>
                </el-form-item>

                <el-form-item label="حالة النشاط" required>
                    <el-select v-model="form.is_active" style="width: 100%">
                        <el-option label="نشط" :value="true" />
                        <el-option label="غير نشط" :value="false" />
                    </el-select>
                </el-form-item>

                <el-form-item label="شرح / وصف الحساب">
                    <el-input v-model="form.description" type="textarea" :rows="3" placeholder="ملاحظات تفصيلية حول استخدام هذا الحساب..." />
                </el-form-item>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="formDrawerVisible = false">إلغاء</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAccount">حفظ الحساب</el-button>
                </div>
            </el-form>
        </el-drawer>

        <!-- Statement / Transactions History Drawer -->
        <el-drawer
            v-model="statementDrawerVisible"
            title="كشف حساب تفصيلي (أستاذ مساعد)"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingStatement" v-loading="loadingStatement" style="min-height: 250px;"></div>
            <div v-else-if="selectedAccount" class="drawer-detail-content">
                <!-- Account header metrics -->
                <div class="mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span class="code-badge" style="font-size: 1rem; padding: 0.25rem 0.75rem; margin-bottom: 0.5rem; display: inline-block;">{{ selectedAccount.code }}</span>
                        <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: var(--text-dark);">{{ selectedAccount.name }}</h3>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.9rem;">تصنيف الحساب: <strong>{{ getArabicType(selectedAccount.type) }}</strong></p>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">الرصيد الدفتري الحالي</span>
                        <h2 :class="parseFloat(selectedAccount.balance) >= 0 ? 'text-success' : 'text-danger'" style="margin: 0; font-size: 1.8rem; font-weight: 800;">
                            ${{ parseFloat(selectedAccount.balance || 0).toFixed(2) }}
                        </h2>
                    </div>
                </div>

                <!-- Ledger Statement Table -->
                <el-table :data="statementEntriesWithBalance" style="width: 100%" stripe size="small">
                    <el-table-column prop="entry_date" label="التاريخ" width="110" align="center" />
                    <el-table-column prop="description" label="البيان" min-width="180" />
                    <el-table-column prop="reference" label="المرجع" width="120" show-overflow-tooltip />
                    <el-table-column prop="debit" label="مدين (Debit)" width="110" align="right">
                        <template #default="{ row }">
                            <span v-if="row.debit > 0" class="text-success">${{ parseFloat(row.debit).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="credit" label="دائن (Credit)" width="110" align="right">
                        <template #default="{ row }">
                            <span v-if="row.credit > 0" class="text-warning">${{ parseFloat(row.credit).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="running_balance" label="الرصيد التراكمي" width="130" align="right">
                        <template #default="{ row }">
                            <strong :class="row.running_balance >= 0 ? 'text-success' : 'text-danger'">
                                ${{ parseFloat(row.running_balance).toFixed(2) }}
                            </strong>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!statementEntries.length" class="empty-state" style="padding: 3rem 0; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-file-invoice" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                    لا توجد حركات أو قيود مسجلة على هذا الحساب بعد.
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { ledgerAccountsApi } from '@/api/ledgerAccounts';
import { journalEntriesApi } from '@/api/journalEntries';
import { ElMessage } from 'element-plus';

const store = useLedgerAccountsStore();

// Type counts
const checkType = (type, target) => {
    const val = String(type || '').toLowerCase();
    const tgt = String(target || '').toLowerCase();
    return val === tgt || val === tgt + 's' || val + 's' === tgt;
};

const assetsCount = computed(() => store.accounts.filter(a => checkType(a.type, 'asset')).length);
const liabilitiesCount = computed(() => store.accounts.filter(a => checkType(a.type, 'liability')).length);
const revenueCount = computed(() => store.accounts.filter(a => checkType(a.type, 'revenue')).length);
const expenseCount = computed(() => store.accounts.filter(a => checkType(a.type, 'expense')).length);

const typeTagType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val.includes('asset')) return 'success';
    if (val.includes('liability')) return 'warning';
    if (val.includes('equity')) return 'danger';
    if (val.includes('revenue')) return 'info';
    return 'info';
};

const getArabicType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val.includes('asset')) return 'أصول (Asset)';
    if (val.includes('liability')) return 'خصوم (Liability)';
    if (val.includes('equity')) return 'حقوق ملكية (Equity)';
    if (val.includes('revenue')) return 'إيرادات (Revenue)';
    if (val.includes('expense')) return 'مصروفات (Expense)';
    return type;
};

// Form state
const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingAccountId = ref(null);
const form = reactive({
    code: '',
    name: '',
    type: 'Asset',
    balance: 0,
    is_active: true,
    description: ''
});

// Statement drawer state
const statementDrawerVisible = ref(false);
const loadingStatement = ref(false);
const selectedAccount = ref(null);
const statementEntries = ref([]);

const resetForm = () => {
    form.code = '';
    form.name = '';
    form.type = 'Asset';
    form.balance = 0;
    form.is_active = true;
    form.description = '';
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    resetForm();
    formDrawerVisible.value = true;
};

const openEditDrawer = (account) => {
    isEditMode.value = true;
    editingAccountId.value = account.id;
    formDrawerVisible.value = true;
    resetForm();
    form.code = account.code;
    form.name = account.name;
    form.type = account.type;
    form.balance = account.balance;
    form.is_active = account.is_active;
    form.description = account.description;
};

const saveAccount = async () => {
    if (!form.code.trim() || !form.name.trim()) {
        ElMessage.warning('يرجى تعبئة رمز الحساب واسمه.');
        return;
    }

    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            await ledgerAccountsApi.update(editingAccountId.value, form);
            ElMessage.success('تم تحديث الحساب بنجاح.');
        } else {
            await ledgerAccountsApi.create(form);
            ElMessage.success('تم حفظ الحساب الجديد بنجاح.');
        }
        formDrawerVisible.value = false;
        await store.fetchAccounts({ per_page: 100 });
    } catch (e) {
        ElMessage.error('خطأ أثناء حفظ الحساب الدفتري.');
    } finally {
        submittingForm.value = false;
    }
};

const deleteAccount = async (id) => {
    if (confirm('هل أنت متأكد من حذف هذا الحساب المحاسبي؟')) {
        try {
            await ledgerAccountsApi.delete(id);
            ElMessage.success('تم حذف الحساب بنجاح.');
            await store.fetchAccounts({ per_page: 100 });
        } catch (e) {
            ElMessage.error('خطأ أثناء حذف الحساب الدفتري.');
        }
    }
};

const openStatementDrawer = async (account) => {
    statementDrawerVisible.value = true;
    selectedAccount.value = account;
    loadingStatement.value = true;
    statementEntries.value = [];
    try {
        const res = await journalEntriesApi.getAll({ ledger_account_id: account.id, per_page: 200 });
        statementEntries.value = res.data.data.entries || [];
    } catch (e) {
        ElMessage.error('خطأ أثناء تحميل الحركات المحاسبية.');
    } finally {
        loadingStatement.value = false;
    }
};

// Computes running balance cumulatively starting from 0 (or balance matches final balance)
const statementEntriesWithBalance = computed(() => {
    if (!statementEntries.value.length) return [];
    
    // Sort oldest first to calculate running balance correctly
    const entriesSorted = [...statementEntries.value].sort((a, b) => new Date(a.entry_date) - new Date(b.entry_date));
    
    let currentBal = 0;
    const entriesWithBal = entriesSorted.map(entry => {
        const deb = parseFloat(entry.debit || 0);
        const cred = parseFloat(entry.credit || 0);
        currentBal += (deb - cred);
        return {
            ...entry,
            running_balance: currentBal
        };
    });
    
    // Return newest first for display in table
    return entriesWithBal.reverse();
});

onMounted(() => {
    store.fetchAccounts({ per_page: 100 }).catch(() => {});
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

.type-stat-card {
    border-radius: var(--radius-md);
    text-align: center;
}

.type-stat-inner {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.type-stat-inner .label {
    font-size: 0.85rem;
    font-weight: 600;
}

.type-stat-inner .count-val {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
}

.code-badge {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.15rem 0.5rem;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-family: monospace;
    font-size: 0.85rem;
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

.action-btn-group .el-button {
    padding: 0.4rem 0.6rem;
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

/* Detail Drawer */
.drawer-detail-content {
    padding: 1.5rem;
    font-family: 'Cairo', sans-serif;
}
</style>
