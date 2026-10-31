/**
 * Arabic & English Tafqeet (Number to Words / التفقيط) Utility.
 *
 * Converts financial and numeric amounts into legally and grammatically accurate
 * spoken words (e.g., for official invoices, quotations, receipts, and payment vouchers).
 *
 * Examples:
 *   tafqeet(15420.75, { currency: 'USD' })
 *   => "فقط خمسة عشر ألفاً وأربعمائة وعشرون دولاراً أمريكياً وخمسة وسبعون سنتاً لا غير"
 *
 *   tafqeet(2500000, { currency: 'SYP' })
 *   => "فقط مليونان وخمسمائة ألف ليرة سورية لا غير"
 */

import i18n from '@/i18n';
import { baseCurrencyCode, currencyInfo } from '@/utils/currency';

/**
 * Currency dictionary containing singular, dual, plural, accusative, genitive,
 * and sub-unit grammatical forms for Arabic and English.
 */
const CURRENCY_GRAMMAR = {
    USD: {
        code: 'USD',
        decimals: 2,
        ar: {
            singular: 'دولار أمريكي',
            dual: 'دولاران أمريكيان',
            plural: 'دولارات أمريكية',
            accusative: 'دولاراً أمريكياً',
            genitive: 'دولار أمريكي',
            feminine: false,
            subSingular: 'سنت',
            subDual: 'سنتان',
            subPlural: 'سنتات',
            subAccusative: 'سنتاً',
            subGenitive: 'سنت',
            subFeminine: false,
        },
        en: {
            singular: 'US Dollar',
            plural: 'US Dollars',
            subSingular: 'Cent',
            subPlural: 'Cents',
        },
    },
    SYP: {
        code: 'SYP',
        decimals: 0,
        ar: {
            singular: 'ليرة سورية',
            dual: 'ليرتان سوريتان',
            plural: 'ليرات سورية',
            accusative: 'ليرة سورية',
            genitive: 'ليرة سورية',
            feminine: true,
            subSingular: 'قرش',
            subDual: 'قرشان',
            subPlural: 'قروش',
            subAccusative: 'قرشاً',
            subGenitive: 'قرش',
            subFeminine: false,
        },
        en: {
            singular: 'Syrian Pound',
            plural: 'Syrian Pounds',
            subSingular: 'Piastre',
            subPlural: 'Piastres',
        },
    },
    SAR: {
        code: 'SAR',
        decimals: 2,
        ar: {
            singular: 'ريال سعودي',
            dual: 'ريالان سعوديان',
            plural: 'ريالات سعودية',
            accusative: 'ريالاً سعودياً',
            genitive: 'ريال سعودي',
            feminine: false,
            subSingular: 'هللة',
            subDual: 'هللتان',
            subPlural: 'هللات',
            subAccusative: 'هللة',
            subGenitive: 'هللة',
            subFeminine: true,
        },
        en: {
            singular: 'Saudi Riyal',
            plural: 'Saudi Riyals',
            subSingular: 'Halala',
            subPlural: 'Halalas',
        },
    },
    AED: {
        code: 'AED',
        decimals: 2,
        ar: {
            singular: 'درهم إماراتي',
            dual: 'درهمان إماراتيان',
            plural: 'دراهم إماراتية',
            accusative: 'درهماً إماراتياً',
            genitive: 'درهم إماراتي',
            feminine: false,
            subSingular: 'فلس',
            subDual: 'فلسان',
            subPlural: 'فلوس',
            subAccusative: 'فلساً',
            subGenitive: 'فلس',
            subFeminine: false,
        },
        en: {
            singular: 'UAE Dirham',
            plural: 'UAE Dirhams',
            subSingular: 'Fils',
            subPlural: 'Fils',
        },
    },
    EUR: {
        code: 'EUR',
        decimals: 2,
        ar: {
            singular: 'يورو',
            dual: 'يوروان',
            plural: 'يوروهات',
            accusative: 'يورو',
            genitive: 'يورو',
            feminine: false,
            subSingular: 'سنت',
            subDual: 'سنتان',
            subPlural: 'سنتات',
            subAccusative: 'سنتاً',
            subGenitive: 'سنت',
            subFeminine: false,
        },
        en: {
            singular: 'Euro',
            plural: 'Euros',
            subSingular: 'Cent',
            subPlural: 'Cents',
        },
    },
    JOD: {
        code: 'JOD',
        decimals: 2,
        ar: {
            singular: 'دينار أردني',
            dual: 'ديناران أردنيان',
            plural: 'دنانير أردنية',
            accusative: 'ديناراً أردنياً',
            genitive: 'دينار أردني',
            feminine: false,
            subSingular: 'قرش',
            subDual: 'قرشان',
            subPlural: 'قروش',
            subAccusative: 'قرشاً',
            subGenitive: 'قرش',
            subFeminine: false,
        },
        en: {
            singular: 'Jordanian Dinar',
            plural: 'Jordanian Dinars',
            subSingular: 'Piastre',
            subPlural: 'Piastres',
        },
    },
    TRY: {
        code: 'TRY',
        decimals: 2,
        ar: {
            singular: 'ليرة تركية',
            dual: 'ليرتان تركيتان',
            plural: 'ليرات تركية',
            accusative: 'ليرة تركية',
            genitive: 'ليرة تركية',
            feminine: true,
            subSingular: 'قرش',
            subDual: 'قرشان',
            subPlural: 'قروش',
            subAccusative: 'قرشاً',
            subGenitive: 'قرش',
            subFeminine: false,
        },
        en: {
            singular: 'Turkish Lira',
            plural: 'Turkish Liras',
            subSingular: 'Kurus',
            subPlural: 'Kurus',
        },
    },
};

/**
 * Resolves grammar configuration for any currency code, using database system data as fallback.
 */
function resolveCurrencyGrammar(currencyCode) {
    const code = String(currencyCode || baseCurrencyCode()).toUpperCase().trim();
    if (CURRENCY_GRAMMAR[code]) {
        return CURRENCY_GRAMMAR[code];
    }

    const info = currencyInfo(code);
    const nameAr = info?.name_ar || info?.name || code;
    const nameEn = info?.name_en || info?.name || code;
    const decimals = Number.isFinite(Number(info?.decimal_places)) ? Number(info.decimal_places) : 2;

    return {
        code,
        decimals,
        ar: {
            singular: nameAr,
            dual: nameAr,
            plural: nameAr,
            accusative: nameAr,
            genitive: nameAr,
            feminine: false,
            subSingular: 'جزء',
            subDual: 'جزآن',
            subPlural: 'أجزاء',
            subAccusative: 'جزءاً',
            subGenitive: 'جزء',
            subFeminine: false,
        },
        en: {
            singular: nameEn,
            plural: nameEn,
            subSingular: 'Cent',
            subPlural: 'Cents',
        },
    };
}

/**
 * Convert a 1-999 group into Arabic text with gender awareness.
 */
function arabicGroupToWords(num, isFeminine = false, isLastGroup = false) {
    if (num <= 0) return '';

    const onesM = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة'];
    const onesF = ['', 'واحدة', 'اثنتان', 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثمان', 'تسع', 'عشر'];
    const teensM = ['عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
    const teensF = ['عشر', 'إحدى عشرة', 'اثنتا عشرة', 'ثلاث عشرة', 'أربع عشرة', 'خمس عشرة', 'ست عشرة', 'سبع عشرة', 'ثماني عشرة', 'تسع عشرة'];
    const tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    const hundreds = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    const h = Math.floor(num / 100);
    const remainder = num % 100;
    const t = Math.floor(remainder / 10);
    const u = remainder % 10;

    const parts = [];
    if (h > 0) {
        parts.push(hundreds[h]);
    }

    const onesArr = (isFeminine && isLastGroup) ? onesF : onesM;
    const teensArr = (isFeminine && isLastGroup) ? teensF : teensM;

    if (remainder > 0) {
        if (remainder <= 10) {
            parts.push(onesArr[remainder]);
        } else if (remainder < 20) {
            parts.push(teensArr[remainder - 10]);
        } else {
            if (u > 0) {
                parts.push(onesArr[u] + ' و' + tens[t]);
            } else {
                parts.push(tens[t]);
            }
        }
    }

    return parts.join(' و');
}

/**
 * Convert full numeric amount into Arabic words with currency units and sub-units.
 */
export function numberToArabicWords(number, currencyCode = null) {
    if (number === null || number === undefined || number === '' || Number.isNaN(Number(number))) {
        return '';
    }

    const num = Number(number);
    const isNegative = num < 0;
    const absVal = Math.abs(num);
    let integerPart = Math.floor(absVal);

    const currConfig = resolveCurrencyGrammar(currencyCode);
    const curr = currConfig.ar;
    const decimals = currConfig.decimals;

    const decMultiplier = Math.pow(10, decimals);
    const decimalPart = decimals > 0 ? Math.round((absVal - integerPart) * decMultiplier) : 0;
    if (decimalPart >= decMultiplier) {
        integerPart += 1;
    }

    if (integerPart === 0 && decimalPart === 0) {
        return `صفر ${curr.singular}`;
    }

    const scales = [
        { value: 1000000000000, singular: 'تريليون', dual: 'تريليونان', plural: 'تريليونات', accusative: 'تريليوناً' },
        { value: 1000000000, singular: 'مليار', dual: 'ملياران', plural: 'مليارات', accusative: 'ملياراً' },
        { value: 1000000, singular: 'مليون', dual: 'مليونان', plural: 'ملايين', accusative: 'مليوناً' },
        { value: 1000, singular: 'ألف', dual: 'ألفان', plural: 'آلاف', accusative: 'ألفاً' },
    ];

    let currentVal = integerPart;
    const parts = [];

    for (let i = 0; i < scales.length; i++) {
        const scale = scales[i];
        if (currentVal >= scale.value) {
            const groupVal = Math.floor(currentVal / scale.value);
            currentVal %= scale.value;
            const isScaleFollowedByMore = currentVal > 0;
            const noun = isScaleFollowedByMore ? scale.accusative : scale.singular;

            if (groupVal === 1) {
                parts.push(scale.singular);
            } else if (groupVal === 2) {
                parts.push(scale.dual);
            } else if (groupVal >= 3 && groupVal <= 10) {
                parts.push(arabicGroupToWords(groupVal, false, false) + ' ' + scale.plural);
            } else {
                parts.push(arabicGroupToWords(groupVal, false, false) + ' ' + noun);
            }
        }
    }

    if (currentVal > 0) {
        if (integerPart === 1 && parts.length === 0) {
            parts.push(curr.singular + (curr.feminine ? ' واحدة' : ' واحد'));
        } else if (integerPart === 2 && parts.length === 0) {
            parts.push(curr.dual);
        } else {
            const words = arabicGroupToWords(currentVal, curr.feminine, true);
            let cName = '';
            const rem100 = currentVal % 100;
            if (rem100 === 0) {
                cName = curr.genitive;
            } else if (rem100 >= 3 && rem100 <= 10) {
                cName = curr.plural;
            } else if (rem100 >= 11) {
                cName = curr.accusative;
            } else {
                cName = curr.genitive;
            }
            parts.push(words + ' ' + cName);
        }
    } else if (parts.length > 0) {
        // Pure thousands/millions/billions (e.g. 5,000,000 USD)
        parts[parts.length - 1] += ' ' + curr.genitive;
    }

    let result = parts.join(' و');

    if (decimalPart > 0) {
        let decWords = '';
        if (decimalPart === 1) {
            decWords = curr.subSingular + (curr.subFeminine ? ' واحدة' : ' واحد');
        } else if (decimalPart === 2) {
            decWords = curr.subDual;
        } else {
            const words = arabicGroupToWords(decimalPart, curr.subFeminine, true);
            const rem100 = decimalPart % 100;
            let subName = '';
            if (rem100 === 0) {
                subName = curr.subGenitive;
            } else if (rem100 >= 3 && rem100 <= 10) {
                subName = curr.subPlural;
            } else if (rem100 >= 11) {
                subName = curr.subAccusative;
            } else {
                subName = curr.subGenitive;
            }
            decWords = words + ' ' + subName;
        }

        if (integerPart > 0) {
            result += ' و' + decWords;
        } else {
            result = decWords;
        }
    }

    return (isNegative ? 'سالب ' : '') + result;
}

/**
 * Convert a 1-999 group into English words.
 */
function englishGroupToWords(num) {
    if (num <= 0) return '';
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    const h = Math.floor(num / 100);
    const remainder = num % 100;
    const t = Math.floor(remainder / 10);
    const u = remainder % 10;

    const parts = [];
    if (h > 0) {
        parts.push(ones[h] + ' Hundred');
    }

    if (remainder > 0) {
        if (remainder < 20) {
            parts.push(ones[remainder]);
        } else {
            if (u > 0) {
                parts.push(tens[t] + '-' + ones[u]);
            } else {
                parts.push(tens[t]);
            }
        }
    }

    return parts.join(' ');
}

/**
 * Convert full numeric amount into English words with currency units and sub-units.
 */
export function numberToEnglishWords(number, currencyCode = null) {
    if (number === null || number === undefined || number === '' || Number.isNaN(Number(number))) {
        return '';
    }

    const num = Number(number);
    const isNegative = num < 0;
    const absVal = Math.abs(num);
    let integerPart = Math.floor(absVal);

    const currConfig = resolveCurrencyGrammar(currencyCode);
    const curr = currConfig.en;
    const decimals = currConfig.decimals;

    const decMultiplier = Math.pow(10, decimals);
    const decimalPart = decimals > 0 ? Math.round((absVal - integerPart) * decMultiplier) : 0;
    if (decimalPart >= decMultiplier) {
        integerPart += 1;
    }

    if (integerPart === 0 && decimalPart === 0) {
        return `Zero ${curr.plural}`;
    }

    const scales = [
        { value: 1000000000000, name: 'Trillion' },
        { value: 1000000000, name: 'Billion' },
        { value: 1000000, name: 'Million' },
        { value: 1000, name: 'Thousand' },
    ];

    let currentVal = integerPart;
    const parts = [];

    for (let i = 0; i < scales.length; i++) {
        const scale = scales[i];
        if (currentVal >= scale.value) {
            const groupVal = Math.floor(currentVal / scale.value);
            currentVal %= scale.value;
            parts.push(englishGroupToWords(groupVal) + ' ' + scale.name);
        }
    }

    if (currentVal > 0) {
        parts.push(englishGroupToWords(currentVal));
    }

    const intWords = parts.join(' ');
    const currName = integerPart === 1 ? curr.singular : curr.plural;
    let result = (intWords ? `${intWords} ${currName}` : '').trim();

    if (decimalPart > 0) {
        const subWords = englishGroupToWords(decimalPart);
        const subName = decimalPart === 1 ? curr.subSingular : curr.subPlural;
        if (result) {
            result += ` and ${subWords} ${subName}`;
        } else {
            result = `${subWords} ${subName}`;
        }
    }

    return (isNegative ? 'Minus ' : '') + result;
}

/**
 * Universal Tafqeet entry point for documents (Invoices, Quotations, Vouchers).
 *
 * @param {number|string} amount
 * @param {object} [options]
 * @param {string} [options.currency] Currency code (defaults to base currency)
 * @param {string} [options.locale] 'ar' or 'en' (defaults to current active locale)
 * @param {boolean} [options.prefix] Prepend "فقط " / "Only " (default: true)
 * @param {boolean} [options.suffix] Append " لا غير" / "." (default: true)
 * @returns {string} Formatted text in words
 */
export function tafqeet(amount, options = {}) {
    let raw = amount;
    if (typeof raw === 'string') {
        raw = raw.replace(/,/g, '').trim();
    }
    if (raw === null || raw === undefined || raw === '' || Number.isNaN(Number(raw))) {
        return '';
    }
    const cleanAmount = Number(raw);

    const activeLocale = options.locale || i18n?.global?.locale?.value || 'ar';
    const isArabic = activeLocale !== 'en';
    const currency = options.currency || baseCurrencyCode();
    const addPrefix = options.prefix !== false;
    const addSuffix = options.suffix !== false;

    if (isArabic) {
        const words = numberToArabicWords(cleanAmount, currency);
        if (!words) return '';
        const prefix = addPrefix ? 'فقط ' : '';
        const suffix = addSuffix ? ' لا غير' : '';
        return `${prefix}${words}${suffix}`.trim();
    }

    const words = numberToEnglishWords(cleanAmount, currency);
    if (!words) return '';
    const prefix = addPrefix ? 'Only ' : '';
    const suffix = addSuffix ? '.' : '';
    return `${prefix}${words}${suffix}`.trim();
}

export default tafqeet;
