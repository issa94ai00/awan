import api from './index';

export const suppliersApi = {
    getAll(params) {
        return api.get('/admin/suppliers', { params });
    },

    getById(id) {
        return api.get(`/admin/suppliers/${id}`);
    },

    create(data) {
        return api.post('/admin/suppliers', data);
    },

    update(id, data) {
        return api.put(`/admin/suppliers/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/suppliers/${id}`);
    }
};
