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
    }
};
