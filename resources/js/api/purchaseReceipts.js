import api from './index';

export const purchaseReceiptsApi = {
    getAll(params) {
        return api.get('/purchase-receipts', { params });
    },

    getById(id) {
        return api.get(`/purchase-receipts/${id}`);
    },

    create(data) {
        return api.post('/purchase-receipts', data);
    },

    update(id, data) {
        return api.put(`/purchase-receipts/${id}`, data);
    },

    delete(id) {
        return api.delete(`/purchase-receipts/${id}`);
    }
};
