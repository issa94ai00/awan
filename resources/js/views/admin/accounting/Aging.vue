<template>
    <div class="accounting-page accounting-aging">
        <AdminPageHeader
            icon="fas fa-hourglass-half text-primary"
            :title="$t('aging_report')"
            :subtitle="$t('aging_report_subtitle')"
        >
            <template #actions>
                <el-date-picker
                    v-model="asOf"
                    type="date"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    :placeholder="$t('as_of_date')"
                    @change="reload"
                />
                <el-button :icon="Refresh" :loading="loading" @click="reload" />
            </template>
        </AdminPageHeader>

        <el-skeleton v-if="loading" :rows="6" animated />
        <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

        <template v-else>
            <el-card
                v-for="section in sections"
                :key="section.key"
                shadow="hover"
                class="table-panel mb-4"
            >
                <template #header>
                    <div class="card-header">
                        <span><i :class="section.icon" class="text-muted"></i> {{ section.title }}</span>

                        <!-- The reconciliation is the point of the report: a list
                             of who owes what is only worth something if it adds
                             up to the account the ledger keeps for it. -->
                        <el-tag
                            v-if="section.data?.control_account"
                            :type="section.data.reconciled === false ? 'danger' : 'success'"
                            effect="light"
                        >
                            {{ section.data.reconciled === false
                                ? $t('not_reconciled_with_ledger', { amount: money(section.data.difference) })
                                : $t('reconciled_with_ledger') }}
                        </el-tag>
                    </div>
                </template>

                <el-table
                    v-if="section.data?.parties?.length"
                    :data="section.data.parties"
                    stripe
                    style="width:100%"
                >
                    <el-table-column prop="name" :label="$t('name')" min-width="180" />

                    <el-table-column
                        v-for="bucket in buckets"
                        :key="bucket"
                        :label="$t('bucket_' + bucket)"
                        width="130"
                        align="right"
                    >
                        <template #default="{ row }">
                            <span v-if="Number(row.buckets?.[bucket] || 0) > 0" :class="{ 'overdue-hard': bucket === 'over_90' }">
                                {{ money(row.buckets[bucket]) }}
                            </span>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('total')" width="140" align="right">
                        <template #default="{ row }">
                            <strong>{{ money(row.total) }}</strong>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('nothing_outstanding')" :image-size="80" />

                <div v-if="section.data?.parties?.length" class="totals-row">
                    <span>{{ $t('subsidiary_total') }}: <strong>{{ money(section.data.total) }}</strong></span>
                    <span v-if="section.data.control_account">
                        {{ $t('control_account') }} {{ section.data.control_account.code }}:
                        <strong>{{ money(section.data.control_account.balance) }}</strong>
                    </span>
                </div>
            </el-card>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { accountingReportsApi } from '@/api/accountingReports';

const { t } = useI18n();

const asOf = ref(new Date().toISOString().slice(0, 10));
const report = ref(null);
const loading = ref(false);
const error = ref('');

// The server decides which buckets exist, so adding one there does not mean
// editing a hardcoded list here.
const buckets = computed(() => report.value?.buckets || ['current', '1_30', '31_60', '61_90', 'over_90']);

const sections = computed(() => [
    {
        key: 'receivables',
        title: t('receivables_aging'),
        icon: 'fas fa-hand-holding-dollar',
        data: report.value?.receivables,
    },
    {
        key: 'payables',
        title: t('payables_aging'),
        icon: 'fas fa-file-invoice-dollar',
        data: report.value?.payables,
    },
]);

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await accountingReportsApi.aging({ as_of: asOf.value });
        report.value = res.data?.data || null;
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

onMounted(reload);
</script>

<style scoped>
.accounting-aging {
    font-family: 'Cairo', sans-serif;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-weight: 700;
    color: var(--text-dark);
}

.totals-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.85rem;
    margin-top: 0.85rem;
    border-top: 1px solid var(--border-color);
    font-weight: 600;
}

.overdue-hard {
    color: #b91c1c;
    font-weight: 700;
}

.muted {
    color: var(--text-muted);
}
</style>
