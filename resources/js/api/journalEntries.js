import api from './index';

export const journalEntriesApi = {
    getAll(params) {
        return api.get('/admin/accounting/journal-entries', { params });
    },

    get(id) {
        return api.get(`/admin/accounting/journal-entries/${id}`);
    },

    create(data) {
        return api.post('/admin/accounting/journal-entries', data);
    },

    update(id, data) {
        return api.put(`/admin/accounting/journal-entries/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/accounting/journal-entries/${id}`);
    }
};
