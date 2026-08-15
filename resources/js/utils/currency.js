/**
 * How money is written across the admin.
 *
 * Every amount this system stores and posts is a number in the **base**
 * currency — `CurrencyService` is explicit that conversion is a storefront
 * display concern and never touches the ledger. So an admin screen needs
 * exactly one fact to be truthful: which currency the books are kept in.
 *
 * That fact used to be spelled out as `'SAR'` in a dozen separate view files,
 * each with its own `Intl.NumberFormat` call. When the platform moved onto the
 * dollar, every one of them kept printing riyals over dollar figures — not a
 * cosmetic slip but a wrong number in front of whoever was reading it. The code
 * lives here now, so moving the base is one change rather than a search.
 *
 * The Vue-reactive wrapper is `Composables/useCurrency`, which prefers the
 * settings store and so also follows a base change made without a reload.
 * Both resolve to the same value; this module is the one that formats.
 */

import i18n from '@/i18n';

/** Mirrors CurrencyService::FALLBACK — used only before anything has loaded. */
export const FALLBACK_CURRENCY = { code: 'USD', symbol: '$', decimal_places: 2 };

/**
 * The currency the books are kept in, from the payload `vue.blade.php` writes.
 * Both keys trace back to `base_currency_code()`; the second is the legacy
 * setting the first mirrors.
 */
export const baseCurrencyCode = () => (
    window.systemData?.currencies?.base
    || window.systemData?.settings?.default_currency
    || FALLBACK_CURRENCY.code
);

/** The full descriptor for a code, or a code-only shape so callers never branch. */
export const currencyInfo = (code) => {
    const wanted = code || baseCurrencyCode();
    const list = window.systemData?.currencies?.list || [];

    return list.find((currency) => currency?.code === wanted)
        || { ...FALLBACK_CURRENCY, code: wanted };
};

/**
 * Decimal places belong to the currency, not to a constant. The Syrian pound is
 * quoted in whole units, and `12,347.00 SYP` is the false precision the server
 * rounds away.
 */
export const currencyDecimals = (code) => {
    const places = Number(currencyInfo(code)?.decimal_places);
    return Number.isFinite(places) && places >= 0 ? places : FALLBACK_CURRENCY.decimal_places;
};

/**
 * Arabic follows `ar-SY`. The admin was split between this and `ar-SA`, which
 * is how one screen could render a total differently from the next.
 */
export const numberLocale = () => (i18n.global.locale.value === 'en' ? 'en-US' : 'ar-SY');

const toNumber = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

/**
 * An amount followed by its currency code: `1,234.00 USD`.
 *
 * Deliberately not `Intl`'s `style: 'currency'`, which renders a localised
 * symbol — the code is unambiguous next to a figure someone may be reconciling
 * against a ledger, and it is what the sales screens have always shown.
 *
 * @param {number|string} value
 * @param {{ code?: string, decimals?: number }} [options] `decimals` overrides
 *   the currency's own precision, for report screens that quote round figures.
 */
export const formatMoney = (value, options = {}) => {
    const { code, decimals } = options;
    const currencyCode = code || baseCurrencyCode();
    const places = Number.isFinite(Number(decimals)) ? Number(decimals) : currencyDecimals(currencyCode);

    const formatted = new Intl.NumberFormat(numberLocale(), {
        minimumFractionDigits: places,
        maximumFractionDigits: places,
    }).format(toNumber(value));

    return `${formatted} ${currencyCode}`;
};

/** A plain count — localised, but never given a currency. */
export const formatNumber = (value) => toNumber(value).toLocaleString(numberLocale());

/**
 * The active currencies as `{ code, label }` options for a select.
 *
 * Labels follow the reading language rather than the page's boot locale, so a
 * language switch relabels the list. Falls back to the base alone, which keeps
 * a select from rendering empty on an install whose currencies have not been
 * seeded — offering a currency that has no row, no symbol and no rate behind it
 * is what the old hardcoded SAR/USD/AED lists did.
 */
export const currencyOptions = () => {
    const list = window.systemData?.currencies?.list || [];
    const readingEnglish = i18n.global.locale.value === 'en';

    if (list.length) {
        return list.map((currency) => {
            const name = (readingEnglish ? currency.name_en : currency.name_ar)
                || currency.name
                || currency.code;

            return { code: currency.code, label: `${name} (${currency.code})` };
        });
    }

    const base = baseCurrencyCode();
    return [{ code: base, label: base }];
};
