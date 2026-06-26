import api from './index';

export const posApi = {
    options() {
        return api.get('/pos/options');
    },

    productLookup(params = {}) {
        return api.get('/pos/products/lookup', { params });
    },

    customers(params = {}) {
        return api.get('/pos/customers', { params });
    },

    customerStore(data) {
        return api.post('/pos/customers', data);
    },

    customerUpdate(id, data) {
        return api.put(`/pos/customers/${id}`, data);
    },

    customerDelete(id) {
        return api.delete(`/pos/customers/${id}`);
    },

    customerShow(id) {
        return api.get(`/pos/customers/${id}`);
    }
};
