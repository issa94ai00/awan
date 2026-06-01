import api from './index';

export const journalEntriesApi = {
    getAll(params) {
        return api.get('/admin/accounting/journal-entries', { params });
    },

    create(data) {
        return api.post('/admin/accounting/journal-entries', data);
    },

    trialBalance(params = {}) {
        return api.get('/admin/accounting/trial-balance', { params });
    }
};
