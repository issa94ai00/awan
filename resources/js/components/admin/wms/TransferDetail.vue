<template>
    <el-drawer
        :model-value="modelValue"
        :size="drawerSize"
        direction="rtl"
        class="transfer-detail-drawer"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @open="load"
    >
        <template #header>
            <div class="detail-header">
                <span class="detail-number" dir="ltr">{{ transfer?.transfer_number || '…' }}</span>
                <el-tag v-if="transfer" :type="transferStatusTone(transfer.status)" effect="light" round size="small">
                    {{ transferStatusLabel(t, transfer.status) }}
                </el-tag>
                <el-button v-if="transfer" size="small" type="primary" plain class="detail-print-btn" @click="emit('print', transfer.id)">
                    <i class="fas fa-print"></i>&nbsp;{{ $t('print') }}
                </el-button>
            </div>
        </template>

        <el-skeleton v-if="loading && !transfer" :rows="8" animated />
        <el-result v-else-if="error && !transfer" icon="error" :title="error">
            <template #extra>
                <el-button type="primary" @click="load">{{ $t('cat_admin_retry') }}</el-button>
            </template>
        </el-result>

        <template v-else-if="transfer">
            <!-- Where it goes -->
            <div class="route-card">
                <div class="route-end">
                    <span class="route-label">{{ $t('trf_from') }}</span>
                    <strong>{{ transfer.from_warehouse?.name || '—' }}</strong>
                </div>
                <i class="fas fa-truck-arrow-right route-icon" aria-hidden="true"></i>
                <div class="route-end">
                    <span class="route-label">{{ $t('trf_to') }}</span>
                    <strong>{{ transfer.to_warehouse?.name || '—' }}</strong>
                </div>
            </div>

            <!-- What happens next, and the button for it -->
            <div v-if="nextStep && mayMove" class="next-step" :class="`ns-${transfer.status}`">
                <div class="next-step-text">
                    <strong>{{ nextStep.title }}</strong>
                    <span>{{ nextStep.hint }}</span>
                </div>
                <div class="next-step-actions">
                    <el-button v-if="canShip(transfer)" type="primary" @click="openShip">
                        <i class="fas fa-truck-fast"></i>&nbsp;{{ $t('trf_approve') }}
                    </el-button>
                    <el-button v-if="canReceive(transfer)" type="success" @click="openReceive">
                        <i class="fas fa-box-open"></i>&nbsp;{{ $t('trf_receive') }}
                    </el-button>
                    <el-button v-if="canCancel(transfer)" type="danger" plain :loading="busy === 'cancel'" @click="cancelTransfer">
                        {{ $t('trf_cancel') }}
                    </el-button>
                </div>
            </div>

            <!-- How it got here -->
            <ol class="timeline">
                <li v-for="step in timeline" :key="step.key" :class="{ done: step.at }">
                    <span class="tl-dot"></span>
                    <div class="tl-body">
                        <strong>{{ step.label }}</strong>
                        <span v-if="step.at" class="tl-meta">{{ formatDateTime(step.at) }}<template v-if="step.by"> · {{ step.by }}</template></span>
                        <span v-else class="tl-meta muted">{{ $t('trf_not_yet') }}</span>
                    </div>
                </li>
            </ol>

            <dl v-if="transfer.fulfillment_method" class="facts">
                <div>
                    <dt>{{ $t('trf_method') }}</dt>
                    <dd>{{ transferMethodLabel(t, transfer.fulfillment_method) }}</dd>
                </div>
            </dl>

            <el-table :data="transfer.items || []" size="small" class="items-table">
                <el-table-column :label="$t('product')" min-width="190">
                    <template #default="{ row }">
                        <div class="item-name">{{ transferProductName(row) }}</div>
                        <code v-if="row.variant?.sku || row.product?.sku" class="item-sku">{{ row.variant?.sku || row.product?.sku }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('trf_requested')" width="80" align="center" prop="quantity_requested" />
                <el-table-column :label="$t('trf_shipped')" width="80" align="center">
                    <template #default="{ row }">{{ row.quantity_shipped ?? '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('trf_received')" width="80" align="center">
                    <template #default="{ row }">
                        <span :class="{ short: isShort(row) }">{{ row.quantity_received ?? '—' }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('trf_value')" width="110" align="right">
                    <template #default="{ row }">{{ formatCurrency(Number(row.unit_cost) * Number(row.quantity_requested)) }}</template>
                </el-table-column>
            </el-table>

            <div class="value-total">
                <span>{{ $t('trf_total_value') }}</span>
                <strong>{{ formatCurrency(totalValue) }}</strong>
            </div>

            <div v-if="transfer.notes" class="notes">
                <h5>{{ $t('notes') }}</h5>
                <p>{{ transfer.notes }}</p>
            </div>
        </template>

        <!-- Approve: how will the goods travel? -->
        <el-dialog v-model="shipVisible" :title="$t('trf_approve')" width="440px" append-to-body>
            <p class="dialog-lead">{{ $t('trf_approve_lead') }}</p>
            <el-radio-group v-model="shipMethod" class="method-choice">
                <el-radio value="delivery" border>
                    <strong>{{ $t('trf_method_delivery') }}</strong>
                    <small>{{ $t('trf_method_delivery_hint') }}</small>
                </el-radio>
                <el-radio value="pickup" border>
                    <strong>{{ $t('trf_method_pickup') }}</strong>
                    <small>{{ $t('trf_method_pickup_hint') }}</small>
                </el-radio>
            </el-radio-group>
            <template #footer>
                <el-button @click="shipVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="busy === 'ship'" @click="ship">{{ $t('trf_approve') }}</el-button>
            </template>
        </el-dialog>

        <!-- Receive: what actually arrived -->
        <el-dialog v-model="receiveVisible" :title="$t('trf_receive')" width="560px" append-to-body>
            <p class="dialog-lead">{{ transfer?.fulfillment_method === 'pickup' ? $t('trf_receive_lead_pickup') : $t('trf_receive_lead') }}</p>
            <el-table :data="receiveLines" size="small">
                <el-table-column :label="$t('product')" min-width="180">
                    <template #default="{ row }">{{ row.name }}</template>
                </el-table-column>
                <el-table-column :label="transfer?.fulfillment_method === 'pickup' ? $t('trf_requested') : $t('trf_shipped')" width="80" align="center" prop="ceiling" />
                <el-table-column :label="$t('trf_received')" width="150" align="center">
                    <template #default="{ row }">
                        <el-input-number v-model="row.received" :min="0" :max="row.ceiling" size="small" controls-position="right" />
                    </template>
                </el-table-column>
            </el-table>
            <el-alert
                v-if="receiveShortfall > 0 && transfer?.fulfillment_method !== 'pickup'"
                type="warning"
                :closable="false"
                show-icon
                class="short-alert"
                :title="$t('trf_receive_short', { count: receiveShortfall })"
            />
            <template #footer>
                <el-button @click="receiveVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="success" :loading="busy === 'receive'" @click="receive">{{ $t('trf_confirm_receipt') }}</el-button>
            </template>
        </el-dialog>
    </el-drawer>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { inventoryTransfersApi } from '@/api/inventoryTransfers';
import { apiErrorMessage, formatCurrency } from '@/utils/sales';
import {
    canCancel, canReceive, canShip, formatDateTime, receiveCeiling,
    transferMethodLabel, transferProductName, transferStatusLabel, transferStatusTone,
} from '@/utils/transfers';

/**
 * One transfer, with the step it is waiting on and the button that takes it:
 * the source approves (shipping it or setting it aside), the destination
 * receives what actually arrived, or it is called off while still at the source.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    transferId: { type: [Number, String], default: null },
    /** False for a sales rep: they may look, but the API refuses the moves. */
    mayMove: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue', 'changed', 'print']);

const { t } = useI18n();

const transfer = ref(null);
const loading = ref(false);
const error = ref('');
const busy = ref('');

const drawerSize = computed(() => (window.innerWidth < 768 ? '100%' : '680px'));

const load = async () => {
    if (!props.transferId) return;
    loading.value = true;
    error.value = '';
    if (transfer.value?.id !== props.transferId) transfer.value = null;
    try {
        const res = await inventoryTransfersApi.get(props.transferId);
        transfer.value = res.data?.data || null;
    } catch (e) {
        error.value = apiErrorMessage(e, t('trf_load_failed'));
    } finally {
        loading.value = false;
    }
};

const totalValue = computed(() => (transfer.value?.items || [])
    .reduce((sum, item) => sum + Number(item.unit_cost) * Number(item.quantity_requested), 0));

const isShort = (item) => transfer.value?.status === 'completed'
    && transfer.value?.fulfillment_method !== 'pickup'
    && Number(item.quantity_received) < Number(item.quantity_shipped);

const nextStep = computed(() => {
    const status = transfer.value?.status;
    if (status === 'pending') return { title: t('trf_next_pending'), hint: t('trf_next_pending_hint') };
    if (status === 'in_transit') return { title: t('trf_next_in_transit'), hint: t('trf_next_in_transit_hint') };
    if (status === 'ready_for_pickup') return { title: t('trf_next_pickup'), hint: t('trf_next_pickup_hint') };
    return null;
});

const timeline = computed(() => {
    const tr = transfer.value || {};
    const steps = [
        { key: 'requested', label: t('trf_step_requested'), at: tr.requested_at || tr.created_at, by: tr.created_by?.name },
        { key: 'approved', label: t('trf_step_approved'), at: tr.approved_at, by: tr.approved_by?.name },
    ];
    if (tr.fulfillment_method !== 'pickup') {
        steps.push({ key: 'shipped', label: t('trf_step_shipped'), at: tr.shipped_at, by: tr.shipped_by?.name });
    }
    steps.push({ key: 'received', label: t('trf_step_received'), at: tr.received_at, by: tr.received_by?.name });
    if (tr.status === 'cancelled') {
        steps.push({ key: 'cancelled', label: t('trf_status_cancelled'), at: tr.updated_at, by: null });
    }
    return steps;
});

const afterChange = async (res) => {
    ElMessage.success(res.data?.message || t('trf_saved'));
    await load();
    emit('changed');
};

// ── Approve ────────────────────────────────────────────────────────────────
const shipVisible = ref(false);
const shipMethod = ref('delivery');

const openShip = () => {
    // The branch's preference, when it stated one, is the starting point.
    shipMethod.value = transfer.value?.fulfillment_method === 'pickup' ? 'pickup' : 'delivery';
    shipVisible.value = true;
};

const ship = async () => {
    busy.value = 'ship';
    try {
        const res = await inventoryTransfersApi.ship(transfer.value.id, { fulfillment_method: shipMethod.value });
        shipVisible.value = false;
        await afterChange(res);
    } catch (e) {
        ElMessage({ type: 'error', message: apiErrorMessage(e, t('trf_action_failed')), duration: 8000, showClose: true });
    } finally {
        busy.value = '';
    }
};

// ── Receive ────────────────────────────────────────────────────────────────
const receiveVisible = ref(false);
const receiveLines = ref([]);

const openReceive = () => {
    receiveLines.value = (transfer.value?.items || []).map((item) => {
        const ceiling = receiveCeiling(transfer.value, item);
        return { id: item.id, name: transferProductName(item), ceiling, received: ceiling };
    });
    receiveVisible.value = true;
};

const receiveShortfall = computed(() => receiveLines.value
    .reduce((sum, line) => sum + Math.max(0, line.ceiling - (Number(line.received) || 0)), 0));

const receive = async () => {
    busy.value = 'receive';
    try {
        const res = await inventoryTransfersApi.receive(transfer.value.id, {
            items: receiveLines.value.map((line) => ({ transfer_item_id: line.id, quantity_received: Number(line.received) || 0 })),
        });
        receiveVisible.value = false;
        await afterChange(res);
    } catch (e) {
        ElMessage({ type: 'error', message: apiErrorMessage(e, t('trf_action_failed')), duration: 8000, showClose: true });
    } finally {
        busy.value = '';
    }
};

// ── Cancel ─────────────────────────────────────────────────────────────────
const cancelTransfer = async () => {
    let reason = '';
    try {
        const answer = await ElMessageBox.prompt(t('trf_cancel_confirm'), t('trf_cancel'), {
            type: 'warning',
            inputPlaceholder: t('trf_cancel_reason'),
            confirmButtonText: t('trf_cancel'),
            cancelButtonText: t('cancel'),
        });
        reason = answer.value || '';
    } catch {
        return;
    }
    busy.value = 'cancel';
    try {
        const res = await inventoryTransfersApi.cancel(transfer.value.id, { reason: reason || undefined });
        await afterChange(res);
    } catch (e) {
        ElMessage({ type: 'error', message: apiErrorMessage(e, t('trf_action_failed')), duration: 8000, showClose: true });
    } finally {
        busy.value = '';
    }
};
</script>

<style scoped>
.detail-header { display: flex; align-items: center; gap: 0.6rem; }
.detail-number { font-family: ui-monospace, monospace; font-weight: 700; font-size: 1.1rem; color: #0f172a; }
.detail-print-btn { margin-inline-start: auto; }

.route-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1rem;
}
.route-end { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.15rem; }
.route-end strong { color: #0f172a; overflow-wrap: anywhere; }
.route-label { font-size: 0.75rem; color: #64748b; }
.route-icon { color: #2563eb; font-size: 1.2rem; }
[dir='rtl'] .route-icon { transform: scaleX(-1); }

.next-step {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
}
.next-step.ns-in_transit,
.next-step.ns-ready_for_pickup { background: #eff6ff; border-color: #bfdbfe; }
.next-step-text { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.next-step-text span { font-size: 0.82rem; color: #475569; }
.next-step-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.timeline { list-style: none; margin: 0 0 1rem; padding: 0; display: grid; gap: 0.55rem; }
.timeline li { display: flex; align-items: flex-start; gap: 0.6rem; }
.tl-dot { width: 10px; height: 10px; margin-top: 0.4rem; border-radius: 50%; border: 2px solid #cbd5e1; background: #fff; flex-shrink: 0; }
.timeline li.done .tl-dot { border-color: #16a34a; background: #16a34a; }
.tl-body { display: flex; flex-direction: column; }
.tl-body strong { font-size: 0.88rem; color: #0f172a; }
.tl-meta { font-size: 0.78rem; color: #475569; }
.tl-meta.muted { color: #94a3b8; }

.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.5rem 1rem; margin: 0 0 1rem; }
.facts dt { font-size: 0.75rem; color: #64748b; }
.facts dd { margin: 0; font-weight: 600; color: #0f172a; }

.items-table { margin-bottom: 0.75rem; }
.item-name { font-weight: 600; }
.item-sku { font-size: 0.72rem; color: #64748b; }
.short { color: #dc2626; font-weight: 700; }

.value-total { display: flex; justify-content: space-between; padding: 0.6rem 0.9rem; background: #f8fafc; border-radius: 10px; margin-bottom: 1rem; }
.value-total strong { font-variant-numeric: tabular-nums; }

.notes h5 { margin: 0 0 0.3rem; color: #334155; }
.notes p { margin: 0; white-space: pre-line; color: #475569; }

.dialog-lead { margin: 0 0 0.9rem; color: #475569; line-height: 1.6; }
.method-choice { display: grid; gap: 0.6rem; width: 100%; }
.method-choice :deep(.el-radio) { height: auto; padding: 0.6rem 0.9rem; margin: 0; align-items: flex-start; white-space: normal; }
.method-choice :deep(.el-radio__label) { display: flex; flex-direction: column; gap: 0.15rem; }
.method-choice small { color: #64748b; font-weight: 400; line-height: 1.5; }
.short-alert { margin-top: 0.75rem; }
</style>
