/**
 * The stage a purchase order is at, whatever spelling its status was stored in.
 *
 * Mirrors PurchaseOrder::normalizeStatus on the API. Orders written by earlier
 * versions of the purchases screen carry statuses ('draft', 'ordered',
 * 'received') that match none of the stages the workflow now uses — rows
 * carrying them matched no tab, and offered no action at all.
 */
const LEGACY_STATUSES = {
    draft: 'pending',
    ordered: 'confirmed',
    received: 'completed',
    complete: 'completed',
    canceled: 'cancelled',
};

export const normalizePurchaseOrderStatus = (status) => {
    const value = String(status || '').trim().toLowerCase();
    return LEGACY_STATUSES[value] || value;
};
