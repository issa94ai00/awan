import api from './index';

export const bankReconciliationsApi = {
    getAll(params = {}) {
        return api.get('/admin/accounting/bank-reconciliations', { params });
    },

    get(id) {
        return api.get(`/admin/accounting/bank-reconciliations/${id}`);
    },

    create(data) {
        return api.post('/admin/accounting/bank-reconciliations', data);
    },

    // Ticking a movement off the statement, or taking the tick back. This is
    // the whole act of reconciling.
    toggleLine(id, lineId) {
        return api.post(`/admin/accounting/bank-reconciliations/${id}/toggle-line`, { line_id: lineId });
    },

    // Refused unless the arithmetic closes: a completed reconciliation claims
    // every difference is timing, and that claim has to be true.
    complete(id) {
        return api.post(`/admin/accounting/bank-reconciliations/${id}/complete`);
    },

    reopen(id) {
        return api.post(`/admin/accounting/bank-reconciliations/${id}/reopen`);
    },

    remove(id) {
        return api.delete(`/admin/accounting/bank-reconciliations/${id}`);
    },
};
