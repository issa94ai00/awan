import api from './index';

export const accountingReportsApi = {
    trialBalance() {
        return api.get('/admin/accounting/trial-balance');
    },

    incomeStatement(params = {}) {
        return api.get('/admin/accounting/income-statement', { params });
    },

    balanceSheet() {
        return api.get('/admin/accounting/balance-sheet');
    }
};
