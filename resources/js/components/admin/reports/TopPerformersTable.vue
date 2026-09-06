<template>
    <el-card shadow="hover" class="top-performers-card">
        <template #header>
            <span>{{ title }}</span>
        </template>

        <el-table v-loading="loading" :data="rows" style="width: 100%" stripe>
            <el-table-column :label="$t('rank')" width="80">
                <template #default="{ $index }">
                    <el-tag :type="rankType($index)" :effect="rankEffect($index)" size="small">
                        {{ $index + 1 }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="employee_name" :label="$t('employee')" />
            <el-table-column :prop="countKey" :label="countLabel" />
            <el-table-column :label="$t('total_sales')">
                <template #default="{ row }">
                    <strong>{{ formatMoney(row.total_sales) }}</strong>
                </template>
            </el-table-column>
            <el-table-column :label="averageLabel">
                <template #default="{ row }">{{ formatMoney(row[averageKey]) }}</template>
            </el-table-column>

            <template #empty>
                <span class="table-empty">{{ $t('no_data_for_current_filters') }}</span>
            </template>
        </el-table>
    </el-card>
</template>

<script setup>
import { formatMoney as formatMoneyWith } from '@/utils/currency';

/**
 * The ranked employee table both the orders and invoices tabs end on —
 * identical shape, only the count/average field names differ (orders count
 * orders and average order value; invoices count invoices and average
 * invoice value).
 */
defineProps({
    title: { type: String, required: true },
    loading: { type: Boolean, default: false },
    rows: { type: Array, default: () => [] },
    countKey: { type: String, required: true },
    countLabel: { type: String, required: true },
    averageKey: { type: String, required: true },
    averageLabel: { type: String, required: true },
});

const formatMoney = (value) => formatMoneyWith(value);

/**
 * The podium reads top-down, not as an alert.
 *
 * First place used to be tagged 'danger' — red, the colour this admin uses
 * everywhere else for a loss or a failure — with second in warning amber and
 * third in green. So the best performer on the page was the one flagged in
 * red, and the ranking ran through three unrelated hues in no order the eye
 * could follow. One colour, descending in emphasis, says "ranked" instead.
 */
const rankType = (index) => (index < 3 ? 'primary' : 'info');

/** Emphasis carries the order the hue no longer has to: solid, light, plain. */
const rankEffect = (index) => {
    if (index === 0) return 'dark';
    return index < 3 ? 'light' : 'plain';
};
</script>

<style scoped>
.top-performers-card {
    border-radius: 1rem;
}

.table-empty {
    color: #94a3b8;
    font-size: 0.85rem;
}
</style>
