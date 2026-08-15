import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import {
    FALLBACK_CURRENCY,
    baseCurrencyCode,
    currencyInfo,
    formatMoney as formatMoneyWith,
    formatNumber as formatNumberWith,
} from '@/utils/currency';

/**
 * Money on the admin screens, written in the currency the books are kept in.
 *
 * The formatting itself lives in `utils/currency`; this is the Vue-reactive way
 * in. It differs in one respect: the base code is read from the settings store
 * first, which is refetched from `/settings` and so reflects an admin moving the
 * base without a page reload, before falling back to the boot payload that is
 * present from the first paint. Both trace back to `CurrencyService::baseCode()`,
 * so the store narrows the freshness window rather than adding a second answer.
 *
 * Depending on `locale` makes the returned computeds re-evaluate on a language
 * switch, so a screen relabels its figures instead of holding the old digits.
 */
export function useCurrency() {
    const settingsStore = useSettingsStore();
    const { locale } = useI18n();

    const baseCode = computed(() => (
        settingsStore.baseCurrency
        || settingsStore.data?.default_currency
        || baseCurrencyCode()
    ));

    /** The base currency's descriptor, preferring the store's freshly-fetched list. */
    const baseCurrency = computed(() => {
        const code = baseCode.value;
        const stored = settingsStore.currencies?.find((currency) => currency?.code === code);

        return stored || currencyInfo(code) || { ...FALLBACK_CURRENCY, code };
    });

    const decimals = computed(() => {
        const places = Number(baseCurrency.value?.decimal_places);
        return Number.isFinite(places) && places >= 0 ? places : FALLBACK_CURRENCY.decimal_places;
    });

    const formatMoney = (value, options = {}) => {
        // Read so the enclosing computed re-runs when the language changes.
        void locale.value;

        return formatMoneyWith(value, {
            code: baseCode.value,
            decimals: decimals.value,
            ...options,
        });
    };

    const formatNumber = (value) => {
        void locale.value;
        return formatNumberWith(value);
    };

    /** Zero in the base currency, for a metric's placeholder before it loads. */
    const zeroMoney = computed(() => formatMoney(0));

    return {
        baseCode,
        baseCurrency,
        formatMoney,
        formatNumber,
        zeroMoney,
    };
}
