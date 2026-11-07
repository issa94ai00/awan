import { shallowReactive, watch } from 'vue';
import { optionKey, optionFromLine } from '@/utils/productPick';
import { useVariantSiblings } from '@/composables/useVariantSiblings';

export const num = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const round2 = (n) => Math.round(n * 100) / 100;
// The server keeps money to 5 places (decimal(15,5)) and works a percentage
// discount out to that; the form does the same so their totals agree.
const round5 = (n) => Math.round(n * 100000) / 100000;

/** Today as YYYY-MM-DD in local time: toISOString() is UTC, which in Damascus
 *  dated anything entered just after midnight to the day before. */
export const todayIso = () => isoOf(new Date());

export const isoOf = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** A YYYY-MM-DD date plus some days, in local time. */
export const addDaysIso = (iso, days) => {
    const [y, m, d] = (iso || todayIso()).split('-').map(Number);
    return isoOf(new Date(y, m - 1, d + days));
};

/**
 * The search rows a purchase form has seen, by pick key ("12" or "12-40"),
 * so a line always knows the product it names — whether it was picked from
 * the catalogue, filled from a request or a sale, or loaded for editing.
 * Looking a picked row up in "the current search results" lost it as soon as
 * the next search ran, and a line could then be saved without a product id.
 */
export function usePurchaseProductCache() {
    const rows = shallowReactive(new Map());
    const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();

    /**
     * @param list search rows or saved lines (anything with product_id is a line)
     * @param loadSizes fetch each product's full size list (for picked lines)
     */
    const remember = (list = [], { loadSizes = false } = {}) => {
        const searchRows = [];
        list.forEach((entry) => {
            if (!entry) return;
            const isLine = entry.product_id != null && entry.product_id !== '';
            const row = isLine ? optionFromLine(entry) : entry;
            if (!row?.id) return;
            const key = optionKey(row);
            // A search row carries more (stock, image, category) than one
            // rebuilt from a saved line, so it is never replaced by one.
            if (!isLine || !rows.has(key)) rows.set(key, row);
            if (!isLine) searchRows.push(row);
            if (loadSizes && (row.variant_id || row.has_variants || entry.needs_variant)) {
                ensureVariantsLoaded(row.id);
            }
        });
        rememberVariantRows(searchRows);
    };

    const rowOf = (pick) => (pick ? rows.get(pick) || null : null);

    return { remember, rowOf, siblingsOf, ensureVariantsLoaded };
}

/**
 * The discount on a purchase document, kept as either a percentage or an
 * amount of `base` (the goods value), with the other figure following it.
 *
 * In percent mode the amount follows the goods as lines change; in amount
 * mode the percentage is only shown. The server recomputes the amount from a
 * percentage it is sent, so a document in amount mode sends no percentage —
 * otherwise a rounded 2-decimal percentage turned an agreed 100.00 off into
 * 99.99.
 */
export function useDocumentDiscount(form, base) {
    const hasPercent = () => form.discount_percent !== '' && form.discount_percent != null;

    const fromPercent = () => {
        if (!hasPercent()) {
            form.discount = 0;
            return;
        }
        let pct = num(form.discount_percent);
        if (pct < 0) pct = form.discount_percent = 0;
        if (pct > 100) pct = form.discount_percent = 100;
        form.discount = round5((pct / 100) * Math.max(0, base.value));
    };

    const fromAmount = () => {
        const amount = num(form.discount);
        if (amount < 0) form.discount = 0;
        form.discount_percent = base.value > 0 && amount > 0 ? round2((amount / base.value) * 100) : '';
    };

    const setMode = (mode) => {
        if (form.discount_mode === mode) return;
        form.discount_mode = mode;
        if (mode === 'percent') {
            if (num(form.discount) > 0) fromAmount();
            fromPercent();
        }
    };

    const applyPreset = (p) => {
        form.discount_mode = 'percent';
        form.discount_percent = num(form.discount_percent) === p ? '' : p;
        fromPercent();
    };

    /** Loads a saved document's discount, choosing the mode it was entered in. */
    const load = ({ discount, discount_percent: percent }, subtotal) => {
        const amount = num(discount);
        const pct = percent === '' || percent == null ? null : num(percent);
        form.discount = amount;
        if (amount <= 0 && !(pct > 0)) {
            form.discount_mode = 'percent';
            form.discount_percent = '';
            return;
        }
        // The server stores a percentage for an amount discount too, rounded;
        // if that percentage does not give back the amount, it was an amount.
        const fromPct = pct != null ? round5((pct / 100) * num(subtotal)) : null;
        if (pct > 0 && Math.abs(fromPct - amount) < 0.0001) {
            form.discount_mode = 'percent';
            form.discount_percent = pct;
        } else {
            form.discount_mode = 'amount';
            form.discount_percent = num(subtotal) > 0 ? round2((amount / num(subtotal)) * 100) : '';
        }
    };

    /** What the API is sent. */
    const payload = () => (form.discount_mode === 'percent' && hasPercent()
        ? { discount: num(form.discount), discount_percent: num(form.discount_percent), discount_mode: 'percent' }
        : { discount: num(form.discount), discount_percent: null, discount_mode: 'amount' });

    watch(base, () => {
        if (form.discount_mode === 'percent') fromPercent();
        else fromAmount();
    });

    return { fromPercent, fromAmount, setMode, applyPreset, load, payload };
}
