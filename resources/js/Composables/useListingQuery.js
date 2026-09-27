import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

export const PER_PAGE_OPTIONS = [12, 24, 48];

/**
 * A storefront product listing whose state is its URL's query string — page,
 * sort, search, in-stock and page size — so the back button steps through
 * pages, a refresh keeps the place, and a link shares exactly this list.
 *
 * Defaults are left out of the URL to keep it short.
 *
 * @param {{ sorts: string[], defaultSort: string }} options
 */
export function useListingQuery({ sorts, defaultSort }) {
    const route = useRoute();
    const router = useRouter();

    const query = computed(() => {
        const q = route.query;
        const page = Number.parseInt(q.page, 10);
        const per = Number.parseInt(q.per, 10);
        return {
            page: Number.isFinite(page) && page > 0 ? page : 1,
            sort: sorts.includes(q.sort) ? q.sort : defaultSort,
            q: typeof q.q === 'string' ? q.q.trim() : '',
            stock: q.stock === '1',
            per: PER_PAGE_OPTIONS.includes(per) ? per : PER_PAGE_OPTIONS[0],
        };
    });

    const buildQuery = (changes = {}) => {
        const next = { ...query.value, ...changes };
        const out = {};
        if (next.q) out.q = next.q;
        if (next.page > 1) out.page = String(next.page);
        if (next.sort !== defaultSort) out.sort = next.sort;
        if (next.stock) out.stock = '1';
        if (next.per !== PER_PAGE_OPTIONS[0]) out.per = String(next.per);
        return out;
    };

    const pageLink = (page) => ({ path: route.path, query: buildQuery({ page }) });

    /** Anything but a page change starts the list again from its first page. */
    const setQuery = (changes, { replace = false } = {}) => {
        const location = { path: route.path, query: buildQuery({ page: 1, ...changes }) };
        return replace ? router.replace(location) : router.push(location);
    };

    /** The API's parameters for the listing as it stands. */
    const apiParams = (locale) => {
        const { page, per, sort, q, stock } = query.value;
        return {
            page,
            per_page: per,
            ...(sort !== defaultSort ? { sort } : {}),
            ...(sort === 'name' ? { lang: locale } : {}),
            ...(q ? { search: q } : {}),
            ...(stock ? { in_stock: 1 } : {}),
        };
    };

    return { query, buildQuery, pageLink, setQuery, apiParams };
}
