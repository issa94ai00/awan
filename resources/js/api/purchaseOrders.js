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

    delete(id) {
        return api.delete(`/admin/purchase-orders/${id}`);
    }
};
