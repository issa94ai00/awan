import api from './index';

// Credit notes are raised by the returns settlement: read and cancel only.
export const creditNotesApi = {
    getAll(params = {}) {
        return api.get('/admin/credit-notes', { params });
    },

    summary(params = {}) {
        return api.get('/admin/credit-notes/summary', { params });
    },

    get(id) {
        return api.get(`/admin/credit-notes/${id}`);
    },

    // Refused by the server once any part of the note has been used.
    cancel(id) {
        return api.post(`/admin/credit-notes/${id}/cancel`);
    },
};
