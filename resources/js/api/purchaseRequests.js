import api from './index';

export const purchaseRequestsApi = {
    getAll(params = {}) {
        return api.get('/purchase-requests', { params });
    },

    get(id) {
        return api.get(`/purchase-requests/${id}`);
    },

    updateStatus(id, status) {
        return api.put(`/purchase-requests/${id}/status`, { status });
    },

    // The lines of a pending request: product, size and quantity each. The
    // server prices them and plans the warehouses again.
    updateItems(id, items) {
        return api.put(`/purchase-requests/${id}/items`, { items });
    }
};
