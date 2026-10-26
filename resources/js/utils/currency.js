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
import {
    formatDecimal,
    formatQuantity,
    moneyDecimals,
    activeNumberSettings,
} from '@/utils/numberFormat';

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
 * An amount with its currency: `1,234.00 USD` by default.
 *
 * Digits, separators, rounding and whether the code or the symbol is shown come
 * from the number settings (`utils/numberFormat`). The default stays the code
 * after the figure rather than `Intl`'s localised symbol — the code is
 * unambiguous next to a figure someone may be reconciling against a ledger.
 *
 * @param {number|string} value
 * @param {{ code?: string, decimals?: number }} [options] `decimals` overrides
 *   the configured precision, for report screens that quote round figures.
 */
export const formatMoney = (value, options = {}) => {
    const { code, decimals } = options;
    const currencyCode = code || baseCurrencyCode();
    const places = decimals !== undefined && decimals !== null && Number.isFinite(Number(decimals))
        ? Number(decimals)
        : moneyDecimals(currencyDecimals(currencyCode));

    const settings = activeNumberSettings();
    const label = settings.number_currency_display === 'symbol'
        ? (currencyInfo(currencyCode)?.symbol || currencyCode)
        : currencyCode;
    const before = settings.number_currency_position === 'before';
    // A code is a word and keeps its space; a symbol hugs a figure it leads ($12).
    const gap = label === currencyCode || !before ? ' ' : '';

    return formatDecimal(toNumber(value), {
        minimumFractionDigits: places,
        maximumFractionDigits: places,
        trailingZeros: settings.number_trailing_zeros,
        prefix: before ? `${label}${gap}` : '',
        suffix: before ? '' : `${gap}${label}`,
    });
};

/**
 * A unit price as the server stores it, to five decimals. Rounding to cents
 * would sell a gram at 0.0036 for nothing.
 */
export const roundUnitPrice = (value) => Math.round(toNumber(value) * 1e5) / 1e5;

/** Decimals a unit price needs on screen: two, or up to five for a price under one. */
export const unitPriceDigits = (value) => {
    const price = Math.abs(roundUnitPrice(value));
    if (price === 0 || price >= 1) return 2;
    const decimals = (String(price).split('.')[1] || '').length;
    return Math.min(5, Math.max(2, decimals));
};

/**
 * A unit price for display: `formatMoney` with the decimals the price needs,
 * so a gram at 0.0036 does not read 0.00.
 */
export const formatUnitPrice = (value, options = {}) => formatMoney(value, {
    ...options,
    decimals: Math.max(moneyDecimals(currencyDecimals(options.code || baseCurrencyCode())), unitPriceDigits(value)),
});

/** A plain count or quantity — in the configured style, never given a currency. */
export const formatNumber = (value) => formatQuantity(toNumber(value));

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
