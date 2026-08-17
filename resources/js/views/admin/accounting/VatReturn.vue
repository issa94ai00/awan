<template>
    <div class="accounting-page vat-return">
        <AdminPageHeader
            icon="fas fa-percent text-primary"
            :title="$t('vat_return')"
            :subtitle="$t('vat_return_subtitle')"
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
                <el-button :icon="Refresh" :loading="loading" @click="reload" />
            </template>
        </AdminPageHeader>

        <el-skeleton v-if="loading" :rows="5" animated />
        <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

        <template v-else-if="report">
            <AdminStatGrid>
                <el-card shadow="hover" class="stat-card">
                    <p>{{ $t('output_tax') }}</p>
                    <h3>{{ money(report.output_tax.amount) }}</h3>
                    <small>{{ report.output_tax.account?.code }} — {{ report.output_tax.account?.name }}</small>
                </el-card>
                <el-card shadow="hover" class="stat-card">
                    <p>{{ $t('input_tax') }}</p>
                    <h3>{{ money(report.input_tax.amount) }}</h3>
                    <small>{{ report.input_tax.account?.code }} — {{ report.input_tax.account?.name }}</small>
                </el-card>
                <el-card shadow="hover" class="stat-card" :class="report.direction">
                    <p>{{ report.direction === 'payable' ? $t('tax_due_to_authority') : $t('tax_recoverable') }}</p>
                    <h3>{{ money(Math.abs(report.net)) }}</h3>
                    <small>{{ $t('sales_base') }}: {{ money(report.sales_base) }}</small>
                </el-card>
            </AdminStatGrid>

            <!-- The documents of the period, added up independently of the
                 ledger. When the two disagree something posted wrong, and that
                 is worth knowing before the return is filed rather than after. -->
            <el-card shadow="hover" class="table-panel mt-4">
                <template #header>
                    <div class="card-header">
                        <span><i class="fas fa-scale-balanced text-muted"></i> {{ $t('documents_vs_ledger') }}</span>
                    </div>
                </template>

                <el-table :data="reconciliationRows" stripe style="width:100%">
                    <el-table-column prop="label" :label="$t('the_side')" min-width="160" />
                    <el-table-column :label="$t('per_documents')" width="160" align="right">
                        <template #default="{ row }">{{ money(row.documents) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('per_ledger')" width="160" align="right">
                        <template #default="{ row }">{{ money(row.ledger) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('difference')" width="160" align="right">
                        <template #default="{ row }">
                            <el-tag :type="row.matches ? 'success' : 'danger'" effect="light">
                                {{ row.matches ? $t('matching') : money(row.difference) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { accountingReportsApi } from '@/api/accountingReports';

const { t } = useI18n();

const range = ref([]);
const report = ref(null);
const loading = ref(false);
const error = ref('');

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const reconciliationRows = computed(() => {
    if (!report.value) return [];

    const r = report.value.reconciliation;

    return [
        {
            label: t('output_tax'),
            documents: report.value.documents.invoice_tax,
            ledger: report.value.output_tax.amount,
            difference: r.output_difference,
            matches: r.output_matches,
        },
        {
            label: t('input_tax'),
            documents: report.value.documents.receipt_tax,
            ledger: report.value.input_tax.amount,
            difference: r.input_difference,
            matches: r.input_matches,
        },
    ];
});

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await accountingReportsApi.vatReturn({
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

onMounted(reload);
</script>

<style scoped>
.vat-return {
    font-family: 'Cairo', sans-serif;
}

.stat-card p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-card h3 {
    margin: 0.35rem 0 0.2rem;
    font-size: 1.7rem;
    font-weight: 800;
}

.stat-card small {
    color: var(--text-muted);
    font-size: 0.78rem;
}

.stat-card.payable h3 {
    color: #b91c1c;
}

.stat-card.refundable h3 {
    color: #047857;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}
</style>
