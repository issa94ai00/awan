<template>
    <el-dialog
        v-model="visible"
        :title="$t('spay_print_preview')"
        width="920px"
        class="sp-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="sp-toolbar">
            <div class="sp-toolbar-group">
                <span class="sp-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="sp-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showContacts">{{ $t('po_show_contacts') }}</el-checkbox>
                <el-checkbox v-model="settings.twoCopies">{{ $t('spay_print_two_copies') }}</el-checkbox>
            </div>
        </div>

        <div v-loading="loading" class="sp-stage">
            <div v-if="payment" :id="SHEET_ID" class="sp-sheet" :class="{ 'two-copies': settings.twoCopies }" dir="rtl" lang="ar">
                <!-- One voucher, or the original and the supplier's copy on one A4. -->
                <section v-for="copy in copies" :key="copy" class="sp-voucher">
                    <PrintDocumentHeader
                        :header-style="settings.twoCopies ? 'compact' : settings.headerStyle"
                        :show-logo="settings.showLogo"
                        :show-contacts="settings.showContacts && !settings.twoCopies"
                        :show-meta="false"
                        :title="$t('spay_print_title')"
                        subtitle="SUPPLIER PAYMENT VOUCHER"
                        :document-number="payment.payment_number"
                        banner-src="/Header.jpeg"
                    />

                    <div v-if="settings.twoCopies" class="sp-copy-tag">{{ copy === 'original' ? $t('spay_print_original') : $t('spay_print_copy') }}</div>

                    <!-- The amount, first and largest -->
                    <div class="sp-hero">
                        <div class="sp-hero-amount">
                            <span class="sp-hero-label">{{ $t('amount') }}</span>
                            <strong dir="ltr">{{ formatCurrency(payment.amount, payment.currency) }}</strong>
                        </div>
                        <div class="sp-hero-facts">
                            <div>
                                <span class="sp-label">{{ $t('payment_number') }}</span>
                                <strong class="sp-mono" dir="ltr">{{ payment.payment_number }}</strong>
                            </div>
                            <div>
                                <span class="sp-label">{{ $t('payment_date') }}</span>
                                <strong dir="ltr">{{ formatDate(payment.payment_date) }}</strong>
                            </div>
                            <div>
                                <span class="sp-label">{{ $t('payment_method') }}</span>
                                <strong>{{ paymentMethodLabel(payment.payment_method) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Who was paid, and against what -->
                    <dl class="sp-details">
                        <div class="wide">
                            <dt>{{ $t('spay_print_paid_to') }}</dt>
                            <dd>
                                <strong>{{ payment.supplier?.name || '—' }}</strong>
                                <span v-if="payment.supplier?.company" class="sp-muted"> ({{ payment.supplier.company }})</span>
                                <span v-if="payment.supplier?.phone" class="sp-muted sp-phone" dir="ltr">{{ payment.supplier.phone }}</span>
                            </dd>
                        </div>
                        <div class="wide">
                            <dt>{{ $t('spay_print_amount_words') }}</dt>
                            <dd class="sp-write-in"></dd>
                        </div>
                        <div>
                            <dt>{{ $t('spay_applied_to') }}</dt>
                            <dd dir="auto">{{ payment.purchase_receipt?.receipt_number || $t('spay_on_account') }}</dd>
                        </div>
                        <div v-if="payment.purchase_order">
                            <dt>{{ $t('spay_print_purchase_order') }}</dt>
                            <dd dir="ltr" class="sp-mono">{{ payment.purchase_order.order_number }}</dd>
                        </div>
                        <div v-if="payment.reference">
                            <dt>{{ $t('reference') }}</dt>
                            <dd dir="auto" class="sp-mono">{{ payment.reference }}</dd>
                        </div>
                        <div v-if="payment.notes" class="wide">
                            <dt>{{ $t('notes') }}</dt>
                            <dd class="sp-notes">{{ payment.notes }}</dd>
                        </div>
                    </dl>

                    <!-- Signatures -->
                    <div class="sp-signatures">
                        <div class="sp-sig">
                            <span class="sp-sig-title">{{ $t('spay_print_prepared_by') }}</span>
                            <div v-if="payment.creator?.name" class="sp-sig-name">{{ payment.creator.name }}</div>
                            <div class="sp-sig-line"></div>
                        </div>
                        <div class="sp-sig">
                            <span class="sp-sig-title">{{ $t('spay_print_cashier') }}</span>
                            <div class="sp-sig-line"></div>
                        </div>
                        <div class="sp-sig">
                            <span class="sp-sig-title">{{ $t('approved_by') }}</span>
                            <div class="sp-sig-line"></div>
                        </div>
                        <div class="sp-sig">
                            <span class="sp-sig-title">{{ $t('spay_print_receiver') }}</span>
                            <div class="sp-sig-line"></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!payment" :loading="printing" @click="print">
                <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { supplierPaymentsApi } from '@/api/supplierPayments';
import { apiErrorMessage, formatCurrency, paymentMethodLabel } from '@/utils/sales';

/**
 * A payment voucher for one supplier payment: who was paid, how much, how,
 * and against which delivery — with room for the cashier's and the
 * supplier's signatures.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    paymentId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'supplier-payment-printable-doc';
const IFRAME_ID = 'sp-print-hidden-iframe';

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const settings = reactive({
    headerStyle: 'official',
    showLogo: true,
    showContacts: true,
    twoCopies: false,
});

const copies = computed(() => (settings.twoCopies ? ['original', 'copy'] : ['original']));

const payment = ref(null);
const loading = ref(false);
const printing = ref(false);

const formatDate = (value) => (value ? String(value).slice(0, 10) : '—');

watch(() => [props.modelValue, props.paymentId], async ([open, id]) => {
    if (!open || !id) return;
    payment.value = null;
    loading.value = true;
    try {
        const res = await supplierPaymentsApi.get(id);
        payment.value = res.data?.data || null;
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_load_report')));
        visible.value = false;
    } finally {
        loading.value = false;
    }
}, { immediate: true });

const PRINT_STYLES = `
    @page { size: A4 portrait; margin: 0 !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; color: #0f172a !important; direction: rtl !important; height: auto !important; overflow: visible !important; }
    a[href]:after, abbr[title]:after { content: none !important; }
    .brand-domain-badge, .domain-badge { display: none !important; }
    /* A print stylesheet another screen left loaded may hide every element. */
    #${SHEET_ID}, #${SHEET_ID} * { visibility: visible !important; }
    #${SHEET_ID} { width: 100% !important; margin: 0 !important; padding: 10mm 12mm !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; }
    /* Two copies share the page: each takes half, cut along the dashed line. */
    #${SHEET_ID}.two-copies { padding: 0 12mm !important; }
    #${SHEET_ID}.two-copies .sp-voucher { height: 148mm !important; padding: 8mm 0 !important; overflow: hidden !important; }
`;

const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// Print through an isolated iframe holding only the sheet.
const print = async () => {
    if (printing.value) return;
    await nextTick();
    const sheet = document.getElementById(SHEET_ID);
    if (!sheet) return;
    printing.value = true;
    try {
        document.getElementById(IFRAME_ID)?.remove();
        const iframe = document.createElement('iframe');
        iframe.id = IFRAME_ID;
        Object.assign(iframe.style, { position: 'fixed', left: '0', top: '0', width: '100vw', height: '100vh', border: '0', opacity: '0', pointerEvents: 'none', zIndex: '-9999' });
        document.body.appendChild(iframe);

        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map((node) => node.outerHTML).join('\n');
        const title = escapeHtml(`${t('spay_print_title')} ${payment.value?.payment_number || ''}`);
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${title}</title>
            ${styles}<style>${PRINT_STYLES}</style>
            </head><body>${sheet.outerHTML}</body></html>`);
        doc.close();

        // Images and webfonts have to arrive before the print dialog snapshots the page.
        const pending = Array.from(doc.images).filter((img) => !img.complete);
        await Promise.race([
            Promise.all(pending.map((img) => new Promise((resolve) => { img.onload = resolve; img.onerror = resolve; }))),
            new Promise((resolve) => setTimeout(resolve, 1500)),
        ]);
        await Promise.race([doc.fonts?.ready, new Promise((resolve) => setTimeout(resolve, 1500))]);

        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => iframe.remove(), 60000);
    } finally {
        printing.value = false;
    }
};

onBeforeUnmount(() => document.getElementById(IFRAME_ID)?.remove());
</script>

<style scoped>
.sp-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 18px;
    padding: 10px 14px;
    margin-bottom: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.sp-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.sp-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.sp-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.sp-sheet {
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    background: #ffffff;
    color: #0f172a;
    font-family: 'Cairo', 'Almarai', Tahoma, sans-serif;
    line-height: 1.5;
    direction: rtl;
    padding: 18px 24px;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.12);
}
.sp-voucher { position: relative; break-inside: avoid; }
.sp-sheet.two-copies .sp-voucher + .sp-voucher { border-top: 1.5px dashed #94a3b8; margin-top: 14px; padding-top: 14px; }
.sp-copy-tag {
    position: absolute;
    top: 6px;
    left: 0;
    font-size: 9.5px;
    font-weight: 800;
    color: #475569;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    padding: 1px 10px;
}
.sp-muted { color: #64748b; }
.sp-mono { font-family: ui-monospace, monospace; font-weight: 700; }
.sp-label { display: block; font-size: 10px; color: #64748b; font-weight: 600; }

/* The amount, first and largest */
.sp-hero {
    display: flex;
    align-items: stretch;
    gap: 12px;
    margin: 12px 0;
    break-inside: avoid;
}
.sp-hero-amount {
    flex: 0 0 38%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: #ecfdf5;
    border: 1.5px solid #10b981;
    border-radius: 10px;
    padding: 10px;
}
.sp-hero-label { font-size: 10.5px; font-weight: 700; color: #047857; }
.sp-hero-amount strong { font-size: 20px; font-weight: 800; color: #065f46; font-variant-numeric: tabular-nums; white-space: nowrap; }
.sp-hero-facts {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 11.5px;
    align-items: center;
}

/* Who was paid, and against what */
.sp-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
    margin: 0 0 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    font-size: 11px;
}
.sp-details > div { display: flex; gap: 10px; align-items: baseline; padding: 7px 10px; border-bottom: 1px solid #f1f5f9; min-width: 0; }
.sp-details > div.wide { grid-column: 1 / -1; }
.sp-details dt { flex: 0 0 auto; min-width: 90px; color: #64748b; font-weight: 600; }
.sp-details dd { margin: 0; flex: 1; min-width: 0; overflow-wrap: anywhere; unicode-bidi: plaintext; }
.sp-phone { margin-inline-start: 8px; }
.sp-write-in { border-bottom: 1px dashed #94a3b8; min-height: 18px; }
.sp-notes { white-space: pre-line; }

/* Signatures */
.sp-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 18px; break-inside: avoid; }
.sp-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.sp-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.sp-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.sp-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

/* Two copies: tighter, so each half fits */
.sp-sheet.two-copies .sp-hero { margin: 8px 0; }
.sp-sheet.two-copies .sp-hero-amount strong { font-size: 17px; }
.sp-sheet.two-copies .sp-signatures { margin-top: 12px; }
.sp-sheet.two-copies .sp-sig-title { margin-bottom: 20px; }

@media (max-width: 768px) {
    .sp-hero { flex-direction: column; }
    .sp-hero-facts { grid-template-columns: 1fr 1fr; }
    .sp-details { grid-template-columns: 1fr; }
    .sp-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
