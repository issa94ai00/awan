<template>
    <div class="picking-detail-page">
        <div v-if="loading && !list" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <div v-else-if="list">
            <!-- Header: what is being picked, and for whom -->
            <div class="page-header">
                <div class="page-title">
                    <el-button text @click="goBack"><i class="fas fa-arrow-right"></i></el-button>
                    <div>
                        <h1><i class="fas fa-clipboard-list"></i> {{ list.list_number }}</h1>
                        <p>
                            <span v-if="list.order_number">
                                طلب <strong class="order-link" @click="goToOrder">{{ list.order_number }}</strong>
                            </span>
                            <span v-if="list.customer_name"> — {{ list.customer_name }}</span>
                            <span> · {{ list.warehouse_name }}</span>
                        </p>
                    </div>
                </div>
                <div class="header-actions">
                    <el-tag :type="statusType(list.status)" effect="dark" size="large">{{ list.status_text }}</el-tag>
                    <el-tag :type="priorityType(list.priority)" effect="plain" size="large">{{ list.priority_text }}</el-tag>
                </div>
            </div>

            <!-- Progress: the one number a picker checks -->
            <el-card shadow="never" class="progress-card">
                <div class="progress-head">
                    <div>
                        <strong>{{ list.picked_items }} من {{ list.total_items }} صنف</strong>
                        <span v-if="shortCount" class="short-note">
                            <i class="fas fa-triangle-exclamation"></i> {{ shortCount }} صنف نقص عن المطلوب
                        </span>
                    </div>
                    <span class="progress-pct">{{ list.progress }}٪</span>
                </div>
                <el-progress
                    :percentage="list.progress"
                    :stroke-width="12"
                    :status="list.progress === 100 ? 'success' : undefined"
                    :show-text="false"
                />

                <div class="progress-actions">
                    <el-button v-if="list.can_start" type="primary" :loading="saving" @click="start">
                        <i class="fas fa-play"></i> بدء السحب
                    </el-button>
                    <el-button v-if="list.can_complete" type="success" :loading="saving" @click="complete">
                        <i class="fas fa-check"></i> إنهاء التجهيز
                    </el-button>
                    <el-button v-if="list.can_cancel" type="danger" plain :loading="saving" @click="cancel">
                        <i class="fas fa-ban"></i> إلغاء القائمة
                    </el-button>
                    <span v-if="list.picker_name" class="picker-note">
                        <i class="fas fa-user"></i> المُجهِّز: {{ list.picker_name }}
                    </span>
                </div>

                <el-alert
                    v-if="list.can_start"
                    type="info"
                    :closable="false"
                    show-icon
                    class="mt-3"
                    title="ابدأ السحب أولاً لتفعيل تسجيل الكميات — القائمة تُسند إليك عند البدء."
                />
            </el-card>

            <!-- The lines, in walking order -->
            <el-card shadow="never" class="mt-3">
                <template #header>
                    <div class="card-header">
                        <span><i class="fas fa-boxes-stacked"></i> أصناف السحب</span>
                        <el-input
                            v-model="scan"
                            placeholder="امسح الباركود أو اكتبه…"
                            clearable
                            :disabled="!isPicking"
                            class="scan-input"
                            @keyup.enter="onScan"
                        >
                            <template #prefix><i class="fas fa-barcode"></i></template>
                        </el-input>
                    </div>
                </template>

                <el-table :data="list.items" stripe :row-class-name="rowClass">
                    <el-table-column label="#" width="55" align="center">
                        <template #default="{ $index }">{{ $index + 1 }}</template>
                    </el-table-column>

                    <el-table-column label="الصنف" min-width="220">
                        <template #default="{ row }">
                            <strong>{{ row.product_name || '—' }}</strong>
                            <p class="row-sub">
                                {{ row.sku || '—' }}
                                <span v-if="row.bin_code"> · رف {{ row.bin_code }}</span>
                                <span v-if="row.bin_location"> ({{ row.bin_location }})</span>
                            </p>
                        </template>
                    </el-table-column>

                    <el-table-column label="المطلوب" width="90" align="center">
                        <template #default="{ row }"><strong>{{ row.quantity_to_pick }}</strong></template>
                    </el-table-column>

                    <el-table-column label="المسحوب" width="90" align="center">
                        <template #default="{ row }">
                            <span :class="{ 'text-success': row.quantity_picked >= row.quantity_to_pick, 'text-warning': row.quantity_picked > 0 && row.quantity_picked < row.quantity_to_pick }">
                                {{ row.quantity_picked }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column label="الحالة" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="itemStatusType(row.status)" size="small" effect="light">{{ row.status_text }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="تسجيل السحب" width="230" align="center">
                        <template #default="{ row }">
                            <div v-if="row.status === 'pending' && isPicking" class="pick-cell">
                                <el-input-number
                                    v-model="draft[row.id]"
                                    :min="1"
                                    :max="row.quantity_to_pick"
                                    size="small"
                                    controls-position="right"
                                    style="width: 110px"
                                />
                                <el-button size="small" type="primary" :loading="saving" @click="pick(row)">
                                    سحب
                                </el-button>
                            </div>
                            <span v-else-if="row.status === 'pending'" class="muted">ابدأ السحب أولاً</span>
                            <span v-else class="muted">{{ row.picked_at || '—' }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <el-empty v-else description="قائمة التجهيز غير موجودة" />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { wmsService } from '@/services/wms';

const route = useRoute();
const router = useRouter();

const list = ref(null);
const loading = ref(false);
const saving = ref(false);
const scan = ref('');

// Per-row quantity being entered. Keyed by item id so a re-render does not
// scramble which number belongs to which line.
const draft = reactive({});

const isPicking = computed(() => list.value?.status === 'in_progress');
const shortCount = computed(() => (list.value?.items || []).filter((i) => i.status === 'short').length);

const statusType = (s) => ({ pending: 'warning', in_progress: 'primary', completed: 'success', cancelled: 'danger' }[s] || 'info');
const priorityType = (p) => ({ low: 'info', normal: 'primary', high: 'warning', urgent: 'danger' }[p] || 'info');
const itemStatusType = (s) => ({ pending: 'info', picked: 'success', short: 'warning', cancelled: 'danger' }[s] || 'info');

const rowClass = ({ row }) => (row.status === 'short' ? 'row-short' : '');

/** Applies whatever the server returned; every action answers with the full list. */
const apply = (payload, message) => {
    list.value = payload;
    seedDrafts();
    if (message) ElMessage.success(message);
};

/** Defaults each pending line to the full required quantity — the common case. */
const seedDrafts = () => {
    (list.value?.items || []).forEach((item) => {
        if (item.status === 'pending') draft[item.id] = item.quantity_to_pick;
    });
};

const load = async () => {
    loading.value = true;
    try {
        const res = await wmsService.getPickingList(route.params.id);
        list.value = res.data?.data?.list || null;
        seedDrafts();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || 'تعذّر تحميل قائمة التجهيز.');
    } finally {
        loading.value = false;
    }
};

const run = async (fn, message) => {
    saving.value = true;
    try {
        const res = await fn();
        apply(res.data?.data?.list, res.data?.message || message);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || 'تعذّر تنفيذ العملية.');
    } finally {
        saving.value = false;
    }
};

const start = () => run(() => wmsService.startPicking(list.value.id));

const pick = (row) => {
    const qty = draft[row.id] ?? row.quantity_to_pick;
    return run(() => wmsService.pickItem(row.id, { quantity: qty, verified: false }));
};

const complete = async () => {
    const pending = (list.value.items || []).filter((i) => i.status === 'pending').length;
    if (pending) {
        ElMessage.warning(`ما زال ${pending} صنف بانتظار السحب.`);
        return;
    }

    try {
        await ElMessageBox.confirm(
            shortCount.value
                ? `${shortCount.value} صنف سُحب ناقصاً عن المطلوب. إنهاء التجهيز بالكميات المسحوبة فعلاً؟`
                : 'إنهاء التجهيز؟ الأصناف تصبح جاهزة للشحن.',
            'إنهاء التجهيز',
            { type: shortCount.value ? 'warning' : 'info', confirmButtonText: 'إنهاء', cancelButtonText: 'رجوع' }
        );
    } catch {
        return;
    }

    return run(() => wmsService.completePicking(list.value.id));
};

const cancel = async () => {
    try {
        await ElMessageBox.confirm(
            'إلغاء قائمة التجهيز؟ لن يُطلب من أحد سحب هذه الأصناف.',
            'إلغاء القائمة',
            { type: 'warning', confirmButtonText: 'إلغاء القائمة', cancelButtonText: 'رجوع' }
        );
    } catch {
        return;
    }

    return run(() => wmsService.cancelPicking(list.value.id));
};

/**
 * Scanning is how picking is actually done: the barcode identifies the line, so
 * the picker never hunts for the right row on a phone or a terminal.
 */
const onScan = () => {
    const code = scan.value.trim();
    if (!code) return;

    const match = (list.value.items || []).find(
        (i) => i.status === 'pending' && (i.barcode === code || i.sku === code)
    );

    if (!match) {
        ElMessage.warning('لا يوجد صنف مطابق بانتظار السحب.');
        return;
    }

    scan.value = '';
    pick(match);
};

const goBack = () => router.push('/admin/wms/picking');

const goToOrder = () => {
    if (list.value?.sales_order_id) router.push('/admin/sales/sales-orders');
};

onMounted(load);
</script>

<style scoped>
.picking-detail-page { padding: 0; font-family: 'Cairo', sans-serif; }

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title { display: flex; align-items: center; gap: 0.5rem; }
.page-title h1 { margin: 0; font-size: 1.35rem; font-weight: 700; display: flex; align-items: center; gap: 0.6rem; }
.page-title h1 i { color: var(--el-color-primary); }
.page-title p { margin: 0.3rem 0 0; color: var(--text-muted); font-size: 0.85rem; }
.header-actions { display: flex; gap: 0.5rem; align-items: center; }

.order-link { color: var(--el-color-primary); cursor: pointer; }
.order-link:hover { text-decoration: underline; }

.progress-card { border-radius: 12px; }
.progress-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem; gap: 1rem; flex-wrap: wrap; }
.progress-pct { font-size: 1.3rem; font-weight: 800; }
.short-note { margin-inline-start: 0.75rem; font-size: 0.8rem; color: var(--el-color-warning); }

.progress-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
.picker-note { font-size: 0.8rem; color: var(--text-muted); margin-inline-start: auto; }

.card-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; font-weight: 700; }
.card-header i { color: var(--el-color-primary); }
.scan-input { width: 260px; }

.row-sub { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--text-muted); }
.pick-cell { display: flex; gap: 0.4rem; align-items: center; justify-content: center; }
.muted { color: var(--text-muted); font-size: 0.78rem; }

.text-success { color: var(--el-color-success); font-weight: 700; }
.text-warning { color: var(--el-color-warning); font-weight: 700; }

/* A short pick is not an error, but it must stay visible after it scrolls by. */
:deep(.row-short) { background: #fffbeb !important; }

.loading-state { padding: 2rem; }
.mt-3 { margin-top: 0.75rem; }
</style>
