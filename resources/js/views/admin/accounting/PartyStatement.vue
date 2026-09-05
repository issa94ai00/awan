<template>
    <div class="accounting-page party-statement">
        <AdminPageHeader
            icon="fas fa-file-lines text-primary"
            :title="$t('party_statement')"
            :subtitle="$t('party_statement_subtitle')"
        >
            <template #actions>
                <el-date-picker
                    v-model="range"
                    type="daterange"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    :start-placeholder="$t('period_from')"
                    :end-placeholder="$t('to')"
                    :shortcuts="dateShortcuts"
                    @change="reload"
                />
                <el-button
                    type="success"
                    plain
                    :disabled="!report"
                    @click="printReport"
                >
                    <i class="fas fa-print"></i> {{ $t('print_statement') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="hover" class="filters-panel mb-4 no-print">
            <div class="filters-row">
                <div class="filter-item">
                    <label>{{ $t('party_type') }}</label>
                    <el-radio-group v-model="type" size="small" @change="onTypeChange">
                        <el-radio-button value="customer">{{ $t('client') }}</el-radio-button>
                        <el-radio-button value="supplier">{{ $t('supplier') }}</el-radio-button>
                    </el-radio-group>
                </div>
                <div class="filter-item grow">
                    <label>{{ type === 'customer' ? $t('client') : $t('supplier') }}</label>
                    <el-select
                        v-model="partyId"
                        filterable
                        clearable
                        :placeholder="$t('choose_party')"
                        style="width:100%"
                        @change="reload"
                    >
                        <el-option
                            v-for="party in parties"
                            :key="party.id"
                            :label="party.name"
                            :value="party.id"
                        />
                    </el-select>
                </div>
            </div>
        </el-card>

        <el-empty v-if="!partyId" class="no-print" :description="$t('choose_a_party_to_see_its_statement')">
            <i class="fas fa-file-invoice empty-hint-icon"></i>
        </el-empty>

        <template v-else>
            <el-skeleton v-if="loading" class="no-print" :rows="6" animated />
            <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" class="no-print" />

            <template v-else-if="report">
                <AdminStatGrid :min="200" class="no-print">
                    <el-card shadow="hover" class="stat-card">
                        <span class="stat-label">{{ $t('opening_balance') }}</span>
                        <strong class="stat-value">{{ money(report.opening_balance) }}</strong>
                    </el-card>
                    <el-card shadow="hover" class="stat-card">
                        <span class="stat-label">{{ $t('total_debit_amount') }}</span>
                        <strong class="stat-value amount-debit">{{ money(report.totals.debits) }}</strong>
                    </el-card>
                    <el-card shadow="hover" class="stat-card">
                        <span class="stat-label">{{ $t('total_credit_amount') }}</span>
                        <strong class="stat-value amount-credit">{{ money(report.totals.credits) }}</strong>
                    </el-card>
                    <el-card shadow="hover" class="stat-card closing-card">
                        <span class="stat-label">{{ $t('closing_balance') }}</span>
                        <strong class="stat-value" :class="closingBalanceClass">{{ money(report.closing_balance) }}</strong>
                        <span class="stat-note">{{ balanceNarrative }}</span>
                    </el-card>
                </AdminStatGrid>

                <el-alert
                    v-if="!report.matches_stored_balance"
                    type="warning"
                    show-icon
                    :closable="false"
                    class="mb-4"
                    :title="$t('statement_does_not_match_party_record')"
                >
                    <template #default>
                        {{ $t('statement_mismatch_difference', { amount: money(mismatchDifference) }) }}
                    </template>
                </el-alert>

                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <div class="party-title">
                                <i class="fas fa-receipt text-muted"></i>
                                <span>{{ report.party.name }}</span>
                                <el-tag size="small" effect="plain" :type="type === 'customer' ? 'primary' : 'warning'">
                                    {{ type === 'customer' ? $t('client') : $t('supplier') }}
                                </el-tag>
                            </div>
                            <span class="movements-count">
                                {{ $t('movements_count', { count: report.movements.length }) }}
                            </span>
                        </div>
                    </template>

                    <el-table v-if="report.movements.length" :data="report.movements" stripe style="width:100%" class="print-table">
                        <el-table-column prop="date" :label="$t('date')" width="120" align="center" />
                        <el-table-column :label="$t('document')" min-width="180">
                            <template #default="{ row }">
                                <el-tag size="small" :type="documentTagType(row.type)" effect="plain">{{ row.label }}</el-tag>
                                <span class="mono ms-2">{{ row.number }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('debtor')" width="130" align="right">
                            <template #default="{ row }">
                                <span v-if="row.debit > 0" class="amount-debit">{{ money(row.debit) }}</span>
                                <span v-else class="muted">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('creditor')" width="130" align="right">
                            <template #default="{ row }">
                                <span v-if="row.credit > 0" class="amount-credit">{{ money(row.credit) }}</span>
                                <span v-else class="muted">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('running_balance')" width="150" align="right">
                            <template #default="{ row }">
                                <strong :class="row.balance >= 0 ? 'amount-debit' : 'amount-credit'">{{ money(row.balance) }}</strong>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-else :description="$t('no_movements_in_this_period')" />

                    <div v-if="report.movements.length" class="totals-row">
                        <span>{{ $t('total_debit_amount') }}: <strong class="amount-debit">{{ money(report.totals.debits) }}</strong></span>
                        <span>{{ $t('total_credit_amount') }}: <strong class="amount-credit">{{ money(report.totals.credits) }}</strong></span>
                        <span>{{ $t('closing_balance') }}: <strong :class="closingBalanceClass">{{ money(report.closing_balance) }}</strong></span>
                    </div>
                </el-card>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { accountingReportsApi } from '@/api/accountingReports';
import { customersApi } from '@/api/customers';
import { suppliersApi } from '@/api/suppliers';
import { formatMoney } from '@/utils/currency';

const { t } = useI18n();

const type = ref('customer');
const partyId = ref(null);
const parties = ref([]);
const range = ref([]);
const report = ref(null);
const loading = ref(false);
const error = ref('');

const money = (value) => formatMoney(value);

/**
 * Same helper the sales screens use, so the currency printed here follows the
 * configured base currency rather than a hardcoded symbol.
 */
const dateShortcuts = computed(() => [
    {
        text: t('current_month'),
        value: () => {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            return [start, now];
        },
    },
    {
        text: t('last_month'),
        value: () => {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const end = new Date(now.getFullYear(), now.getMonth(), 0);
            return [start, end];
        },
    },
    {
        text: t('this_year'),
        value: () => {
            const now = new Date();
            return [new Date(now.getFullYear(), 0, 1), now];
        },
    },
]);

const mismatchDifference = computed(() => {
    if (!report.value) return 0;
    return Math.abs((report.value.closing_balance || 0) - (report.value.stored_balance || 0));
});

// A customer's balance is what they owe us — read positive; a supplier's is
// what we owe them, so the same sign means the opposite direction.
const closingBalanceClass = computed(() => {
    if (!report.value) return '';
    return report.value.closing_balance >= 0 ? 'amount-debit' : 'amount-credit';
});

const balanceNarrative = computed(() => {
    if (!report.value) return '';
    const balance = report.value.closing_balance || 0;
    const amount = money(Math.abs(balance));

    if (Math.abs(balance) < 0.005) return t('account_balance_settled');

    if (type.value === 'customer') {
        return balance > 0
            ? t('customer_owes_balance', { amount })
            : t('customer_credit_balance', { amount });
    }

    return balance > 0
        ? t('we_owe_supplier_balance', { amount })
        : t('supplier_owes_us_balance', { amount });
});

const documentTagType = (docType) => {
    const map = {
        invoice: 'primary',
        payment: 'success',
        credit_note: 'warning',
        receipt: 'info',
        landed_cost: 'info',
        return: 'danger',
    };
    return map[docType] || 'info';
};

const loadParties = async () => {
    try {
        const res = type.value === 'customer'
            ? await customersApi.getAll({ per_page: 200 })
            : await suppliersApi.getAll({ per_page: 200 });

        const data = res.data?.data || {};
        parties.value = data.customers || data.suppliers || [];
    } catch (e) {
        error.value = e.response?.data?.message || t('failed_to_load_report');
    }
};

const onTypeChange = async () => {
    partyId.value = null;
    report.value = null;
    await loadParties();
};

const reload = async () => {
    if (!partyId.value) return;

    loading.value = true;
    error.value = '';
    try {
        const res = await accountingReportsApi.partyStatement({
            type: type.value,
            party_id: partyId.value,
            date_from: range.value?.[0] || undefined,
            date_to: range.value?.[1] || undefined,
        });
        report.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const printReport = () => window.print();

onMounted(loadParties);
</script>

<style scoped>
.party-statement {
    font-family: 'Cairo', sans-serif;
}

.filters-panel {
    border-radius: var(--radius-md, 12px);
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

.filter-item.grow {
    flex: 1;
    min-width: 240px;
}

.filter-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
}

.stat-card {
    border-radius: var(--radius-md, 12px);
}

.stat-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.stat-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
}

.stat-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-dark, #1e293b);
}

.stat-note {
    font-size: 0.78rem;
    color: var(--text-muted, #64748b);
    font-weight: 500;
}

.closing-card {
    background: var(--bg-light);
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-weight: 700;
    color: var(--text-dark, #1e293b);
}

.party-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.movements-count {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-muted, #64748b);
}

.mono {
    font-family: monospace;
    font-weight: 700;
}

.ms-2 {
    margin-inline-start: 0.5rem;
}

.muted {
    color: var(--text-muted, #64748b);
}

.amount-debit {
    color: #16a34a;
    font-weight: 600;
}

.amount-credit {
    color: #dc2626;
    font-weight: 600;
}

.totals-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 1.75rem;
    padding-top: 0.85rem;
    margin-top: 0.85rem;
    border-top: 1px solid var(--border-color);
    font-weight: 600;
    color: var(--text-muted, #64748b);
}

.empty-hint-icon {
    font-size: 2.5rem;
    color: var(--text-light, #cbd5e1);
    opacity: 0.6;
}

@media print {
    .no-print {
        display: none !important;
    }
    .print-table {
        width: 100% !important;
    }
}
</style>
