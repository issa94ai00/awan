import api from './index';

export const leaveRequestsApi = {
    getAll(params = {}) {
        return api.get('/admin/leave-requests', { params });
    },

    getById(id) {
        return api.get(`/admin/leave-requests/${id}`);
    },

    create(data) {
        return api.post('/admin/leave-requests', data);
    },

    update(id, data) {
        return api.put(`/admin/leave-requests/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/leave-requests/${id}`);
    }
};
