<template>
    <el-dialog
        v-model="visible"
        :title="$t('pay_print_preview')"
        width="920px"
        class="cp-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="cp-toolbar">
            <div class="cp-toolbar-group">
                <span class="cp-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="cp-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showContacts">{{ $t('po_show_contacts') }}</el-checkbox>
                <el-checkbox v-model="settings.twoCopies">{{ $t('spay_print_two_copies') }}</el-checkbox>
            </div>
        </div>

        <div class="cp-stage">
            <div
                v-if="payment"
                :id="SHEET_ID"
                class="cp-sheet"
                :class="{ 'two-copies': settings.twoCopies, 'is-refund': isRefund }"
                dir="rtl"
                lang="ar"
            >
                <!-- One voucher, or the original and the customer's copy on one A4. -->
                <section v-for="copy in copies" :key="copy" class="cp-voucher">
                    <PrintDocumentHeader
                        :header-style="settings.twoCopies ? 'compact' : settings.headerStyle"
                        :show-logo="settings.showLogo"
                        :show-contacts="settings.showContacts && !settings.twoCopies"
                        :show-meta="false"
                        :title="title"
                        :subtitle="isRefund ? 'CUSTOMER REFUND VOUCHER' : 'CUSTOMER RECEIPT VOUCHER'"
                        :document-number="payment.payment_number || ''"
                        banner-src="/Header.jpeg"
                    />

                    <div v-if="settings.twoCopies" class="cp-copy-tag">{{ copy === 'original' ? $t('spay_print_original') : $t('pay_print_customer_copy') }}</div>

                    <!-- The amount, first and largest -->
                    <div class="cp-hero">
                        <div class="cp-hero-amount">
                            <span class="cp-hero-label">{{ isRefund ? $t('pay_kind_refund') : $t('amount') }}</span>
                            <strong dir="ltr">{{ formatCurrency(Math.abs(num(payment.amount))) }}</strong>
                            <span v-if="foreign" class="cp-hero-tendered" dir="ltr">
                                {{ $t('pay_tendered') }}: {{ formatCurrency(Math.abs(num(payment.tendered_amount)), payment.currency) }}
                            </span>
                        </div>
                        <div class="cp-hero-facts">
                            <div>
                                <span class="cp-label">{{ $t('payment_number') }}</span>
                                <strong class="cp-mono" dir="ltr">{{ payment.payment_number || '—' }}</strong>
                            </div>
                            <div>
                                <span class="cp-label">{{ $t('payment_date') }}</span>
                                <strong dir="ltr">{{ formatDate(payment.payment_date || payment.created_at) }}</strong>
                            </div>
                            <div>
                                <span class="cp-label">{{ $t('payment_method') }}</span>
                                <strong>{{ paymentMethodLabel(payment.payment_method) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- From whom, and against what -->
                    <dl class="cp-details">
                        <div class="wide">
                            <dt>{{ isRefund ? $t('spay_print_paid_to') : $t('pay_print_received_from') }}</dt>
                            <dd>
                                <strong>{{ customerName(payment) }}</strong>
                                <span v-if="payment.customer?.company" class="cp-muted"> ({{ payment.customer.company }})</span>
                                <span v-if="payment.customer?.phone" class="cp-muted cp-phone" dir="ltr">{{ payment.customer.phone }}</span>
                            </dd>
                        </div>
                        <div class="wide">
                            <dt>{{ $t('spay_print_amount_words') }}</dt>
                            <dd class="cp-write-in"></dd>
                        </div>
                        <div>
                            <dt>{{ $t('spay_applied_to') }}</dt>
                            <dd v-if="payment.invoice" dir="ltr" class="cp-mono">{{ payment.invoice.invoice_number }}</dd>
                            <dd v-else>{{ $t('pay_on_account') }}</dd>
                        </div>
                        <div v-if="payment.invoice && !isRefund">
                            <dt>{{ $t('pay_print_invoice_left_now') }}</dt>
                            <dd dir="ltr" class="cp-owed" :class="invoiceOwed > 0.009 ? 'owing' : 'settled'">{{ formatCurrency(invoiceOwed) }}</dd>
                        </div>
                        <div v-if="payment.reference">
                            <dt>{{ $t('reference') }}</dt>
                            <dd dir="auto" class="cp-mono">{{ payment.reference }}</dd>
                        </div>
                        <div v-if="payment.notes" class="wide">
                            <dt>{{ $t('notes') }}</dt>
                            <dd class="cp-notes">{{ payment.notes }}</dd>
                        </div>
                    </dl>

                    <!-- Signatures -->
                    <div class="cp-signatures">
                        <div class="cp-sig">
                            <span class="cp-sig-title">{{ isRefund ? $t('spay_print_cashier') : $t('pay_print_cashier_received') }}</span>
                            <div v-if="payment.creator?.name" class="cp-sig-name">{{ payment.creator.name }}</div>
                            <div class="cp-sig-line"></div>
                        </div>
                        <div class="cp-sig">
                            <span class="cp-sig-title">{{ $t('spay_print_prepared_by') }}</span>
                            <div class="cp-sig-line"></div>
                        </div>
                        <div class="cp-sig">
                            <span class="cp-sig-title">{{ $t('approved_by') }}</span>
                            <div class="cp-sig-line"></div>
                        </div>
                        <div class="cp-sig">
                            <span class="cp-sig-title">{{ isRefund ? $t('pay_print_customer_received') : $t('pay_print_customer_paid') }}</span>
                            <div class="cp-sig-line"></div>
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
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { customerName, formatCurrency, paymentMethodLabel } from '@/utils/sales';

/**
 * A receipt voucher for one customer payment — or, for a refund, the voucher
 * for money paid back. It prints the list row as it stands: the row already
 * carries what the invoice still owes and whether it is a refund, which the
 * single-payment endpoint does not.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    payment: { type: Object, default: null },
    /** Paid in a currency other than the base one: show what was handed over. */
    foreign: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'customer-payment-printable-doc';
const IFRAME_ID = 'cp-print-hidden-iframe';

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
const printing = ref(false);

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const formatDate = (value) => (value ? String(value).slice(0, 10) : '—');

const isRefund = computed(() => Boolean(props.payment?.is_refund));
const title = computed(() => (isRefund.value ? t('pay_print_refund_title') : t('pay_print_title')));

const invoiceOwed = computed(() => {
    const row = props.payment;
    if (!row?.invoice) return 0;
    return row.invoice_owed !== null && row.invoice_owed !== undefined
        ? num(row.invoice_owed)
        : Math.max(0, num(row.invoice.total) - num(row.invoice.paid_amount));
});

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
    #${SHEET_ID}.two-copies .cp-voucher { height: 148mm !important; padding: 8mm 0 !important; overflow: hidden !important; }
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
        const docTitle = escapeHtml(`${title.value} ${props.payment?.payment_number || ''}`);
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${docTitle}</title>
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
.cp-toolbar {
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
.cp-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.cp-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.cp-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.cp-sheet {
    --cp-accent: #047857;
    --cp-accent-dark: #065f46;
    --cp-accent-bg: #ecfdf5;
    --cp-accent-border: #10b981;
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
/* Money going back out reads in red, so it is never mistaken for a receipt. */
.cp-sheet.is-refund {
    --cp-accent: #b91c1c;
    --cp-accent-dark: #991b1b;
    --cp-accent-bg: #fef2f2;
    --cp-accent-border: #ef4444;
}
.cp-voucher { position: relative; break-inside: avoid; }
.cp-sheet.two-copies .cp-voucher + .cp-voucher { border-top: 1.5px dashed #94a3b8; margin-top: 14px; padding-top: 14px; }
.cp-copy-tag {
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
.cp-muted { color: #64748b; }
.cp-mono { font-family: ui-monospace, monospace; font-weight: 700; }
.cp-label { display: block; font-size: 10px; color: #64748b; font-weight: 600; }

/* The amount, first and largest */
.cp-hero {
    display: flex;
    align-items: stretch;
    gap: 12px;
    margin: 12px 0;
    break-inside: avoid;
}
.cp-hero-amount {
    flex: 0 0 38%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: var(--cp-accent-bg);
    border: 1.5px solid var(--cp-accent-border);
    border-radius: 10px;
    padding: 10px;
}
.cp-hero-label { font-size: 10.5px; font-weight: 700; color: var(--cp-accent); }
.cp-hero-amount strong { font-size: 20px; font-weight: 800; color: var(--cp-accent-dark); font-variant-numeric: tabular-nums; white-space: nowrap; }
.cp-hero-tendered { font-size: 10px; color: #475569; font-variant-numeric: tabular-nums; }
.cp-hero-facts {
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

/* From whom, and against what */
.cp-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
    margin: 0 0 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    font-size: 11px;
}
.cp-details > div { display: flex; gap: 10px; align-items: baseline; padding: 7px 10px; border-bottom: 1px solid #f1f5f9; min-width: 0; }
.cp-details > div.wide { grid-column: 1 / -1; }
.cp-details dt { flex: 0 0 auto; min-width: 90px; color: #64748b; font-weight: 600; }
.cp-details dd { margin: 0; flex: 1; min-width: 0; overflow-wrap: anywhere; unicode-bidi: plaintext; }
.cp-phone { margin-inline-start: 8px; }
.cp-write-in { border-bottom: 1px dashed #94a3b8; min-height: 18px; }
.cp-notes { white-space: pre-line; }
.cp-owed { font-weight: 700; font-variant-numeric: tabular-nums; }
.cp-owed.owing { color: #b45309; }
.cp-owed.settled { color: #15803d; }

/* Signatures */
.cp-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 18px; break-inside: avoid; }
.cp-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.cp-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.cp-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.cp-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

/* Two copies: tighter, so each half fits */
.cp-sheet.two-copies .cp-hero { margin: 8px 0; }
.cp-sheet.two-copies .cp-hero-amount strong { font-size: 17px; }
.cp-sheet.two-copies .cp-signatures { margin-top: 12px; }
.cp-sheet.two-copies .cp-sig-title { margin-bottom: 20px; }

@media (max-width: 768px) {
    .cp-hero { flex-direction: column; }
    .cp-hero-facts { grid-template-columns: 1fr 1fr; }
    .cp-details { grid-template-columns: 1fr; }
    .cp-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
