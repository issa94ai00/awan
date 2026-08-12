/**
 * Shared helpers for the sales module (quotes, sales orders, invoices, payments).
 *
 * These used to be copy-pasted into every sales view, and each copy compared a
 * lowercased English status against `window.t('...')` — a translated Arabic
 * label — so the comparison never matched and every "pending"/"completed"
 * counter silently reported 0. Statuses are stable identifiers coming from the
 * PHP model constants, so they are matched as identifiers here and translated
 * only for display.
 */

import i18n from '@/i18n';

const t = (key, fallback) => {
    const translated = i18n.global.t(key);
    // vue-i18n echoes the key back when it is missing.
    return translated === key ? fallback : translated;
};

/* ------------------------------------------------------------------ *
 * Status definitions — keys mirror the PHP model constants.
 * ------------------------------------------------------------------ */

// App\Models\Quote
export const QUOTE_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

// App\Models\SalesOrder and App\Models\Invoice share the same set.
export const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

// App\Models\Payment
export const PAYMENT_METHODS = ['cash', 'card', 'bank_transfer', 'check'];

/**
 * Allowed forward transitions. Mirrors what the API will accept, so the UI
 * never offers a move the backend rejects.
 */
export const QUOTE_TRANSITIONS = {
    draft: ['sent', 'rejected'],
    sent: ['accepted', 'rejected', 'expired'],
    accepted: [],
    rejected: [],
    expired: [],
};

export const ORDER_TRANSITIONS = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
    delivered: [],
    cancelled: [],
};

const TAG_TYPES = {
    // shared
    cancelled: 'danger',
    pending: 'warning',
    processing: 'primary',
    confirmed: 'info',
    shipped: 'info',
    delivered: 'success',
    // Not a model constant, but present in existing invoice rows.
    partial: 'warning',
    // quotes
    draft: 'info',
    sent: 'warning',
    accepted: 'success',
    rejected: 'danger',
    expired: 'danger',
    // payments
    completed: 'success',
    failed: 'danger',
    refunded: 'warning',
};

const ICONS = {
    draft: 'fa-pen-to-square',
    sent: 'fa-paper-plane',
    accepted: 'fa-circle-check',
    rejected: 'fa-circle-xmark',
    expired: 'fa-hourglass-end',
    pending: 'fa-clock',
    confirmed: 'fa-thumbs-up',
    processing: 'fa-gears',
    shipped: 'fa-truck-fast',
    delivered: 'fa-circle-check',
    cancelled: 'fa-ban',
    partial: 'fa-circle-half-stroke',
    completed: 'fa-circle-check',
    failed: 'fa-triangle-exclamation',
    refunded: 'fa-rotate-left',
};

const FALLBACK_LABELS = {
    draft: 'مسودة',
    sent: 'مُرسل',
    accepted: 'مقبول',
    rejected: 'مرفوض',
    expired: 'منتهي الصلاحية',
    pending: 'معلق',
    confirmed: 'مؤكد',
    processing: 'قيد المعالجة',
    shipped: 'تم الشحن',
    delivered: 'تم التسليم',
    cancelled: 'ملغي',
    partial: 'مسدد جزئياً',
    completed: 'مكتمل',
    failed: 'فشل',
    refunded: 'مُسترجع',
};

const METHOD_FALLBACK_LABELS = {
    cash: 'نقداً',
    card: 'بطاقة',
    bank_transfer: 'حوالة بنكية',
    check: 'شيك',
};

/** Normalises whatever the API returned into a bare status identifier. */
export const normalizeStatus = (status) => String(status ?? '').trim().toLowerCase();

export const statusTagType = (status) => TAG_TYPES[normalizeStatus(status)] || 'info';

export const statusIcon = (status) => ICONS[normalizeStatus(status)] || 'fa-circle-question';

export const statusLabel = (status) => {
    const key = normalizeStatus(status);
    if (!key) return t('undefined', 'غير محدد');
    return t(`sales_status_${key}`, FALLBACK_LABELS[key] || key);
};

export const paymentMethodLabel = (method) => {
    const key = normalizeStatus(method);
    if (!key) return '—';
    return t(`payment_method_${key}`, METHOD_FALLBACK_LABELS[key] || key);
};

/**
 * The status options to show in a transition dropdown: the current one plus
 * whatever it may legally move to.
 */
export const availableTransitions = (current, map) => {
    const key = normalizeStatus(current);
    const next = map[key] || [];
    return [key, ...next].filter(Boolean);
};

/* ------------------------------------------------------------------ *
 * Formatting
 * ------------------------------------------------------------------ */

const toNumber = (value) => {
    const n = parseFloat(value);
    return Number.isFinite(n) ? n : 0;
};

/**
 * Currency formatter. Amounts are stored in the base currency; that code is
 * the single source of truth (mirrored as settings.default_currency).
 */
export const formatCurrency = (value, currency) => {
    const amount = toNumber(value);
    const code = currency
        || window.systemData?.currencies?.base
        || window.systemData?.settings?.default_currency
        || 'SAR';
    const locale = i18n.global.locale.value === 'en' ? 'en-US' : 'ar-SY';

    const formatted = new Intl.NumberFormat(locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);

    return `${formatted} ${code}`;
};

export const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    const locale = i18n.global.locale.value === 'en' ? 'en-GB' : 'ar-SY';
    return new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric' }).format(date);
};

/** Sum a collection's numeric field without tripping over string amounts. */
export const sumBy = (rows, field) => (rows || []).reduce((total, row) => total + toNumber(row?.[field]), 0);

/* ------------------------------------------------------------------ *
 * Invoice balances
 * ------------------------------------------------------------------ */

export const invoicePaid = (invoice) => toNumber(invoice?.paid_amount);

/**
 * What is still owed on an invoice.
 *
 * `due_amount` is only maintained from the moment a payment is recorded through
 * the payments endpoint, so invoices created by other paths (conversion from a
 * sales order, POS, imports) sit at due_amount = 0 while nothing has been paid.
 * Trusting that column blindly reports those invoices as settled and hides the
 * "record payment" action, so a stored zero is only believed when the totals
 * agree with it.
 */
export const invoiceDue = (invoice) => {
    const stored = parseFloat(invoice?.due_amount);
    const derived = toNumber(invoice?.total) - invoicePaid(invoice);

    if (Number.isFinite(stored) && stored > 0) return stored;
    return Math.max(0, derived);
};

/** An invoice is settled when nothing is outstanding (within rounding noise). */
export const isInvoiceSettled = (invoice) => invoiceDue(invoice) <= 0.009;

export const invoicePaidPercentage = (invoice) => {
    const total = toNumber(invoice?.total);
    if (total <= 0) return 0;
    return Math.min(100, Math.round((invoicePaid(invoice) / total) * 100));
};

/**
 * Pulls a readable customer name off any sales record. The API is inconsistent:
 * invoices expose a flat `customer_name`, everything else nests `customer.name`.
 */
export const customerName = (row) => row?.customer?.name || row?.customer_name || '—';

/** Extracts a human message from an axios error, falling back to a given default. */
export const apiErrorMessage = (error, fallback) =>
    error?.response?.data?.message || error?.message || fallback;
