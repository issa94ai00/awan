/**
 * Small helpers shared by the sales stores.
 */

/**
 * Reads a pagination block defensively.
 *
 * The stores used to do `data.pagination.current_page` directly, which throws a
 * TypeError whenever an endpoint answers without a pagination block (errors,
 * empty results, or the non-paginated shapes some of these controllers return).
 */
export const readPagination = (raw, previous = {}, fallbackTotal = 0) => {
    const pagination = raw || {};
    return {
        current_page: pagination.current_page || 1,
        per_page: pagination.per_page || previous.per_page || 20,
        total: pagination.total ?? fallbackTotal,
        last_page: pagination.last_page || 1,
    };
};

/**
 * Sends the user to the SPA login when there is no token, mirroring the guard
 * the sales stores each had inline.
 *
 * @returns {boolean} true when the caller may continue.
 */
export const requireAuth = (auth, router, redirect) => {
    const token = localStorage.getItem('token') || (auth?.user ? '1' : null);
    if (!token) {
        router.push({ path: '/login', query: { redirect } });
        return false;
    }
    return true;
};
