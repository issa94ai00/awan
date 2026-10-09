import api from '@/api';

const API_BASE_URL = '/audit';

export const auditService = {
    // Audit Logs Query & Show
    getAuditLogs(params = {}) {
        return api.get(API_BASE_URL, { params });
    },

    getAuditLog(id) {
        return api.get(`${API_BASE_URL}/${id}`);
    },

    // Entity Logs & Timelines
    getEntityLogs(params = {}) {
        return api.get(`${API_BASE_URL}/entity-logs`, { params });
    },

    getActivityTimeline(params = {}) {
        return api.get(`${API_BASE_URL}/activity-timeline`, { params });
    },

    // User Activity & Summary
    getUserLogs(userId, params = {}) {
        return api.get(`${API_BASE_URL}/user-logs/${userId}`, { params });
    },

    getUserSummary(userId, params = {}) {
        return api.get(`${API_BASE_URL}/user-summary/${userId}`, { params });
    },

    getMySummary(params = {}) {
        return api.get(`${API_BASE_URL}/my-summary`, { params });
    },

    // Module Logs
    getModuleLogs(module, params = {}) {
        return api.get(`${API_BASE_URL}/module-logs/${module}`, { params });
    },

    // Recent & Today
    getRecentLogs(params = {}) {
        return api.get(`${API_BASE_URL}/recent`, { params });
    },

    getTodayLogs() {
        return api.get(`${API_BASE_URL}/today`);
    },

    // Statistics & Analytics
    getStatistics(params = {}) {
        return api.get(`${API_BASE_URL}/statistics`, { params });
    },

    // Risk Scan & Anomaly Reconciliation
    getRiskScan() {
        return api.get(`${API_BASE_URL}/risk-scan`);
    },

    exportRiskScan() {
        return api.get(`${API_BASE_URL}/risk-scan/export`, {
            responseType: 'blob',
        });
    },

    getReconciliationSummary() {
        return api.get(`${API_BASE_URL}/reconciliation`);
    },

    // Exports
    exportLogs(params = {}) {
        return api.get(`${API_BASE_URL}/export`, {
            params,
            responseType: 'blob',
        });
    },

    // Log Retention / Cleanup
    cleanupOldLogs(days = 90) {
        return api.post(`${API_BASE_URL}/cleanup`, { days });
    },
};

export default auditService;
