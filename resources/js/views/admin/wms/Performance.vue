<template>
    <div class="performance-page">
        <AdminPageHeader icon="fas fa-chart-line" :title="$t('wms.performance')">
            <template #actions>
                <el-select v-model="selectedWarehouse" :placeholder="$t('all_warehouses')" clearable style="width: 200px" @change="loadAll">
                    <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="wh.name" />
                </el-select>
                <el-date-picker
                    v-model="dateRange"
                    type="daterange"
                    value-format="YYYY-MM-DD"
                    :range-separator="$t('common.to')"
                    :start-placeholder="$t('common.start_date')"
                    :end-placeholder="$t('common.end_date')"
                    style="width: 260px"
                    @change="loadMetrics"
                />
            </template>
        </AdminPageHeader>

        <div v-if="loading" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <template v-else>
            <el-alert
                type="info" :closable="false" show-icon class="mb-3"
                :title="$t('wms.performance_period_hint', { from: metrics.from_date, to: metrics.to_date })"
            />

            <div class="stat-grid mb-3">
                <el-card shadow="never" class="stat-card stat-blue">
                    <span class="stat-label">{{ $t('wms.picking_accuracy') }}</span>
                    <strong class="stat-value">{{ formatPct(metrics.picking_accuracy) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-orange">
                    <span class="stat-label">{{ $t('wms.cycle_count_accuracy') }}</span>
                    <strong class="stat-value">{{ formatPct(metrics.cycle_count_accuracy) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-purple">
                    <span class="stat-label">{{ $t('wms.total_units_picked') }}</span>
                    <strong class="stat-value">{{ formatNumber(metrics.total_units_picked) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-green">
                    <span class="stat-label">{{ $t('wms.completed_activity') }}</span>
                    <strong class="stat-value">
                        {{ metrics.completed_picking_lists }} / {{ metrics.completed_packing_lists }} / {{ metrics.completed_cycle_counts }}
                    </strong>
                    <span class="stat-sub">{{ $t('wms.picking_packing_counts_short') }}</span>
                </el-card>
            </div>

            <div class="two-col mb-3">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('wms.average_picking_time') }}</span>
                            <el-tag>{{ metrics.average_picking_time || 0 }} {{ $t('common.minutes') }}</el-tag>
                        </div>
                    </template>
                    <el-progress :percentage="timePercentage(metrics.average_picking_time, 30)" :status="timeStatus(metrics.average_picking_time, 30)" />
                </el-card>
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('wms.average_packing_time') }}</span>
                            <el-tag>{{ metrics.average_packing_time || 0 }} {{ $t('common.minutes') }}</el-tag>
                        </div>
                    </template>
                    <el-progress :percentage="timePercentage(metrics.average_packing_time, 25)" :status="timeStatus(metrics.average_packing_time, 25)" />
                </el-card>
            </div>

            <el-card shadow="never">
                <template #header>{{ $t('wms.performance_trends') }}</template>
                <el-empty v-if="!hasTrendData" :description="$t('wms.no_trend_data')" :image-size="60" />
                <div v-show="hasTrendData" ref="chartRef" class="trend-chart"></div>
            </el-card>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'
import { formatNumber as formatCount } from '@/utils/currency'
import { wmsService } from '@/services/wms'
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue'

const { t } = useI18n()
const loading = ref(true)
const selectedWarehouse = ref(null)
const dateRange = ref([])
const warehouses = ref([])
const metrics = ref({
    picking_accuracy: null,
    cycle_count_accuracy: null,
    average_picking_time: 0,
    average_packing_time: 0,
    total_units_picked: 0,
    completed_picking_lists: 0,
    completed_packing_lists: 0,
    completed_cycle_counts: 0,
    from_date: '',
    to_date: '',
})
const trends = ref({ labels: [], picking_accuracy: [], cycle_count_accuracy: [] })
const chartRef = ref(null)
let chartInstance = null

const hasTrendData = computed(() => trends.value.labels.length > 0 && (
    trends.value.picking_accuracy.some((v) => v !== null) || trends.value.cycle_count_accuracy.some((v) => v !== null)
))

const formatPct = (value) => (value === null || value === undefined ? t('wms.no_data_short') : `${value}%`)
const formatNumber = (num) => (num === null || num === undefined ? '—' : formatCount(num))

const timePercentage = (value, max) => Math.min(((value || 0) / max) * 100, 100)
const timeStatus = (value, max) => {
    const pct = timePercentage(value, max)
    if (pct < 50) return 'success'
    if (pct < 80) return 'warning'
    return 'exception'
}

const loadWarehouses = async () => {
    try {
        const res = await wmsService.getWarehouses()
        warehouses.value = res.data?.data || res.data || []
    } catch {
        /* the filter simply stays empty */
    }
}

const loadMetrics = async () => {
    try {
        const params = {}
        if (selectedWarehouse.value) params.warehouse_id = selectedWarehouse.value
        if (dateRange.value?.length === 2) {
            params.from_date = dateRange.value[0]
            params.to_date = dateRange.value[1]
        }
        const res = await wmsService.getPerformanceMetrics(params)
        metrics.value = res.data
    } catch {
        ElMessage.error(t('common.load_error'))
    }
}

const loadTrends = async () => {
    try {
        const params = { months: 6 }
        if (selectedWarehouse.value) params.warehouse_id = selectedWarehouse.value
        const res = await wmsService.getPerformanceTrends(params)
        trends.value = res.data
        await nextTick()
        renderChart()
    } catch {
        ElMessage.error(t('common.load_error'))
    }
}

const loadAll = () => Promise.all([loadMetrics(), loadTrends()])

const renderChart = () => {
    if (!chartRef.value || !hasTrendData.value) return
    if (chartInstance) chartInstance.dispose()
    chartInstance = echarts.init(chartRef.value)

    chartInstance.setOption({
        tooltip: { trigger: 'axis' },
        legend: { data: [t('wms.picking_accuracy'), t('wms.cycle_count_accuracy')] },
        xAxis: { type: 'category', data: trends.value.labels },
        yAxis: { type: 'value', max: 100, axisLabel: { formatter: '{value}%' } },
        series: [
            { name: t('wms.picking_accuracy'), type: 'line', connectNulls: true, data: trends.value.picking_accuracy },
            { name: t('wms.cycle_count_accuracy'), type: 'line', connectNulls: true, data: trends.value.cycle_count_accuracy },
        ],
    })
}

onMounted(async () => {
    loading.value = true
    await loadWarehouses()
    await loadAll()
    loading.value = false

    window.addEventListener('resize', () => chartInstance?.resize())
})
</script>

<style scoped>
.performance-page { font-family: 'Cairo', sans-serif; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
.stat-card { border-radius: 12px; border-inline-start: 4px solid var(--el-color-info); }
.stat-card :deep(.el-card__body) { display: flex; flex-direction: column; gap: 0.3rem; }
.stat-blue { border-inline-start-color: var(--el-color-primary); }
.stat-orange { border-inline-start-color: #f97316; }
.stat-purple { border-inline-start-color: #8b5cf6; }
.stat-green { border-inline-start-color: var(--el-color-success); }
.stat-label { font-size: 0.8rem; color: var(--text-muted); }
.stat-value { font-size: 1.4rem; font-weight: 800; }
.stat-sub { font-size: 0.7rem; color: var(--text-muted); }

.two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; }
.card-header { display: flex; justify-content: space-between; align-items: center; }

.trend-chart { height: 320px; }
.loading-state { padding: 2rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
