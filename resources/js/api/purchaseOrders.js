import api from './index';

export const purchaseOrdersApi = {
    getAll(params) {
        return api.get('/admin/purchase-orders', { params });
    },

    getById(id) {
        return api.get(`/admin/purchase-orders/${id}`);
    },

    create(data) {
        return api.post('/admin/purchase-orders', data);
    },

    update(id, data) {
        return api.put(`/admin/purchase-orders/${id}`, data);
    },

    // Moves the order along the workflow without resending its lines — update()
    // deletes and rewrites every one of them.
    updateStatus(id, status) {
        return api.put(`/admin/purchase-orders/${id}/status`, { status });
    },

    delete(id) {
        return api.delete(`/admin/purchase-orders/${id}`);
    }
};
