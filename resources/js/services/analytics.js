import api from '@/api';

/**
 * Analytics API client.
 *
 * Every method here maps to a route that actually exists. Seven others
 * advertised endpoints the backend never implemented — sales by category and by
 * region, low-stock, overstock, revenue-by-period, packing efficiency and zone
 * utilisation. They answered 404 and no screen called them, so they were removed
 * rather than left as traps. Add them back alongside real routes if that data
 * gets built.
 */

/**
 * Analytics API client.
 *
 * Every method here maps to a route that exists. Seven others once advertised
 * endpoints the backend never implemented (sales by category/region, low-stock,
 * overstock, revenue-by-period, packing efficiency, zone utilisation); they
 * answered 404 and no screen called them, so they were removed rather than left
 * as traps. Add them back alongside real routes if that data is built.
 */
const API_BASE_URL = '/analytics';

export const analyticsService = {
    // Dashboard
    getDashboardStats() {
        return api.get(`${API_BASE_URL}/dashboards`);
    },

    // Sales Analytics
    getSalesAnalytics(params = {}) {
        return api.get(`${API_BASE_URL}/sales/summary`, { params });
    },

    getSalesTrends(params = {}) {
        return api.get(`${API_BASE_URL}/sales/trend`, { params });
    },

    getTopProducts(params = {}) {
        return api.get(`${API_BASE_URL}/sales/top-products`, { params });
    },

    getSalesForecast(params = {}) {
        return api.get(`${API_BASE_URL}/sales/forecast`, { params });
    },

    // Inventory Analytics
    getInventoryAnalytics(params = {}) {
        return api.get(`${API_BASE_URL}/inventory/summary`, { params });
    },

    getInventoryTurnover(params = {}) {
        return api.get(`${API_BASE_URL}/inventory/turnover`, { params });
    },

    getABCAnalysis(params = {}) {
        return api.get(`${API_BASE_URL}/inventory/abc`, { params });
    },

    // Warehouse Analytics
    getWarehouseAnalytics(params = {}) {
        return api.get(`${API_BASE_URL}/warehouse/performance`, { params });
    },

    getWarehouseUtilization(params = {}) {
        return api.get(`${API_BASE_URL}/warehouse/bin-utilization`, { params });
    },

    getPickingEfficiency(params = {}) {
        return api.get(`${API_BASE_URL}/warehouse/picker-performance`, { params });
    },

    // Financial Analytics
    getFinancialAnalytics(params = {}) {
        return api.get(`${API_BASE_URL}/financial/summary`, { params });
    },

    getProfitLoss(params = {}) {
        return api.get(`${API_BASE_URL}/financial/profit-loss`, { params });
    },

    getCashFlow(params = {}) {
        return api.get(`${API_BASE_URL}/financial/cash-flow`, { params });
    },

    getFinancialRatios(params = {}) {
        return api.get(`${API_BASE_URL}/financial/ratios`, { params });
    },

    getExpensesByCategory(params = {}) {
        return api.get(`${API_BASE_URL}/financial/expenses`, { params });
    },

    // Custom Metrics
    getMetrics(params = {}) {
        return api.get(`${API_BASE_URL}/metrics`, { params });
    },

    getMetric(id) {
        return api.get(`${API_BASE_URL}/metrics/${id}`);
    },

    createMetric(data) {
        return api.post(`${API_BASE_URL}/metrics`, data);
    },

    updateMetric(id, data) {
        return api.put(`${API_BASE_URL}/metrics/${id}`, data);
    },

    deleteMetric(id) {
        return api.delete(`${API_BASE_URL}/metrics/${id}`);
    },

    calculateMetric(id, params = {}) {
        return api.post(`${API_BASE_URL}/metrics/${id}/calculate`, params);
    },

    // Reports
    getReports(params = {}) {
        return api.get(`${API_BASE_URL}/reports`, { params });
    },

    getReport(id) {
        return api.get(`${API_BASE_URL}/reports/${id}`);
    },

    createReport(data) {
        return api.post(`${API_BASE_URL}/reports`, data);
    },

    updateReport(id, data) {
        return api.put(`${API_BASE_URL}/reports/${id}`, data);
    },

    deleteReport(id) {
        return api.delete(`${API_BASE_URL}/reports/${id}`);
    },

    executeReport(id, params = {}) {
        return api.post(`${API_BASE_URL}/reports/${id}/execute`, params);
    },

    downloadReport(id, format = 'pdf') {
        return api.get(`${API_BASE_URL}/reports/${id}/download`, {
            params: { format },
            responseType: 'blob'
        });
    },

    // Dashboards
    getDashboards(params = {}) {
        return api.get(`${API_BASE_URL}/dashboards`, { params });
    },

    getDashboard(id) {
        return api.get(`${API_BASE_URL}/dashboards/${id}`);
    },

    createDashboard(data) {
        return api.post(`${API_BASE_URL}/dashboards`, data);
    },

    updateDashboard(id, data) {
        return api.put(`${API_BASE_URL}/dashboards/${id}`, data);
    },

    deleteDashboard(id) {
        return api.delete(`${API_BASE_URL}/dashboards/${id}`);
    },

    getDashboardData(id, params = {}) {
        return api.get(`${API_BASE_URL}/dashboards/${id}/data`, { params });
    }
};

export default analyticsService;
