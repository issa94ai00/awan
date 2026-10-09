<template>
    <CollapsibleCard
        id="top-performers"
        :title="title"
        :count="rows.length || null"
        class="top-performers-card"
        @active-change="emit('active-change', $event)"
    >
        <el-table v-loading="loading" :data="rows" style="width: 100%" stripe>
            <el-table-column :label="$t('rank')" width="90" align="center">
                <template #default="{ $index }">
                    <span v-if="$index === 0" class="podium-badge podium-1" title="1st Place">
                        <i class="fas fa-trophy"></i> 1
                    </span>
                    <span v-else-if="$index === 1" class="podium-badge podium-2" title="2nd Place">
                        <i class="fas fa-medal"></i> 2
                    </span>
                    <span v-else-if="$index === 2" class="podium-badge podium-3" title="3rd Place">
                        <i class="fas fa-medal"></i> 3
                    </span>
                    <span v-else class="podium-badge podium-other">
                        {{ $index + 1 }}
                    </span>
                </template>
            </el-table-column>

            <el-table-column prop="employee_name" :label="$t('employee')" min-width="150">
                <template #default="{ row }">
                    <div class="employee-cell">
                        <span class="employee-avatar">{{ getInitials(row.employee_name) }}</span>
                        <strong class="employee-name">{{ row.employee_name || '-' }}</strong>
                    </div>
                </template>
            </el-table-column>

            <el-table-column :prop="countKey" :label="countLabel" width="110" align="center">
                <template #default="{ row }">
                    <span class="count-pill">{{ Number(row[countKey] || 0).toLocaleString() }}</span>
                </template>
            </el-table-column>

            <el-table-column :label="$t('total_sales')" min-width="140">
                <template #default="{ row }">
                    <strong class="sales-amount">{{ formatMoney(row.total_sales) }}</strong>
                </template>
            </el-table-column>

            <el-table-column :label="averageLabel" min-width="140">
                <template #default="{ row }">
                    <span class="average-amount">{{ formatMoney(row[averageKey]) }}</span>
                </template>
            </el-table-column>

            <template #empty>
                <span class="table-empty">{{ $t('no_data_for_current_filters') }}</span>
            </template>
        </el-table>
    </CollapsibleCard>
</template>

<script setup>
import { formatMoney as formatMoneyWith } from '@/utils/currency';
import CollapsibleCard from '@/components/admin/reports/CollapsibleCard.vue';

defineProps({
    title: { type: String, required: true },
    loading: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    countKey: { type: String, required: true },
    countLabel: { type: String, required: true },
    averageKey: { type: String, required: true },
    averageLabel: { type: String, required: true },
});

const emit = defineEmits(['active-change']);

const formatMoney = (value) => formatMoneyWith(value || 0);

const getInitials = (name) => {
    if (!name) return 'EMP';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    return name.slice(0, 2).toUpperCase();
};
</script>

<style scoped>
.top-performers-card {
    border-radius: 1rem;
    border: 1px solid #edf2f7;
}

.podium-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.podium-1 {
    background: #fef9c3;
    color: #a16207;
    border: 1px solid #fde047;
    box-shadow: 0 1px 4px rgba(234, 179, 8, 0.2);
}

.podium-2 {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.podium-3 {
    background: #ffedd5;
    color: #c2410c;
    border: 1px solid #fed7aa;
}

.podium-other {
    background: #f8fafc;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
    font-weight: 600;
}

.employee-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.employee-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    font-size: 0.72rem;
    font-weight: 700;
    flex-shrink: 0;
}

.employee-name {
    color: #1e293b;
    font-weight: 600;
}

.count-pill {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    background: #f1f5f9;
    color: #334155;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.82rem;
}

.sales-amount {
    color: #0f172a;
    font-weight: 700;
}

.average-amount {
    color: #475569;
    font-size: 0.88rem;
}

.table-empty {
    color: #94a3b8;
    font-size: 0.85rem;
}

:deep(.el-table) {
    font-variant-numeric: tabular-nums;
}
</style>
