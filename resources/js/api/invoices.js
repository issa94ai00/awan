import api from './index';

export const invoicesApi = {
    getAll(params = {}) {
        return api.get('/invoices', { params });
    },

    getById(id) {
        return api.get(`/invoices/${id}`);
    },

    create(data) {
        return api.post('/invoices', data);
    },

    updateStatus(id, data) {
        return api.put(`/invoices/${id}/status`, data);
    },

    delete(id) {
        return api.delete(`/invoices/${id}`);
    },

    summary() {
        return api.get('/invoices/summary/stats');
    }
};
