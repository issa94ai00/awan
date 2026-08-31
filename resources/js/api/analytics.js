import api from './index';

/**
 * The analytics module's endpoints.
 *
 * This file did not exist. Every analytics screen carried its API call written
 * out as a comment — `// await api.get('/api/v1/analytics/overview')` — with
 * hardcoded numbers underneath, so the whole module rendered figures nobody
 * had computed. The backend was there the entire time; nothing called it.
 *
 * Paths are relative because `@/api/index.js` sets `baseURL: '/api/v1'` and
 * attaches the bearer token and the 401 handler. `Reports.vue` reached for raw
 * `axios` instead and so sent no token at all.
 */
const analyticsApi = {
    /** The landing screen's whole card row, each figure against its own past. */
    overview(params = {}) {
        return api.get('/analytics/overview', { params });
    },

    /* ------------------------------- Sales ------------------------------- */

    salesSummary(params = {}) {
        return api.get('/analytics/sales/summary', { params });
    },

    salesTrend(params = {}) {
        return api.get('/analytics/sales/trend', { params: { days: 30, group_by: 'day', ...params } });
    },

    salesByChannel(params = {}) {
        return api.get('/analytics/sales/by-channel', { params });
    },

    topProducts(params = {}) {
        return api.get('/analytics/sales/top-products', { params: { limit: 10, ...params } });
    },

    customerAnalytics(params = {}) {
        return api.get('/analytics/sales/customer-analytics', { params });
    },

    salesForecast(params = {}) {
        return api.get('/analytics/sales/forecast', { params: { days: 30, forecast_days: 7, ...params } });
    },

    conversionFunnel(params = {}) {
        return api.get('/analytics/sales/conversion-funnel', { params });
    },

    /* ----------------------------- Inventory ----------------------------- */

    inventorySummary(params = {}) {
        return api.get('/analytics/inventory/summary', { params });
    },

    inventoryTurnover(params = {}) {
        return api.get('/analytics/inventory/turnover', { params });
    },

    slowMovingInventory(params = {}) {
        return api.get('/analytics/inventory/slow-moving', { params });
    },

    stockoutAnalysis(params = {}) {
        return api.get('/analytics/inventory/stockout', { params });
    },

    inventoryValuation(params = {}) {
        return api.get('/analytics/inventory/valuation', { params });
    },

    abcAnalysis(params = {}) {
        return api.get('/analytics/inventory/abc', { params });
    },

    inventoryHealthScore(params = {}) {
        return api.get('/analytics/inventory/health-score', { params });
    },

    /* ----------------------------- Warehouse ----------------------------- */

    warehousePerformance(params = {}) {
        return api.get('/analytics/warehouse/performance', { params });
    },

    binUtilization(params = {}) {
        return api.get('/analytics/warehouse/bin-utilization', { params });
    },

    cycleCountAccuracy(params = {}) {
        return api.get('/analytics/warehouse/cycle-count-accuracy', { params });
    },

    pickerPerformance(params = {}) {
        return api.get('/analytics/warehouse/picker-performance', { params });
    },

    capacityPlanning(params = {}) {
        return api.get('/analytics/warehouse/capacity-planning', { params });
    },

    /* ----------------------------- Financial ----------------------------- */

    financialSummary(params = {}) {
        return api.get('/analytics/financial/summary', { params });
    },

    revenueByCategory(params = {}) {
        return api.get('/analytics/financial/revenue-by-category', { params });
    },

    expenseBreakdown(params = {}) {
        return api.get('/analytics/financial/expenses', { params });
    },

    cashFlow(params = {}) {
        return api.get('/analytics/financial/cash-flow', { params });
    },

    profitAndLoss(params = {}) {
        return api.get('/analytics/financial/profit-loss', { params });
    },

    accountsAging(params = {}) {
        return api.get('/analytics/financial/aging', { params });
    },

    financialRatios(params = {}) {
        return api.get('/analytics/financial/ratios', { params });
    },

    budgetVsActual(params = {}) {
        return api.get('/analytics/financial/budget-vs-actual', { params });
    },

    /* ------------------------------ Metrics ------------------------------ */

    metrics(params = {}) {
        return api.get('/analytics/metrics', { params });
    },

    metric(id) {
        return api.get(`/analytics/metrics/${id}`);
    },

    createMetric(payload) {
        return api.post('/analytics/metrics', payload);
    },

    updateMetric(id, payload) {
        return api.put(`/analytics/metrics/${id}`, payload);
    },

    deleteMetric(id) {
        return api.delete(`/analytics/metrics/${id}`);
    },

    metricData(id, params = {}) {
        return api.get(`/analytics/metrics/${id}/data`, { params });
    },

    /* ------------------------------ Reports ------------------------------ */

    reports(params = {}) {
        return api.get('/analytics/reports', { params });
    },

    report(id) {
        return api.get(`/analytics/reports/${id}`);
    },

    createReport(payload) {
        return api.post('/analytics/reports', payload);
    },

    updateReport(id, payload) {
        return api.put(`/analytics/reports/${id}`, payload);
    },

    deleteReport(id) {
        return api.delete(`/analytics/reports/${id}`);
    },

    runReport(id, payload = {}) {
        return api.post(`/analytics/reports/${id}/run`, payload);
    },

    /* ---------------------------- Dashboards ----------------------------- */

    dashboards(params = {}) {
        return api.get('/analytics/dashboards', { params });
    },

    dashboard(id) {
        return api.get(`/analytics/dashboards/${id}`);
    },

    createDashboard(payload) {
        return api.post('/analytics/dashboards', payload);
    },

    updateDashboard(id, payload) {
        return api.put(`/analytics/dashboards/${id}`, payload);
    },

    deleteDashboard(id) {
        return api.delete(`/analytics/dashboards/${id}`);
    },

    addWidget(dashboardId, payload) {
        return api.post(`/analytics/dashboards/${dashboardId}/widgets`, payload);
    },

    updateWidget(id, payload) {
        return api.put(`/analytics/widgets/${id}`, payload);
    },

    deleteWidget(id) {
        return api.delete(`/analytics/widgets/${id}`);
    },

    /* ------------------------------ Visitors ------------------------------ */

    visitorsSummary(params = {}) {
        return api.get('/analytics/visitors/summary', { params });
    },

    visitorsTrend(params = {}) {
        return api.get('/analytics/visitors/trend', { params });
    },

    visitorsBreakdown(params = {}) {
        return api.get('/analytics/visitors/breakdown', { params });
    },

    visitorsTopPages(params = {}) {
        return api.get('/analytics/visitors/top-pages', { params: { limit: 10, ...params } });
    },

    visitorsLog(params = {}) {
        return api.get('/analytics/visitors/log', { params: { per_page: 20, page: 1, ...params } });
    },

    visitorsFilters() {
        return api.get('/analytics/visitors/filters');
    },

    /* ------------------------------ Export ------------------------------- */

    /**
     * Fetches a domain's rows as a CSV blob.
     *
     * `responseType: 'blob'` matters: without it axios parses the body as text
     * and the UTF-8 BOM that makes Excel read Arabic correctly is lost on the
     * way to the download.
     */
    exportDomain(domain, params = {}) {
        return api.get(`/analytics/export/${domain}`, { params, responseType: 'blob' });
    },
};

export default analyticsApi;
export { analyticsApi };
