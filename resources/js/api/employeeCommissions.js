import api from './index';

export const employeeCommissionsApi = {
    getAll(params = {}) {
        return api.get('/admin/employee-commissions', { params });
    },

    calculateSales(data) {
        return api.post('/admin/employee-commissions/calculate-sales', data);
    },

    create(data) {
        return api.post('/admin/employee-commissions', data);
    },

    update(id, data) {
        return api.put(`/admin/employee-commissions/${id}`, data);
    },

    delete(id) {
        return api.delete(`/admin/employee-commissions/${id}`);
    },

    getTrashed(params = {}) {
        return api.get('/admin/employee-commissions/trashed', { params });
    },

    restore(id) {
        return api.put(`/admin/employee-commissions/${id}/restore`);
    },

    getWithdrawals(commissionId) {
        return api.get(`/admin/employee-commissions/${commissionId}/withdrawals`);
    },

    createWithdrawal(commissionId, data) {
        return api.post(`/admin/employee-commissions/${commissionId}/withdrawals`, data);
    },

    updateWithdrawal(commissionId, withdrawalId, data) {
        return api.put(`/admin/employee-commissions/${commissionId}/withdrawals/${withdrawalId}`, data);
    },

    deleteWithdrawal(commissionId, withdrawalId) {
        return api.delete(`/admin/employee-commissions/${commissionId}/withdrawals/${withdrawalId}`);
    },

    getTrashedWithdrawals(commissionId) {
        return api.get(`/admin/employee-commissions/${commissionId}/withdrawals/trashed`);
    },

    restoreWithdrawal(commissionId, withdrawalId) {
        return api.put(`/admin/employee-commissions/${commissionId}/withdrawals/${withdrawalId}/restore`);
    }
};
