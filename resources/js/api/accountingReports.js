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
    },

    /** Cross-module consistency between the books and the operational records. */
    systemHealth() {
        return api.get('/admin/accounting/system-health');
    },

    /**
     * One account's movements over a period, with the opening balance in front
     * of them. Computed on the server: a running total built in the browser
     * from a page of entries starts at zero and stops matching the account.
     */
    accountStatement(params = {}) {
        return api.get('/admin/accounting/account-statement', { params });
    },

    /** Who owes what, for how long, reconciled against the control accounts. */
    aging(params = {}) {
        return api.get('/admin/accounting/aging', { params });
    }
};
