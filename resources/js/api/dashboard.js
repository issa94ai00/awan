import { invoicesApi } from './invoices';
import { productsApi } from './products';
import { posApi } from './pos';
import api from './index';

export const dashboardApi = {
    getOverviewStats() {
        return api.get('/dashboard/stats');
    },

    getInvoiceSummary() {
        return invoicesApi.summary();
    },

    getRecentInvoices(params = {}) {
        return invoicesApi.getAll(params);
    },

    getProductStats(params = {}) {
        return productsApi.getAll({ per_page: 1, sort_by: 'created_at', sort_order: 'desc', ...params });
    },

    getTopProducts(params = {}) {
        return productsApi.getAll({ per_page: 4, sort_by: 'created_at', sort_order: 'desc', ...params });
    },

    getCustomerStats(params = {}) {
        return posApi.customers({ per_page: 1, ...params });
    }
};
