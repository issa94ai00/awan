/**
 * Reading which warehouse can fill a sale line.
 *
 * Two small rules decide whether the direct-sale screen tells the truth, and
 * both are easy to get subtly wrong inside a large component:
 *
 *   - A product can sit in several rows of one warehouse (different bins), so
 *     the free quantities have to be summed per warehouse before being offered.
 *     Showing one bin's figure makes a warehouse that can fill the line look
 *     like it cannot.
 *   - Two lines of the same product from the same warehouse have to be counted
 *     together. Judged separately, each looks satisfiable while together they
 *     overdraw the shelf — which is exactly the case the server refuses, so the
 *     screen must refuse it in the same terms or the seller is surprised at the
 *     till.
 *
 * They live here rather than in the screen so they can be tested directly, and
 * so there is one statement of the rule instead of one per caller.
 */

/**
 * Collapses raw warehouse-inventory rows into one entry per warehouse.
 *
 * Sorted by free quantity, most first: the warehouse that can fill the line is
 * almost always the one the seller wants, so it should be the first thing under
 * the cursor.
 *
 * @param {Array<object>} rows as returned by /admin/inventory/stock
 * @returns {Array<{warehouse_id:number, warehouse_name:string, available:number}>}
 */
export function summariseSources(rows) {
    const byWarehouse = new Map();

    for (const row of rows || []) {
        const id = row?.warehouse_id;
        if (!id) continue;

        // `available` is the computed free figure; the raw column is the
        // fallback for callers that did not ask for it.
        const free = Math.max(0, Number(row.available ?? row.available_quantity ?? 0));
        const existing = byWarehouse.get(id);

        if (existing) {
            existing.available += free;
        } else {
            byWarehouse.set(id, {
                warehouse_id: id,
                warehouse_name: row.warehouse?.name || `#${id}`,
                available: free,
            });
        }
    }

    return [...byWarehouse.values()].sort((a, b) => b.available - a.available);
}

/** Free quantity at the warehouse a line is drawing from. */
export function availableAt(item) {
    const source = (item?.sources || []).find((row) => row.warehouse_id === item.warehouse_id);
    return source ? source.available : 0;
}

/**
 * Whether a line asks for more than its warehouse holds.
 *
 * Pools every line drawing the same product from the same warehouse, matching
 * how the server totals them before deciding.
 *
 * @param {Array<object>} items every line on the sale
 * @param {object} item the line being judged
 */
export function isLineShort(items, item) {
    if (!item?.warehouse_id) return false;

    const asked = (items || [])
        .filter((row) => row.product_id === item.product_id && row.warehouse_id === item.warehouse_id)
        .reduce((sum, row) => sum + (Number(row.quantity) || 0), 0);

    return asked > availableAt(item);
}

/**
 * The source to preselect for a newly added line.
 *
 * The first that can cover the whole line, so the common case needs no
 * interaction. Falls back to the fullest warehouse — which will be marked short,
 * telling the seller the problem rather than leaving the field blank and making
 * them discover it on submit.
 */
export function preferredSource(sources, quantity) {
    if (!sources || sources.length === 0) return null;

    const covering = sources.find((row) => row.available >= quantity);
    return (covering ?? sources[0]).warehouse_id;
}
