import api from './index';

export const payrollsApi = {
    getAll(params = {}) {
        return api.get('/payrolls', { params });
    },

    getById(id) {
        return api.get(`/payrolls/${id}`);
    },

    create(data) {
        return api.post('/payrolls', data);
    },

    update(id, data) {
        return api.put(`/payrolls/${id}`, data);
    },

    delete(id) {
        return api.delete(`/payrolls/${id}`);
    },

    autoGenerate(data) {
        return api.post('/payrolls/auto-generate', data);
    }
};
