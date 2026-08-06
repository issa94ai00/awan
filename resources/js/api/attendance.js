import api from './index';

export const attendanceApi = {
    getAll(params = {}) {
        return api.get('/admin/attendance', { params });
    },

    getById(id) {
        return api.get(`/admin/attendance/${id}`);
    },

    create(data) {
        return api.post('/admin/attendance', data);
    },

    update(id, data) {
        return api.put(`/admin/attendance/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/attendance/${id}`);
    }
};
