import axios from 'axios';

const API_BASE_URL = '/api/admin/analytics';

export const analyticsService = {
    // Dashboard
    getDashboardStats() {
        return axios.get(`${API_BASE_URL}/dashboard`);
    },

    // Sales Analytics
    getSalesAnalytics(params = {}) {
        return axios.get(`${API_BASE_URL}/sales`, { params });
    },

    getSalesTrends(params = {}) {
        return axios.get(`${API_BASE_URL}/sales/trends`, { params });
    },

    getSalesByCategory(params = {}) {
        return axios.get(`${API_BASE_URL}/sales/by-category`, { params });
    },

    getSalesByRegion(params = {}) {
        return axios.get(`${API_BASE_URL}/sales/by-region`, { params });
    },

    getTopProducts(params = {}) {
        return axios.get(`${API_BASE_URL}/sales/top-products`, { params });
    },

    getSalesForecast(params = {}) {
        return axios.get(`${API_BASE_URL}/sales/forecast`, { params });
    },

    // Inventory Analytics
    getInventoryAnalytics(params = {}) {
        return axios.get(`${API_BASE_URL}/inventory`, { params });
    },

    getInventoryTurnover(params = {}) {
        return axios.get(`${API_BASE_URL}/inventory/turnover`, { params });
    },

    getLowStockAlerts(params = {}) {
        return axios.get(`${API_BASE_URL}/inventory/low-stock`, { params });
    },

    getOverstockItems(params = {}) {
        return axios.get(`${API_BASE_URL}/inventory/overstock`, { params });
    },

    getABCAnalysis(params = {}) {
        return axios.get(`${API_BASE_URL}/inventory/abc-analysis`, { params });
    },

    // Warehouse Analytics
    getWarehouseAnalytics(params = {}) {
        return axios.get(`${API_BASE_URL}/warehouse`, { params });
    },

    getWarehouseUtilization(params = {}) {
        return axios.get(`${API_BASE_URL}/warehouse/utilization`, { params });
    },

    getPickingEfficiency(params = {}) {
        return axios.get(`${API_BASE_URL}/warehouse/picking-efficiency`, { params });
    },

    getPackingEfficiency(params = {}) {
        return axios.get(`${API_BASE_URL}/warehouse/packing-efficiency`, { params });
    },

    getZoneUtilization(params = {}) {
        return axios.get(`${API_BASE_URL}/warehouse/zone-utilization`, { params });
    },

    // Financial Analytics
    getFinancialAnalytics(params = {}) {
        return axios.get(`${API_BASE_URL}/financial`, { params });
    },

    getProfitLoss(params = {}) {
        return axios.get(`${API_BASE_URL}/financial/profit-loss`, { params });
    },

    getCashFlow(params = {}) {
        return axios.get(`${API_BASE_URL}/financial/cash-flow`, { params });
    },

    getFinancialRatios(params = {}) {
        return axios.get(`${API_BASE_URL}/financial/ratios`, { params });
    },

    getRevenueByPeriod(params = {}) {
        return axios.get(`${API_BASE_URL}/financial/revenue`, { params });
    },

    getExpensesByCategory(params = {}) {
        return axios.get(`${API_BASE_URL}/financial/expenses`, { params });
    },

    // Custom Metrics
    getMetrics(params = {}) {
        return axios.get(`${API_BASE_URL}/metrics`, { params });
    },

    getMetric(id) {
        return axios.get(`${API_BASE_URL}/metrics/${id}`);
    },

    createMetric(data) {
        return axios.post(`${API_BASE_URL}/metrics`, data);
    },

    updateMetric(id, data) {
        return axios.put(`${API_BASE_URL}/metrics/${id}`, data);
    },

    deleteMetric(id) {
        return axios.delete(`${API_BASE_URL}/metrics/${id}`);
    },

    calculateMetric(id, params = {}) {
        return axios.post(`${API_BASE_URL}/metrics/${id}/calculate`, params);
    },

    // Reports
    getReports(params = {}) {
        return axios.get(`${API_BASE_URL}/reports`, { params });
    },

    getReport(id) {
        return axios.get(`${API_BASE_URL}/reports/${id}`);
    },

    createReport(data) {
        return axios.post(`${API_BASE_URL}/reports`, data);
    },

    updateReport(id, data) {
        return axios.put(`${API_BASE_URL}/reports/${id}`, data);
    },

    deleteReport(id) {
        return axios.delete(`${API_BASE_URL}/reports/${id}`);
    },

    executeReport(id, params = {}) {
        return axios.post(`${API_BASE_URL}/reports/${id}/execute`, params);
    },

    downloadReport(id, format = 'pdf') {
        return axios.get(`${API_BASE_URL}/reports/${id}/download`, {
            params: { format },
            responseType: 'blob'
        });
    },

    // Dashboards
    getDashboards(params = {}) {
        return axios.get(`${API_BASE_URL}/dashboards`, { params });
    },

    getDashboard(id) {
        return axios.get(`${API_BASE_URL}/dashboards/${id}`);
    },

    createDashboard(data) {
        return axios.post(`${API_BASE_URL}/dashboards`, data);
    },

    updateDashboard(id, data) {
        return axios.put(`${API_BASE_URL}/dashboards/${id}`, data);
    },

    deleteDashboard(id) {
        return axios.delete(`${API_BASE_URL}/dashboards/${id}`);
    },

    getDashboardData(id, params = {}) {
        return axios.get(`${API_BASE_URL}/dashboards/${id}/data`, { params });
    }
};

export default analyticsService;
