// Shared by the transfers list, its detail drawer and the printed note, so a
// stage reads the same everywhere. Mirrors App\Models\InventoryTransfer.

export const TRANSFER_STATUSES = ['pending', 'ready_for_pickup', 'in_transit', 'completed', 'cancelled'];

export const transferStatusTone = (status) => ({
    pending: 'warning',
    ready_for_pickup: 'primary',
    in_transit: 'primary',
    completed: 'success',
    cancelled: 'info',
}[status] || 'info');

export const transferStatusLabel = (t, status) => (TRANSFER_STATUSES.includes(status) ? t(`trf_status_${status}`) : status || '—');

export const transferMethodLabel = (t, method) => (method === 'delivery' || method === 'pickup' ? t(`trf_method_${method}`) : '—');

// The source can still approve it.
export const canShip = (transfer) => transfer?.status === 'pending';

// Approved either way: on the road, or set aside for collection.
export const canReceive = (transfer) => ['in_transit', 'ready_for_pickup'].includes(transfer?.status);

// Only while the goods are still at the source.
export const canCancel = (transfer) => ['pending', 'ready_for_pickup'].includes(transfer?.status);

/**
 * The most a line can be received as: a pickup gives what was set aside, a
 * delivery brings what was put on the van. Mirrors ReplenishmentWorkflowService.
 */
export const receiveCeiling = (transfer, item) => (transfer?.fulfillment_method === 'pickup'
    ? Number(item.quantity_requested) || 0
    : Number(item.quantity_shipped || item.quantity_requested) || 0);

export const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return `${date.toLocaleDateString('en-CA')} ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
};

export const transferProductName = (item) => item?.product?.name_ar || item?.product?.name_en || item?.product?.name || `#${item?.product_id}`;
