import api from './index';

export const ledgerAccountsApi = {
    getAll(params) {
        return api.get('/admin/accounting/ledger-accounts', { params });
    },

    getById(id) {
        return api.get(`/admin/accounting/ledger-accounts/${id}`);
    },

    create(data) {
        return api.post('/admin/accounting/ledger-accounts', data);
    },

    update(id, data) {
        return api.put(`/admin/accounting/ledger-accounts/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/accounting/ledger-accounts/${id}`);
    }
};
