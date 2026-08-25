/**
 * Reading which warehouse can fill a sale line.
 *
 * A sale line can draw its quantity from more than one warehouse — the
 * common case is a single source, but a seller can split a line across
 * shelves when one alone cannot cover it. Each line therefore carries a list
 * of allocations (`{ warehouse_id, quantity }`) instead of one warehouse and
 * one quantity, and these helpers reason in terms of that list.
 *
 * Two small rules decide whether the direct-sale screen tells the truth, and
 * both are easy to get subtly wrong inside a large component:
 *
 *   - A product can sit in several rows of one warehouse (different bins), so
 *     the free quantities have to be summed per warehouse before being offered.
 *     Showing one bin's figure makes a warehouse that can fill the line look
 *     like it cannot.
 *   - Every allocation drawing the same product from the same warehouse has to
 *     be counted together — whether it is two lines or two allocations on one
 *     line. Judged separately, each looks satisfiable while together they
 *     overdraw the shelf, which is exactly the case the server refuses, so the
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

/** Free quantity a line's product has at one warehouse. */
export function availableAt(item, warehouseId) {
    const source = (item?.sources || []).find((row) => row.warehouse_id === warehouseId);
    return source ? source.available : 0;
}

/**
 * The combined quantity every line asks of one product at one warehouse.
 *
 * Pools every allocation on every line, not just the one being judged —
 * matching how the server totals them before deciding.
 */
export function askedFor(items, productId, warehouseId) {
    return (items || []).reduce((sum, item) => {
        if (item.product_id !== productId) return sum;

        return sum + (item.allocations || [])
            .filter((allocation) => allocation.warehouse_id === warehouseId)
            .reduce((s, allocation) => s + (Number(allocation.quantity) || 0), 0);
    }, 0);
}

/** Whether one allocation asks for more than its warehouse holds. */
export function isAllocationShort(items, item, allocation) {
    if (!allocation?.warehouse_id) return false;
    return askedFor(items, item.product_id, allocation.warehouse_id) > availableAt(item, allocation.warehouse_id);
}

/** Whether any allocation on a line asks for more than its warehouse holds. */
export function isLineShort(items, item) {
    return (item?.allocations || []).some((allocation) => isAllocationShort(items, item, allocation));
}

/** The quantity a line asks for in total, across every source it draws from. */
export function lineQuantity(item) {
    return (item?.allocations || []).reduce((sum, allocation) => sum + (Number(allocation.quantity) || 0), 0);
}

/**
 * The source to preselect for a newly added line or allocation.
 *
 * The first that can cover the whole request, so the common case needs no
 * interaction. Falls back to the fullest warehouse — which will be marked short,
 * telling the seller the problem rather than leaving the field blank and making
 * them discover it on submit.
 */
export function preferredSource(sources, quantity) {
    if (!sources || sources.length === 0) return null;

    const covering = sources.find((row) => row.available >= quantity);
    return (covering ?? sources[0]).warehouse_id;
}
