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

    customerShow(id) {
        return api.get(`/pos/customers/${id}`);
    }
};
