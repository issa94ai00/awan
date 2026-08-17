<template>
    <div class="accounting-page cash-flow">
        <AdminPageHeader
            icon="fas fa-water text-primary"
            :title="$t('cash_flow_statement')"
            :subtitle="$t('cash_flow_subtitle')"
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

        <el-skeleton v-if="loading" :rows="6" animated />
        <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

        <template v-else-if="report">
            <!-- Opening, the change, and closing — a cash flow statement that
                 does not tie those together is a list, not a statement. -->
            <AdminStatGrid>
                <el-card shadow="hover" class="stat-card">
                    <p>{{ $t('opening_balance') }}</p>
                    <h3>{{ money(report.opening_balance) }}</h3>
                </el-card>
                <el-card shadow="hover" class="stat-card">
                    <p>{{ $t('net_change_in_cash') }}</p>
                    <h3 :class="report.net_change >= 0 ? 'up' : 'down'">
                        {{ report.net_change >= 0 ? '+' : '−' }}{{ money(Math.abs(report.net_change)) }}
                    </h3>
                </el-card>
                <el-card shadow="hover" class="stat-card">
                    <p>{{ $t('closing_balance') }}</p>
                    <h3>{{ money(report.closing_balance) }}</h3>
                    <small v-if="Math.abs(report.closing_balance - report.stored_balance) > 0.005" class="warn">
                        {{ $t('does_not_tie_to_accounts') }}
                    </small>
                </el-card>
            </AdminStatGrid>

            <el-card
                v-for="section in sections"
                :key="section.key"
                shadow="hover"
                class="table-panel mt-4"
            >
                <template #header>
                    <div class="card-header">
                        <span><i :class="section.icon" class="text-muted"></i> {{ section.title }}</span>
                        <strong :class="section.data.net >= 0 ? 'up' : 'down'">
                            {{ section.data.net >= 0 ? '+' : '−' }}{{ money(Math.abs(section.data.net)) }}
                        </strong>
                    </div>
                </template>

                <p class="section-note">{{ section.note }}</p>

                <el-table v-if="section.rows.length" :data="section.rows" stripe style="width:100%">
                    <el-table-column prop="label" :label="$t('the_reason')" min-width="220" />
                    <el-table-column :label="$t('direction')" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.direction === 'in' ? 'success' : 'danger'" effect="light">
                                {{ row.direction === 'in' ? $t('cash_in') : $t('cash_out') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('amount')" width="150" align="right">
                        <template #default="{ row }">{{ money(row.amount) }}</template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_movement_in_this_activity')" :image-size="70" />
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

const activityMeta = computed(() => ({
    operating: {
        title: t('operating_activities'),
        note: t('operating_activities_note'),
        icon: 'fas fa-cart-shopping',
    },
    investing: {
        title: t('investing_activities'),
        note: t('investing_activities_note'),
        icon: 'fas fa-building-columns',
    },
    financing: {
        title: t('financing_activities'),
        note: t('financing_activities_note'),
        icon: 'fas fa-hand-holding-dollar',
    },
}));

/** Inflows and outflows shown as one list per activity, largest first. */
const sections = computed(() => {
    if (!report.value) return [];

    return ['operating', 'investing', 'financing'].map((key) => {
        const data = report.value.activities[key] || { inflows: [], outflows: [], net: 0 };

        const rows = [
            ...(data.inflows || []).map((row) => ({ ...row, direction: 'in' })),
            ...(data.outflows || []).map((row) => ({ ...row, direction: 'out' })),
        ].sort((a, b) => b.amount - a.amount);

        return { key, ...activityMeta.value[key], data, rows };
    });
});

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await accountingReportsApi.cashFlow({
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
.cash-flow {
    font-family: 'Cairo', sans-serif;
}

.stat-card p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-card h3 {
    margin: 0.35rem 0 0;
    font-size: 1.6rem;
    font-weight: 800;
}

.up { color: #1b6b4c; }
.down { color: #9b2c2c; }

.warn {
    color: #8a6212;
    font-size: 0.78rem;
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

.section-note {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: 0.88rem;
}
</style>
