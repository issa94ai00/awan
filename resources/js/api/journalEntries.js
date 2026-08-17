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

    // A posted entry is never rewritten or removed — the API refuses both.
    // Correcting one means reversing it and recording the right entry, so the
    // journal keeps the whole story.
    reverse(id, data = {}) {
        return api.post(`/admin/accounting/journal-entries/${id}/reverse`, data);
    }
};
