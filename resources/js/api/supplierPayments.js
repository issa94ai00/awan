import api from './index';

export const supplierPaymentsApi = {
    getAll(params) {
        return api.get('/admin/supplier-payments', { params });
    },

    get(id) {
        return api.get(`/admin/supplier-payments/${id}`);
    },

    create(data) {
        return api.post('/admin/supplier-payments', data);
    },

    // What is still owed to each supplier, read off the balances that receipts
    // and payments maintain.
    outstanding(params) {
        return api.get('/admin/supplier-payments/outstanding', { params });
    },

    // There is no update: a settled payment is corrected by cancelling it and
    // recording the right one, so the ledger keeps both halves.
    cancel(id) {
        return api.delete(`/admin/supplier-payments/${id}`);
    },
};
