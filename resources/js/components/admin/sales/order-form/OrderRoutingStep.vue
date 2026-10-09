<template>
    <section class="panel" :class="{ 'has-error': order.attempted[2] && issues.length }">
        <header class="panel-head">
            <h3><i class="fas fa-warehouse"></i> {{ t('so_routing_title') }}</h3>
            <div class="mode" role="radiogroup">
                <button type="button" role="radio" :aria-checked="routing.mode === 'plan'" :class="{ 'is-on': routing.mode === 'plan' }" @click="setMode('plan')">
                    {{ t('so_routing_plan_now') }}
                </button>
                <button type="button" role="radio" :aria-checked="routing.mode === 'later'" :class="{ 'is-on': routing.mode === 'later' }" @click="setMode('later')">
                    {{ t('so_routing_later') }}
                </button>
            </div>
        </header>

        <el-alert v-if="routing.mode === 'later'" type="info" show-icon :closable="false" :title="t('so_routing_later_hint')" />

        <template v-else>
            <el-skeleton v-if="routing.loading && !routing.warehouses.length" :rows="4" animated />

            <template v-else>
                <!-- The one-glance answer: can a single place fill it all? -->
                <div class="summary" :class="'is-' + summary.state">
                    <i class="fas" :class="summary.icon"></i>
                    <span class="summary-text">{{ summary.text }}</span>
                    <div class="summary-actions">
                        <el-select
                            :model-value="null"
                            size="default"
                            class="route-all"
                            :placeholder="t('so_route_all_to')"
                            @update:model-value="order.routeAllTo"
                        >
                            <el-option v-for="w in routing.warehouses" :key="w.id" :value="w.id" :label="w.name">
                                <div class="wh-option">
                                    <span>{{ w.name }}</span>
                                    <small :class="{ short: !order.coversAll(w.id) }">{{ order.coversAll(w.id) ? t('so_covers_all') : t('so_covers_part') }}</small>
                                </div>
                            </el-option>
                        </el-select>
                        <el-button :loading="routing.loading" @click="order.suggestRouting(true)">
                            <i class="fas fa-wand-magic-sparkles"></i>&nbsp;{{ t('so_resuggest') }}
                        </el-button>
                    </div>
                </div>

                <div class="route-lines">
                    <article v-for="line in order.lines.value" :key="line.key" class="route-line" :class="'is-' + order.routingState(line)">
                        <header class="route-line-head">
                            <div class="route-line-name">
                                <strong>{{ line.name }}</strong>
                                <VariantChip v-if="line.product_variant_id" :label="line.variant_label" />
                            </div>
                            <span class="route-line-state">
                                {{ t('so_routing_placed', { placed: order.allocated(line), total: line.quantity }) }}
                                <i v-if="order.routingState(line) === 'ok'" class="fas fa-circle-check"></i>
                                <span v-else-if="order.routingState(line) === 'short'" class="tag is-warn">{{ t('so_routing_short') }}</span>
                                <span v-else class="tag is-error">{{ t('so_routing_mismatch', { n: line.quantity - order.allocated(line) }) }}</span>
                            </span>
                        </header>

                        <div v-for="(source, index) in line.allocations" :key="index" class="source">
                            <el-select v-model="source.warehouse_id" size="default" class="source-wh" :placeholder="t('so_choose_warehouse')">
                                <el-option
                                    v-for="w in routing.warehouses"
                                    :key="w.id"
                                    :value="w.id"
                                    :label="w.name"
                                    :disabled="line.allocations.some((a, i) => i !== index && a.warehouse_id === w.id)"
                                >
                                    <div class="wh-option">
                                        <span>{{ w.name }}</span>
                                        <small>{{ t('so_free_here', { n: order.freeFor(line, w.id, index) }) }}</small>
                                    </div>
                                </el-option>
                            </el-select>
                            <el-input-number v-model="source.quantity" :min="1" :max="Number(line.quantity)" :step="1" step-strictly size="default" controls-position="right" class="source-qty" />
                            <span class="source-free" :class="{ short: source.warehouse_id && source.quantity > order.freeFor(line, source.warehouse_id, index) }">
                                {{ t('so_free_here', { n: source.warehouse_id ? order.freeFor(line, source.warehouse_id, index) : '—' }) }}
                            </span>
                            <button v-if="line.allocations.length > 1" type="button" class="icon-btn" :aria-label="t('delete')" @click="order.removeSource(line, index)">
                                <i class="fas fa-xmark"></i>
                            </button>
                        </div>

                        <button
                            v-if="line.allocations.length < routing.warehouses.length"
                            type="button"
                            class="link-btn"
                            @click="order.addSource(line)"
                        >
                            <i class="fas fa-plus"></i> {{ t('so_add_source') }}
                        </button>
                    </article>
                </div>
            </template>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';

const { t } = useI18n();
const order = useSalesOrderForm();
const routing = order.routing;

const issues = computed(() => order.issuesFor(2));

const summary = computed(() => {
    const lines = order.lines.value;
    if (lines.some((line) => order.routingState(line) === 'open')) {
        return { state: 'error', icon: 'fa-circle-exclamation', text: t('so_routing_incomplete', { n: lines.filter((line) => order.routingState(line) === 'open').length }) };
    }
    if (lines.some((line) => order.routingState(line) === 'short')) {
        return { state: 'warn', icon: 'fa-triangle-exclamation', text: t('so_routing_summary_short') };
    }
    const used = order.warehousesUsed.value;
    if (used.size === 1) return { state: 'ok', icon: 'fa-circle-check', text: t('so_routing_summary_single', { name: order.warehouseName([...used][0]) }) };
    return { state: 'split', icon: 'fa-code-branch', text: t('so_routing_summary_split', { n: used.size }) };
});

const setMode = (mode) => {
    routing.mode = mode;
    if (mode === 'plan') order.suggestRouting(false);
};
</script>

<style scoped>
.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
.panel.has-error { border-color: #f87171; }
.panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.85rem; flex-wrap: wrap; }
.panel-head h3 { margin: 0; font-size: 0.98rem; font-weight: 700; color: #1e293b; display: flex; gap: 0.5rem; align-items: center; }
.panel-head h3 i { color: #64748b; }

.mode { display: flex; border: 1px solid #dcdfe6; border-radius: 8px; overflow: hidden; }
.mode button { all: unset; cursor: pointer; padding: 0.35rem 0.85rem; font-size: 0.82rem; color: #475569; }
.mode button.is-on { background: #2563eb; color: #fff; }
.mode button:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; }

.summary {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.7rem 0.85rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    margin-bottom: 0.85rem;
    font-size: 0.88rem;
    color: #334155;
}
.summary > i { font-size: 1.05rem; }
.summary.is-ok { background: #f0fdf4; border-color: #bbf7d0; }
.summary.is-ok > i { color: #16a34a; }
.summary.is-warn { background: #fffbeb; border-color: #fde68a; }
.summary.is-warn > i { color: #d97706; }
.summary.is-error { background: #fef2f2; border-color: #fecaca; }
.summary.is-error > i { color: #dc2626; }
.summary.is-split > i { color: #2563eb; }
.summary-text { flex: 1 1 200px; }
.summary-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.route-all { width: 200px; }

.wh-option { display: flex; justify-content: space-between; gap: 1rem; }
.wh-option small { color: #16a34a; }
.wh-option small.short { color: #b45309; }

.route-lines { display: flex; flex-direction: column; gap: 0.6rem; }
.route-line { border: 1px solid #e2e8f0; border-inline-start-width: 3px; border-radius: 10px; padding: 0.7rem 0.85rem; }
.route-line.is-ok { border-inline-start-color: #22c55e; }
.route-line.is-short { border-inline-start-color: #f59e0b; }
.route-line.is-open { border-inline-start-color: #ef4444; }
.route-line-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.route-line-name { display: flex; align-items: center; gap: 0.4rem; min-width: 0; }
.route-line-name strong { font-size: 0.88rem; color: #1e293b; }
.route-line-state { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #64748b; }
.route-line-state .fa-circle-check { color: #16a34a; }
.tag { padding: 0 0.5rem; border-radius: 999px; font-size: 0.72rem; line-height: 1.7; }
.tag.is-warn { background: #fffbeb; color: #b45309; }
.tag.is-error { background: #fef2f2; color: #b91c1c; }

.source { display: grid; grid-template-columns: minmax(0, 260px) 130px auto 30px; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem; }
.source-qty { width: 100%; }
.source-free { font-size: 0.78rem; color: #16a34a; }
.source-free.short { color: #b45309; }

.icon-btn { all: unset; display: grid; place-items: center; width: 28px; height: 28px; border-radius: 6px; color: #94a3b8; cursor: pointer; }
.icon-btn:hover { background: #fef2f2; color: #dc2626; }
.link-btn { all: unset; cursor: pointer; font-size: 0.8rem; color: #2563eb; }
.link-btn:hover { text-decoration: underline; }
.icon-btn:focus-visible, .link-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

@media (max-width: 768px) {
    .panel { padding: 0.85rem; }
    .summary-actions, .route-all { width: 100%; }
    .summary-actions .el-button { flex: 1; }
    .source { grid-template-columns: minmax(0, 1fr) 110px 28px; }
    .source-free { grid-column: 1 / -1; grid-row: 2; }
}
</style>
