import api from './index';

export const salesOrdersApi = {
    getAll(params) {
        return api.get('/sales-orders', { params });
    },

    getById(id) {
        return api.get(`/sales-orders/${id}`);
    },

    create(data) {
        return api.post('/sales-orders', data);
    },

    update(id, data) {
        return api.put(`/sales-orders/${id}`, data);
    },

    delete(id) {
        return api.delete(`/sales-orders/${id}`);
    },

    convertToInvoice(id) {
        return api.post(`/sales-orders/${id}/convert-to-invoice`);
    }
};
