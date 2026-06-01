import api from './index';

export const quotesApi = {
    getAll(params) {
        return api.get('/quotes', { params });
    },

    getById(id) {
        return api.get(`/quotes/${id}`);
    },

    create(data) {
        return api.post('/quotes', data);
    },

    update(id, data) {
        return api.put(`/quotes/${id}`, data);
    },

    delete(id) {
        return api.delete(`/quotes/${id}`);
    },

    convertToSalesOrder(id) {
        return api.post(`/quotes/${id}/convert-to-sales-order`);
    }
};
