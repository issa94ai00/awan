import api from './index';

// Stock transfers between the company's warehouses. Ship, receive and cancel
// run through the same workflow the field app's replenishment requests use.
export const inventoryTransfersApi = {
    getAll(params = {}) {
        return api.get('/inventory/transfers', { params });
    },

    get(id) {
        return api.get(`/inventory/transfers/${id}`);
    },

    // Reserves the lines at the source; lines are costed by the server.
    create(data) {
        return api.post('/inventory/transfers', data);
    },

    // fulfillment_method: 'delivery' (goods leave now) or 'pickup' (set aside).
    ship(id, data = {}) {
        return api.post(`/inventory/transfers/${id}/ship`, data);
    },

    // items: [{ transfer_item_id, quantity_received }]; omitted means all of it.
    receive(id, data = {}) {
        return api.post(`/inventory/transfers/${id}/receive`, data);
    },

    cancel(id, data = {}) {
        return api.post(`/inventory/transfers/${id}/cancel`, data);
    },
};
