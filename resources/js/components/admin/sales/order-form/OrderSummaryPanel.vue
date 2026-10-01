<template>
    <aside class="summary-panel">
        <div class="summary-card">
            <h3>{{ t('sof_order_total') }}</h3>

            <dl class="totals">
                <div>
                    <dt>{{ t('items_subtotal_amount') }} <small>({{ t('pieces_count', { count: order.pieces.value }) }})</small></dt>
                    <dd>{{ formatCurrency(order.subtotal.value) }}</dd>
                </div>
                <div v-if="order.discountAmount.value">
                    <dt>{{ t('discount') }} <small v-if="order.form.discount_mode === 'percent'">{{ order.form.discount }}%</small></dt>
                    <dd class="minus">−{{ formatCurrency(order.discountAmount.value) }}</dd>
                </div>
                <div v-if="order.taxAmount.value">
                    <dt>{{ t('tax') }} <small v-if="order.form.tax_mode === 'percent'">{{ order.form.tax }}%</small></dt>
                    <dd>+{{ formatCurrency(order.taxAmount.value) }}</dd>
                </div>
                <div v-if="order.shipping.value">
                    <dt>{{ t('sof_shipping_charge') }}</dt>
                    <dd>+{{ formatCurrency(order.shipping.value) }}</dd>
                </div>
                <div class="grand">
                    <dt>{{ t('final_amount') }}</dt>
                    <dd>{{ formatCurrency(order.total.value) }}</dd>
                </div>
            </dl>

            <!-- What this step still needs, in the words "next" would use. -->
            <ul v-if="issues.length && showIssues" class="issues">
                <li v-for="issue in issues" :key="issue.text"><i class="fas fa-circle-exclamation"></i> {{ issue.text }}</li>
            </ul>

            <div class="actions" :class="{ 'is-final': isLast }">
                <template v-if="!isLast">
                    <el-button type="primary" size="large" class="block" @click="next">
                        {{ nextLabel }} <i class="fas" :class="nextArrow"></i>
                    </el-button>
                </template>
                <template v-else>
                    <el-button
                        type="success"
                        size="large"
                        class="block"
                        :loading="order.submitting.value === 'confirm'"
                        :disabled="!!order.submitting.value"
                        @click="emit('submit', { confirm: true })"
                    >
                        <i class="fas fa-check"></i>&nbsp;{{ t('so_save_and_confirm') }}
                    </el-button>
                    <el-button
                        size="large"
                        class="block"
                        :loading="order.submitting.value === 'draft'"
                        :disabled="!!order.submitting.value"
                        @click="emit('submit', { confirm: false })"
                    >
                        <i class="fas fa-floppy-disk"></i>&nbsp;{{ order.isEdit.value ? t('save_order_changes') : t('save_sales_order_draft') }}
                    </el-button>
                    <p class="hint">{{ t('so_execute_hint') }}</p>
                </template>

                <!-- An order being edited can be saved from any step. -->
                <el-button
                    v-if="order.isEdit.value && !isLast"
                    size="large"
                    class="block"
                    :loading="order.submitting.value === 'draft'"
                    :disabled="!!order.submitting.value || !order.lines.value.length"
                    @click="emit('submit', { confirm: false })"
                >
                    <i class="fas fa-floppy-disk"></i>&nbsp;{{ t('save_order_changes') }}
                </el-button>

                <el-button v-if="order.step.value > 0" text class="block back" @click="order.goTo(order.step.value - 1)">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('sof_back_to', { step: stepTitles[order.step.value - 1] }) }}
                </el-button>
            </div>

            <footer v-if="!order.isEdit.value" class="draft-line">
                <span v-if="order.draftSavedAt.value" :title="t('sof_draft_auto')"><i class="fas fa-cloud"></i> {{ t('sof_draft_kept', { time: savedTime }) }}</span>
                <span v-else>{{ t('sof_draft_auto') }}</span>
                <button v-if="order.lines.value.length" type="button" class="link-btn danger" @click="emit('clear')">{{ t('clear_the_form') }}</button>
            </footer>
        </div>

        <!-- A phone: the total and the next move stay on screen. -->
        <div v-if="!isLast" class="mobile-bar">
            <div class="mobile-total">
                <small>{{ t('final_amount') }}</small>
                <strong>{{ formatCurrency(order.total.value) }}</strong>
            </div>
            <el-button type="primary" size="large" @click="next">{{ nextLabel }}</el-button>
        </div>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatCurrency } from '@/utils/sales';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';

const emit = defineEmits(['submit', 'clear']);
const { t, locale } = useI18n();
const order = useSalesOrderForm();

const stepTitles = computed(() => [t('choose_products'), t('customer_and_shipping_data'), t('so_wizard_routing'), t('so_wizard_review')]);
const isLast = computed(() => order.step.value === 3);
const issues = computed(() => order.issuesFor(order.step.value));
// Problems show once "next" was tried, or once there is something to be wrong
// about — an empty first step is not an error to shout at on arrival.
const showIssues = computed(() => order.attempted[order.step.value] || (order.step.value === 0 ? order.lines.value.length > 0 : true));

const nextLabel = computed(() => t('sof_next_to', { step: stepTitles.value[order.step.value + 1] }));
const rtl = computed(() => locale.value === 'ar');
const nextArrow = computed(() => (rtl.value ? 'fa-arrow-left' : 'fa-arrow-right'));
const backArrow = computed(() => (rtl.value ? 'fa-arrow-right' : 'fa-arrow-left'));

const next = () => order.goTo(order.step.value + 1);

const savedTime = computed(() => order.draftSavedAt.value?.toLocaleTimeString(locale.value === 'ar' ? 'ar-SY' : 'en-GB', { hour: '2-digit', minute: '2-digit' }));
</script>

<style scoped>
.summary-panel { position: sticky; top: 1rem; }

.summary-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
.summary-card h3 { margin: 0 0 0.75rem; font-size: 0.98rem; font-weight: 700; color: #1e293b; }

.totals { margin: 0; display: flex; flex-direction: column; gap: 0.45rem; }
.totals div { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; font-size: 0.86rem; }
.totals dt { color: #64748b; }
.totals dt small { color: #94a3b8; }
.totals dd { margin: 0; font-weight: 600; color: #1e293b; font-variant-numeric: tabular-nums; white-space: nowrap; }
.totals dd.minus { color: #dc2626; }
.totals .grand { margin-top: 0.35rem; padding-top: 0.65rem; border-top: 1px dashed #cbd5e1; }
.totals .grand dt { color: #1e293b; font-weight: 700; }
.totals .grand dd { font-size: 1.25rem; font-weight: 800; color: #1d4ed8; }

.issues { list-style: none; margin: 0.85rem 0 0; padding: 0.6rem 0.75rem; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; display: flex; flex-direction: column; gap: 0.3rem; }
.issues li { font-size: 0.8rem; color: #92400e; display: flex; gap: 0.4rem; align-items: baseline; }

.actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }
.actions .block { width: 100%; margin: 0 !important; }
.actions .block i.fas { margin-inline-start: 0.35rem; }
.actions .back { color: #64748b; }
.hint { margin: 0.15rem 0 0; font-size: 0.74rem; color: #64748b; line-height: 1.5; }

.draft-line { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; font-size: 0.74rem; color: #94a3b8; }
.link-btn { all: unset; cursor: pointer; font-size: 0.74rem; color: #2563eb; white-space: nowrap; }
.link-btn.danger { color: #dc2626; }
.link-btn:hover { text-decoration: underline; }
.link-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

.mobile-bar { display: none; }

@media (max-width: 992px) {
    .summary-panel { position: static; }
}

@media (max-width: 768px) {
    /* The bar carries "next"; the last step keeps its two ways out here. */
    .summary-card .actions:not(.is-final) { display: none; }
    .mobile-bar {
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 40;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.65rem 1rem calc(0.65rem + env(safe-area-inset-bottom));
        background: #fff;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -6px 18px rgba(15, 23, 42, 0.08);
    }
    .mobile-total { display: flex; flex-direction: column; }
    .mobile-total small { font-size: 0.7rem; color: #64748b; }
    .mobile-total strong { font-size: 1.05rem; color: #1d4ed8; font-variant-numeric: tabular-nums; }
}
</style>
