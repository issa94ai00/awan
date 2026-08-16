import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import analyticsApi from '@/api/analytics';
import { downloadBlob, filenameFromResponse } from '@/utils/download';

/**
 * The scaffolding every analytics screen needs and none of them had.
 *
 * The screens shared a shape — pick a period, fetch, draw, export — but each
 * implemented it separately and none implemented it fully: no error state, no
 * empty state, no indication of when the figures were read, and export buttons
 * that reported success without sending a request.
 *
 * Holding it here means a screen declares *what* to load and gets the period
 * control, the loading and failure states, the refresh and a real export.
 */

const toIso = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    // Local parts, not toISOString(): the latter converts to UTC and shifts the
    // date by a day for anyone east or west of Greenwich, so "today" silently
    // became yesterday's figures.
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${d.getFullYear()}-${month}-${day}`;
};

const daysAgo = (n) => {
    const d = new Date();
    d.setDate(d.getDate() - n);
    return d;
};

/**
 * @param {object} options
 * @param {(params: {from_date: string, to_date: string}) => Promise<any>} options.load
 *   Fetches everything the screen shows. Throwing surfaces the error state.
 * @param {string} [options.exportDomain] Enables the export button when set.
 * @param {number} [options.defaultDays]
 */
export function useAnalyticsPanel({ load, exportDomain = null, defaultDays = 30 }) {
    const { t, locale } = useI18n();

    const loading = ref(false);
    const refreshing = ref(false);
    const exporting = ref(false);
    const error = ref(null);
    const lastUpdatedAt = ref(null);
    /** Distinguishes "not loaded yet" from "loaded and there was nothing". */
    const loadedOnce = ref(false);

    const range = ref([toIso(daysAgo(defaultDays)), toIso(new Date())]);

    const params = computed(() => ({
        from_date: range.value?.[0] ?? toIso(daysAgo(defaultDays)),
        to_date: range.value?.[1] ?? toIso(new Date()),
    }));

    /** Ready-made periods, because most questions are asked about one of these. */
    const rangePresets = computed(() => [
        { text: t('analytics.range_last_7_days'), value: () => [daysAgo(7), new Date()] },
        { text: t('analytics.range_last_30_days'), value: () => [daysAgo(30), new Date()] },
        { text: t('analytics.range_last_90_days'), value: () => [daysAgo(90), new Date()] },
        { text: t('analytics.range_last_year'), value: () => [daysAgo(365), new Date()] },
    ]);

    const lastUpdatedLabel = computed(() => {
        if (!lastUpdatedAt.value) return '';

        return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(lastUpdatedAt.value);
    });

    /**
     * @param {{silent?: boolean}} options `silent` keeps the figures on screen
     *   and spins only the refresh button — replacing a populated report with
     *   skeletons on every refresh loses the reader's place.
     */
    const fetchAll = async ({ silent = false } = {}) => {
        if (silent) {
            refreshing.value = true;
        } else {
            loading.value = true;
        }
        error.value = null;

        try {
            await load(params.value);
            lastUpdatedAt.value = new Date();
            loadedOnce.value = true;
        } catch (err) {
            // The screens used to swallow failures and leave their mock numbers
            // on display, so a dead endpoint looked like healthy data.
            error.value = err?.response?.data?.message || err?.message || t('analytics.load_failed');
            console.error('Analytics load failed:', err);
        } finally {
            loading.value = false;
            refreshing.value = false;
        }
    };

    const refresh = () => fetchAll({ silent: true });

    const applyRange = () => fetchAll();

    const exportCsv = async () => {
        if (!exportDomain) return;

        exporting.value = true;
        try {
            const response = await analyticsApi.exportDomain(exportDomain, params.value);
            const fallback = `analytics-${exportDomain}-${params.value.from_date}-to-${params.value.to_date}.csv`;

            downloadBlob(response.data, filenameFromResponse(response, fallback));
            ElMessage.success(t('analytics.export_ready'));
        } catch (err) {
            // Only claimed after the file is actually in hand.
            ElMessage.error(err?.response?.data?.message || t('analytics.export_failed'));
        } finally {
            exporting.value = false;
        }
    };

    return {
        loading,
        refreshing,
        exporting,
        error,
        loadedOnce,
        range,
        rangePresets,
        params,
        lastUpdatedAt,
        lastUpdatedLabel,
        fetchAll,
        refresh,
        applyRange,
        exportCsv,
    };
}
