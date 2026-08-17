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
    },

    /** Tax collected against tax paid, and what is left owing. */
    vatReturn(params = {}) {
        return api.get('/admin/accounting/vat-return', { params });
    },

    /**
     * Where the money went, as opposed to what was earned — a business can be
     * plainly profitable and still run out of cash.
     */
    cashFlow(params = {}) {
        return api.get('/admin/accounting/cash-flow', { params });
    },

    /**
     * The documents behind one party's balance. The aging report says who owes
     * what; this says why, which is what a disputed figure actually needs.
     */
    partyStatement(params = {}) {
        return api.get('/admin/accounting/party-statement', { params });
    },

    /**
     * Which branch made the money. The combined income statement says whether
     * the company was profitable; it never says which location was.
     */
    costCenterStatement(params = {}) {
        return api.get('/admin/accounting/cost-center-statement', { params });
    }
};

/** The register of things bought to keep, and their depreciation. */
export const fixedAssetsApi = {
    getAll(params = {}) {
        return api.get('/admin/accounting/fixed-assets', { params });
    },

    get(id) {
        return api.get(`/admin/accounting/fixed-assets/${id}`);
    },

    create(data) {
        return api.post('/admin/accounting/fixed-assets', data);
    },

    // Retiring an asset, rather than deleting it: its cost is on the balance
    // sheet and its depreciation in periods already reported on.
    dispose(id, data = {}) {
        return api.post(`/admin/accounting/fixed-assets/${id}/dispose`, data);
    }
};
