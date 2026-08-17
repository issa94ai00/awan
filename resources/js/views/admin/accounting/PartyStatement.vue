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
                    @change="reload"
                />
            </template>
        </AdminPageHeader>

        <el-card shadow="hover" class="filters-panel mb-4">
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

        <el-empty v-if="!partyId" :description="$t('choose_a_party_to_see_its_statement')" />

        <template v-else>
            <el-skeleton v-if="loading" :rows="6" animated />
            <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

            <template v-else-if="report">
                <el-alert
                    v-if="!report.matches_stored_balance"
                    type="warning"
                    show-icon
                    :closable="false"
                    class="mb-4"
                    :title="$t('statement_does_not_match_party_record')"
                />

                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-receipt text-muted"></i> {{ report.party.name }}</span>
                            <div class="summary">
                                <span>{{ $t('opening_balance') }}: <strong>{{ money(report.opening_balance) }}</strong></span>
                                <span>{{ $t('closing_balance') }}: <strong>{{ money(report.closing_balance) }}</strong></span>
                            </div>
                        </div>
                    </template>

                    <el-table v-if="report.movements.length" :data="report.movements" stripe style="width:100%">
                        <el-table-column prop="date" :label="$t('date')" width="120" align="center" />
                        <el-table-column :label="$t('document')" min-width="160">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ row.label }}</el-tag>
                                <span class="mono ms-2">{{ row.number }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('debtor')" width="130" align="right">
                            <template #default="{ row }">
                                <span v-if="row.debit > 0">{{ money(row.debit) }}</span>
                                <span v-else class="muted">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('creditor')" width="130" align="right">
                            <template #default="{ row }">
                                <span v-if="row.credit > 0">{{ money(row.credit) }}</span>
                                <span v-else class="muted">—</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('running_balance')" width="140" align="right">
                            <template #default="{ row }">
                                <strong>{{ money(row.balance) }}</strong>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-else :description="$t('no_movements_in_this_period')" />
                </el-card>
            </template>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { accountingReportsApi } from '@/api/accountingReports';
import { customersApi } from '@/api/customers';
import { suppliersApi } from '@/api/suppliers';

const { t } = useI18n();

const type = ref('customer');
const partyId = ref(null);
const parties = ref([]);
const range = ref([]);
const report = ref(null);
const loading = ref(false);
const error = ref('');

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

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

onMounted(loadParties);
</script>

<style scoped>
.party-statement {
    font-family: 'Cairo', sans-serif;
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

.filter-item.grow {
    flex: 1;
    min-width: 240px;
}

.filter-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
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
    color: var(--text-dark);
}

.summary {
    display: flex;
    gap: 1.25rem;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--text-muted);
}

.mono {
    font-family: monospace;
    font-weight: 700;
}

.ms-2 {
    margin-inline-start: 0.5rem;
}

.muted {
    color: var(--text-muted);
}
</style>
