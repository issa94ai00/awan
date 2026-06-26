import api from './index';

export default {
    // Public API
    store(payload) {
        return api.post('/inquiries', payload);
    },

    userIndex(params = {}) {
        return api.get('/user/inquiries', { params });
    },

    userShow(id) {
        return api.get(`/user/inquiries/${id}`);
    },

    // Admin API
    adminIndex(params = {}) {
        return api.get('/admin/inquiries', { params });
    },

    adminShow(id) {
        return api.get(`/admin/inquiries/${id}`);
    },

    adminReply(id, payload) {
        return api.post(`/admin/inquiries/${id}/replies`, payload);
    },

    adminUpdate(id, payload) {
        return api.put(`/admin/inquiries/${id}`, payload);
    },

    adminDelete(id) {
        return api.delete(`/admin/inquiries/${id}`);
    },

    adminClose(id) {
        return api.post(`/admin/inquiries/${id}/close`);
    },

    adminReopen(id) {
        return api.post(`/admin/inquiries/${id}/reopen`);
    },

    adminAssign(id, payload) {
        return api.post(`/admin/inquiries/${id}/assign`, payload);
    },

    adminBulkUpdate(payload) {
        return api.post('/admin/inquiries/bulk-update', payload);
    },

    adminBulkDelete(payload) {
        return api.post('/admin/inquiries/bulk-delete', payload);
    }
}
