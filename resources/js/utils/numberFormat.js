/**
 * How every number in the system is written: digits, separators, rounding.
 *
 * The admin used to let each screen pick its own `Intl` locale — `en-US` here,
 * `ar-SY` there — so one total could read `1,234.50` on a list and `١٬٢٣٤٫٥`
 * on its invoice. The choices are now settings (Settings → Numbers & decimals)
 * and this module is the one place that applies them; `utils/currency` builds
 * money on top of it.
 *
 * Formatting is display only. Amounts the server stores and posts keep their
 * own precision — rounding a figure here never changes what the ledger holds.
 *
 * `numberSettings` is reactive, so a template that formats a number re-renders when
 * an admin saves new choices, without a reload.
 */

import { reactive } from 'vue';
import i18n from '@/i18n';

/** Defaults reproduce what the screens printed before these were settings. */
export const NUMBER_DEFAULTS = Object.freeze({
    // auto | latin | arabic — auto writes Arabic-Indic digits in the Arabic UI.
    number_digits: 'auto',
    // auto | . | , | ٫
    number_decimal_separator: 'auto',
    // auto | , | . | ٬ | space | apostrophe | none
    number_thousands_separator: 'auto',
    // currency (each currency's own decimal places) | 0…4
    number_money_decimals: 'currency',
    number_quantity_decimals: '3',
    number_percent_decimals: '1',
    // half_up | half_even | half_down | ceil | floor | truncate
    number_rounding_mode: 'half_up',
    // keep | drop_whole (12.00 → 12) | drop_all (12.50 → 12.5) — money only
    number_trailing_zeros: 'keep',
    // minus | parentheses | trailing
    number_negative_format: 'minus',
    // code | symbol
    number_currency_display: 'code',
    // after | before
    number_currency_position: 'after',
});

export const NUMBER_SETTING_KEYS = Object.keys(NUMBER_DEFAULTS);

export const ROUNDING_MODES = ['half_up', 'half_even', 'half_down', 'ceil', 'floor', 'truncate'];

export const numberSettings = reactive({ ...NUMBER_DEFAULTS });

/** Takes the number_* keys out of a settings map; anything absent keeps its value. */
export const applyNumberSettings = (source) => {
    if (!source || typeof source !== 'object') return;

    NUMBER_SETTING_KEYS.forEach((key) => {
        const value = source[key];
        if (value !== undefined && value !== null && value !== '') {
            numberSettings[key] = String(value);
        }
    });
};

// Set only while previewNumberSettings() runs, so a draft never leaks into other screens.
let draft = null;

/** The settings formatting reads now: a preview's draft, else the saved ones. */
export const activeNumberSettings = () => draft || numberSettings;

/** Runs `render` against unsaved choices — the settings screen's live preview. */
export const previewNumberSettings = (choices, render) => {
    draft = { ...NUMBER_DEFAULTS, ...choices };
    try {
        return render();
    } finally {
        draft = null;
    }
};

applyNumberSettings(typeof window !== 'undefined' ? window.systemData?.settings : null);

const MAX_PLACES = 10;
const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

const toNumber = (value) => {
    const parsed = typeof value === 'number' ? value : parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const clampPlaces = (places) => {
    const n = Math.trunc(Number(places));
    return Number.isFinite(n) ? Math.min(MAX_PLACES, Math.max(0, n)) : 0;
};

/** Moves the decimal point through the exponent, so 1.005 does not become 1.00499…. */
const shift = (value, places) => {
    const [mantissa, exponent = '0'] = String(value).split('e');
    return Number(`${mantissa}e${Number(exponent) + places}`);
};

/**
 * Rounds to `places` decimals the way the settings say. `half_up` is the
 * schoolbook rule (2.5 → 3, −2.5 → −3); `half_even` is banker's rounding.
 */
export const roundNumber = (value, places = 0, mode = activeNumberSettings().number_rounding_mode) => {
    const n = toNumber(value);
    if (n === 0) return 0;

    const p = clampPlaces(places);
    // toPrecision(15) drops binary noise: 0.1 + 0.2 scaled is 30.000000000000004.
    const scaled = Number(shift(n, p).toPrecision(15));
    const sign = Math.sign(scaled);
    const abs = Math.abs(scaled);
    const whole = Math.floor(abs);
    const fraction = abs - whole;

    let rounded;
    switch (mode) {
        case 'half_even':
            rounded = sign * (fraction > 0.5 || (fraction === 0.5 && whole % 2 !== 0) ? whole + 1 : whole);
            break;
        case 'half_down':
            rounded = sign * (fraction > 0.5 ? whole + 1 : whole);
            break;
        case 'ceil':
            rounded = Math.ceil(scaled);
            break;
        case 'floor':
            rounded = Math.floor(scaled);
            break;
        case 'truncate':
            rounded = Math.trunc(scaled);
            break;
        default:
            rounded = sign * Math.round(abs);
    }

    // `|| 0` folds −0 into 0, so −0.001 never prints as "-0.00".
    return shift(rounded, -p) || 0;
};

const isArabicUi = () => i18n.global.locale.value !== 'en';

const arabicDigits = () => (
    activeNumberSettings().number_digits === 'arabic'
    || (activeNumberSettings().number_digits === 'auto' && isArabicUi())
);

export const decimalSeparator = () => {
    const chosen = activeNumberSettings().number_decimal_separator;
    if (chosen && chosen !== 'auto') return chosen;
    return arabicDigits() ? '٫' : '.';
};

export const thousandsSeparator = () => {
    const chosen = activeNumberSettings().number_thousands_separator;
    const separator = {
        auto: arabicDigits() ? '٬' : ',',
        space: '\u202F',
        apostrophe: '\u2019',
        none: '',
    }[chosen] ?? chosen ?? ',';

    // A grouping mark that matches the decimal mark would make 1.234 ambiguous.
    return separator === decimalSeparator() ? '' : separator;
};

const localiseDigits = (text) => (
    arabicDigits() ? text.replace(/\d/g, (digit) => ARABIC_DIGITS[digit]) : text
);

const signed = (body, negative) => {
    if (!negative) return body;

    switch (activeNumberSettings().number_negative_format) {
        case 'parentheses':
            return `(${body})`;
        case 'trailing':
            return `${body}-`;
        default:
            // The Arabic letter mark keeps the minus on the number's side in RTL text.
            return `${arabicDigits() ? '\u061C' : ''}-${body}`;
    }
};

/**
 * The building block every formatter uses.
 *
 * @param {number|string} value
 * @param {{
 *   minimumFractionDigits?: number,
 *   maximumFractionDigits?: number,
 *   roundingMode?: string,
 *   trailingZeros?: 'keep'|'drop_whole'|'drop_all',
 *   grouping?: boolean,
 *   prefix?: string,
 *   suffix?: string,
 * }} [options] `prefix`/`suffix` go inside the sign, so a negative reads −$5, not $−5.
 */
export const formatDecimal = (value, options = {}) => {
    const max = clampPlaces(options.maximumFractionDigits ?? options.minimumFractionDigits ?? 0);
    const min = Math.min(max, clampPlaces(options.minimumFractionDigits ?? 0));
    const rounded = roundNumber(value, max, options.roundingMode);

    let [whole, fraction = ''] = Math.abs(rounded).toFixed(max).split('.');
    fraction = fraction.replace(/0+$/, '').padEnd(min, '0');

    const trailing = options.trailingZeros ?? 'keep';
    if (trailing === 'drop_all' || (trailing === 'drop_whole' && /^0*$/.test(fraction))) {
        fraction = fraction.replace(/0+$/, '');
    }

    if (options.grouping !== false) {
        whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '\u0000');
    }

    const digits = localiseDigits(fraction ? `${whole}.${fraction}` : whole)
        .replace('.', decimalSeparator())
        .replace(/\u0000/g, thousandsSeparator());

    return signed(`${options.prefix ?? ''}${digits}${options.suffix ?? ''}`, rounded < 0);
};

const settingPlaces = (key) => clampPlaces(activeNumberSettings()[key] ?? NUMBER_DEFAULTS[key]);

/** Decimals money shows: the currency's own, unless a fixed count is set. */
export const moneyDecimals = (currencyPlaces = 2) => (
    activeNumberSettings().number_money_decimals === 'currency'
        ? clampPlaces(currencyPlaces)
        : settingPlaces('number_money_decimals')
);

/** A count or quantity: up to the configured decimals, none forced. */
export const formatQuantity = (value, maximumFractionDigits = settingPlaces('number_quantity_decimals')) => (
    formatDecimal(value, { maximumFractionDigits })
);

/** A percentage of an already-multiplied value: 12.5 → "12.5%". */
export const formatPercent = (value, maximumFractionDigits = settingPlaces('number_percent_decimals')) => (
    formatDecimal(value, { maximumFractionDigits, suffix: '%' })
);

/** A short figure for a chart axis or a KPI tile: 1.2M / ١٫٢ مليون. */
export const formatCompact = (value, maximumFractionDigits = 1) => {
    const n = toNumber(value);
    const language = isArabicUi() ? 'ar' : 'en';
    const text = new Intl.NumberFormat(`${language}-u-nu-latn`, {
        notation: 'compact',
        maximumFractionDigits,
    }).format(Math.abs(n));

    const body = localiseDigits(text)
        .replace(/\./g, '\u0000')
        .replace(/,/g, thousandsSeparator())
        .replace(/\u0000/g, decimalSeparator())
        // Intl's own direction marks; signed() adds the one this setting wants.
        .replace(/[\u200E\u200F\u061C]/g, '');

    return signed(body, n < 0);
};
