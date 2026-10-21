<template>
    <el-dialog
        v-model="visible"
        :title="$t('exp_print_preview')"
        width="920px"
        class="ex-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="ex-toolbar">
            <div class="ex-toolbar-group">
                <span class="ex-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="ex-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showContacts">{{ $t('po_show_contacts') }}</el-checkbox>
                <el-checkbox v-model="settings.twoCopies">{{ $t('spay_print_two_copies') }}</el-checkbox>
            </div>
        </div>

        <div class="ex-stage">
            <div
                v-if="expense"
                :id="SHEET_ID"
                class="ex-sheet"
                :class="{ 'two-copies': settings.twoCopies }"
                dir="rtl"
                lang="ar"
            >
                <!-- One voucher, or the original and a copy on one A4. -->
                <section v-for="copy in copies" :key="copy" class="ex-voucher">
                    <!-- An expense that was not paid must not pass for one that was. -->
                    <div v-if="!isPaid" class="ex-watermark" :class="`st-${status}`"><span>{{ statusLabel }}</span></div>

                    <PrintDocumentHeader
                        :header-style="settings.twoCopies ? 'compact' : settings.headerStyle"
                        :show-logo="settings.showLogo"
                        :show-contacts="settings.showContacts && !settings.twoCopies"
                        :show-meta="false"
                        :title="$t('exp_print_title')"
                        subtitle="EXPENSE PAYMENT VOUCHER"
                        :document-number="expense.expense_number || ''"
                        banner-src="/Header.jpeg"
                    />

                    <div v-if="settings.twoCopies" class="ex-copy-tag">{{ copy === 'original' ? $t('spay_print_original') : $t('exp_print_copy') }}</div>

                    <!-- The amount, first and largest -->
                    <div class="ex-hero">
                        <div class="ex-hero-amount">
                            <span class="ex-hero-label">{{ $t('amount') }}</span>
                            <strong dir="ltr">{{ formatCurrency(expense.amount) }}</strong>
                        </div>
                        <div class="ex-hero-facts">
                            <div>
                                <span class="ex-label">{{ $t('expense_number') }}</span>
                                <strong class="ex-mono" dir="ltr">{{ expense.expense_number || '—' }}</strong>
                            </div>
                            <div>
                                <span class="ex-label">{{ $t('the_date') }}</span>
                                <strong dir="ltr">{{ formatDate(expense.expense_date || expense.created_at) }}</strong>
                            </div>
                            <div>
                                <span class="ex-label">{{ $t('category') }}</span>
                                <strong>{{ categoryLabel }}</strong>
                            </div>
                            <div>
                                <span class="ex-label">{{ $t('expense_status') }}</span>
                                <strong class="ex-status" :class="`st-${status}`">{{ statusLabel }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- What it was for, and who took the money -->
                    <dl class="ex-details">
                        <div class="wide">
                            <dt>{{ $t('description') }}</dt>
                            <dd><strong>{{ expense.description || '—' }}</strong></dd>
                        </div>
                        <div class="wide">
                            <dt>{{ $t('spay_print_paid_to') }}</dt>
                            <dd class="ex-write-in"></dd>
                        </div>
                        <div class="wide">
                            <dt>{{ $t('spay_print_amount_words') }}</dt>
                            <dd class="ex-write-in"></dd>
                        </div>
                        <div v-if="salesOrder">
                            <dt>{{ $t('sales_order') }}</dt>
                            <dd dir="ltr" class="ex-mono">{{ salesOrder.order_number }}</dd>
                        </div>
                        <div v-if="expense.invoice">
                            <dt>{{ $t('invoice') }}</dt>
                            <dd dir="ltr" class="ex-mono">{{ expense.invoice.invoice_number }}</dd>
                        </div>
                        <div v-if="expense.customer">
                            <dt>{{ $t('client') }}</dt>
                            <dd>
                                {{ expense.customer.name }}
                                <span v-if="expense.customer.phone" class="ex-muted" dir="ltr">{{ expense.customer.phone }}</span>
                            </dd>
                        </div>
                        <div v-if="expense.notes" class="wide">
                            <dt>{{ $t('notes') }}</dt>
                            <dd class="ex-notes">{{ expense.notes }}</dd>
                        </div>
                    </dl>

                    <!-- Signatures -->
                    <div class="ex-signatures">
                        <div class="ex-sig">
                            <span class="ex-sig-title">{{ $t('exp_print_requested_by') }}</span>
                            <div v-if="expense.creator?.name" class="ex-sig-name">{{ expense.creator.name }}</div>
                            <div class="ex-sig-line"></div>
                        </div>
                        <div class="ex-sig">
                            <span class="ex-sig-title">{{ $t('approved_by') }}</span>
                            <div class="ex-sig-line"></div>
                        </div>
                        <div class="ex-sig">
                            <span class="ex-sig-title">{{ $t('spay_print_cashier') }}</span>
                            <div class="ex-sig-line"></div>
                        </div>
                        <div class="ex-sig">
                            <span class="ex-sig-title">{{ $t('exp_print_receiver') }}</span>
                            <div class="ex-sig-line"></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!expense" :loading="printing" @click="print">
                <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { formatCurrency } from '@/utils/sales';

/**
 * A payment voucher for one expense: what the money was for, how much, what
 * it belongs to, and signatures for whoever asked, approved, paid and took
 * it. Printed from the list row, with the page's own category and status
 * labels so the sheet reads as the list does.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    expense: { type: Object, default: null },
    categoryLabel: { type: String, default: '' },
    statusLabel: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'expense-printable-doc';
const IFRAME_ID = 'ex-print-hidden-iframe';

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

const formatDate = (value) => (value ? String(value).slice(0, 10) : '—');

const status = computed(() => String(props.expense?.status || '').toLowerCase());
const isPaid = computed(() => status.value === 'paid');
const salesOrder = computed(() => props.expense?.sales_order || props.expense?.salesOrder || null);

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
    #${SHEET_ID}.two-copies .ex-voucher { height: 148mm !important; padding: 8mm 0 !important; overflow: hidden !important; }
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
        const docTitle = escapeHtml(`${t('exp_print_title')} ${props.expense?.expense_number || ''}`);
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
.ex-toolbar {
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
.ex-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.ex-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.ex-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.ex-sheet {
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
.ex-voucher { position: relative; break-inside: avoid; }
.ex-sheet.two-copies .ex-voucher + .ex-voucher { border-top: 1.5px dashed #94a3b8; margin-top: 14px; padding-top: 14px; }
.ex-copy-tag {
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
.ex-muted { color: #64748b; margin-inline-start: 6px; }
.ex-mono { font-family: ui-monospace, monospace; font-weight: 700; }
.ex-label { display: block; font-size: 10px; color: #64748b; font-weight: 600; }

/* An unpaid or rejected expense carries its status across the voucher. */
.ex-watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 2;
    overflow: hidden;
}
.ex-watermark span {
    transform: rotate(-24deg);
    font-size: 64px;
    font-weight: 900;
    letter-spacing: 2px;
    color: rgba(180, 83, 9, 0.12);
    border: 6px solid rgba(180, 83, 9, 0.12);
    border-radius: 14px;
    padding: 4px 28px;
    white-space: nowrap;
}
.ex-watermark.st-rejected span { color: rgba(185, 28, 28, 0.14); border-color: rgba(185, 28, 28, 0.14); }

/* The amount, first and largest */
.ex-hero {
    display: flex;
    align-items: stretch;
    gap: 12px;
    margin: 12px 0;
    break-inside: avoid;
}
.ex-hero-amount {
    flex: 0 0 34%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background: #fff7ed;
    border: 1.5px solid #f97316;
    border-radius: 10px;
    padding: 10px;
}
.ex-hero-label { font-size: 10.5px; font-weight: 700; color: #c2410c; }
.ex-hero-amount strong { font-size: 20px; font-weight: 800; color: #9a3412; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ex-hero-facts {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 11.5px;
    align-items: center;
}
.ex-status.st-paid { color: #15803d; }
.ex-status.st-pending { color: #b45309; }
.ex-status.st-approved { color: #1d4ed8; }
.ex-status.st-rejected { color: #b91c1c; }

/* What it was for, and who took the money */
.ex-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
    margin: 0 0 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    font-size: 11px;
}
.ex-details > div { display: flex; gap: 10px; align-items: baseline; padding: 7px 10px; border-bottom: 1px solid #f1f5f9; min-width: 0; }
.ex-details > div.wide { grid-column: 1 / -1; }
.ex-details dt { flex: 0 0 auto; min-width: 90px; color: #64748b; font-weight: 600; }
.ex-details dd { margin: 0; flex: 1; min-width: 0; overflow-wrap: anywhere; unicode-bidi: plaintext; }
.ex-write-in { border-bottom: 1px dashed #94a3b8; min-height: 18px; }
.ex-notes { white-space: pre-line; }

/* Signatures */
.ex-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 18px; break-inside: avoid; }
.ex-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.ex-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.ex-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.ex-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

/* Two copies: tighter, so each half fits */
.ex-sheet.two-copies .ex-hero { margin: 8px 0; }
.ex-sheet.two-copies .ex-hero-amount strong { font-size: 17px; }
.ex-sheet.two-copies .ex-details > div { padding: 5px 10px; }
.ex-sheet.two-copies .ex-signatures { margin-top: 12px; }
.ex-sheet.two-copies .ex-sig-title { margin-bottom: 20px; }
.ex-sheet.two-copies .ex-watermark span { font-size: 48px; }

@media (max-width: 768px) {
    .ex-hero { flex-direction: column; }
    .ex-details { grid-template-columns: 1fr; }
    .ex-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
