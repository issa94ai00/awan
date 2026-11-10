// Settings → Document numbering: which documents carry a configurable prefix.
//
// Mirrors App\Support\DocumentNumbers::DEFAULTS — the two must agree. Only the
// letters are configurable; the separator and the zero-padded sequence are
// fixed, so `sample()` shows what the next number will look like.

export const DOC_PREFIX_SETTING = 'doc_prefix_';
export const DOC_PREFIX_SEPARATOR = '-';
export const DOC_PREFIX_MAX = 12;
/** Letters, digits and a little punctuation; no separator, no whitespace. */
export const DOC_PREFIX_PATTERN = /^[\p{L}\p{N}._/]{1,12}$/u;

/** Each group of documents, in display order, with the default prefix and a sample sequence. */
export const DOC_PREFIX_GROUPS = Object.freeze([
    {
        key: 'sales',
        types: [
            { type: 'invoice', prefix: 'INV', sequence: '20260110-00123' },
            { type: 'sales_order', prefix: 'SO', sequence: '000123' },
            { type: 'quote', prefix: 'QT', sequence: '000123' },
            { type: 'sales_contract', prefix: 'CTR', sequence: '000123' },
            { type: 'payment', prefix: 'PAY', sequence: '000123' },
            { type: 'refund', prefix: 'REF', sequence: 'RMA-000123' },
            { type: 'credit_note', prefix: 'CN', sequence: '000123' },
            { type: 'rma', prefix: 'RMA', sequence: '000123' },
            { type: 'storefront_order', prefix: 'ORD', sequence: '6730A1F2C4E9B' },
        ],
    },
    {
        key: 'purchasing',
        types: [
            { type: 'purchase_order', prefix: 'PO', sequence: '000123' },
            { type: 'purchase_receipt', prefix: 'PR', sequence: '000123' },
            { type: 'purchase_return', prefix: 'PRT', sequence: '000123' },
            { type: 'supplier_payment', prefix: 'SPY', sequence: '000123' },
        ],
    },
    {
        key: 'inventory',
        types: [
            { type: 'inventory_transfer', prefix: 'TRF', sequence: '000123' },
            { type: 'cycle_count', prefix: 'CC', sequence: '000123' },
            { type: 'picking_list', prefix: 'PL', sequence: '000123' },
            { type: 'packing_list', prefix: 'PCK', sequence: '000123' },
            { type: 'shipping_manifest', prefix: 'SHP', sequence: '000123' },
            { type: 'production_order', prefix: 'PROD', sequence: '000123' },
        ],
    },
    {
        key: 'finance',
        types: [
            { type: 'expense', prefix: 'EXP', sequence: '000123' },
            { type: 'journal_entry', prefix: 'JE', sequence: '000123' },
            { type: 'fixed_asset', prefix: 'FA', sequence: '00123' },
            { type: 'bank_reconciliation', prefix: 'BR', sequence: '00123' },
            { type: 'payroll', prefix: 'PAY', sequence: '000123' },
        ],
    },
]);

export const DOC_PREFIX_TYPES = Object.freeze(DOC_PREFIX_GROUPS.flatMap((group) => group.types));

export const docPrefixKey = (type) => `${DOC_PREFIX_SETTING}${type}`;

/** `{ doc_prefix_invoice: 'INV', … }` — the form's defaults, keyed like the settings. */
export const DOC_PREFIX_DEFAULTS = Object.freeze(
    Object.fromEntries(DOC_PREFIX_TYPES.map((item) => [docPrefixKey(item.type), item.prefix])),
);

export const DOC_PREFIX_KEYS = Object.keys(DOC_PREFIX_DEFAULTS);

export const isValidDocPrefix = (value) => DOC_PREFIX_PATTERN.test(String(value ?? '').trim());

/** What a number issued under `prefix` would look like, e.g. `INV-20260110-00123`. */
export const docNumberSample = (item, prefix) => {
    const lead = String(prefix ?? '').trim() || item.prefix;
    return `${lead}${DOC_PREFIX_SEPARATOR}${item.sequence}`;
};
