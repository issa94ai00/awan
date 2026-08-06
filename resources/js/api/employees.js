import api from './index';

export const employeesApi = {
    getAll(params = {}) {
        return api.get('/admin/employees', { params });
    },

    getById(id) {
        return api.get(`/admin/employees/${id}`);
    },

    create(data) {
        return api.post('/admin/employees', data);
    },

    update(id, data) {
        return api.put(`/admin/employees/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/employees/${id}`);
    }
};
