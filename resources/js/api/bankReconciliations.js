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

    // Correcting the statement a sheet is held against, while it is open.
    update(id, data) {
        return api.put(`/admin/accounting/bank-reconciliations/${id}`, data);
    },

    // Ticking a movement off the statement, or taking the tick back. This is
    // the whole act of reconciling.
    toggleLine(id, lineId) {
        return api.post(`/admin/accounting/bank-reconciliations/${id}/toggle-line`, { line_id: lineId });
    },

    // Sets several movements to cleared or not in one call. Explicit rather
    // than a toggle, so repeating it lands in the same place.
    setLines(id, lineIds, cleared) {
        return api.post(`/admin/accounting/bank-reconciliations/${id}/lines`, { line_ids: lineIds, cleared });
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
