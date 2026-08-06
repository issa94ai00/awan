import axios from 'axios';

const API_BASE_URL = '/api/admin/audit';

export const auditService = {
    // Audit Logs
    getAuditLogs(params = {}) {
        return axios.get(`${API_BASE_URL}`, { params });
    },

    getAuditLog(id) {
        return axios.get(`${API_BASE_URL}/${id}`);
    },

    // Entity Logs
    getEntityLogs(params = {}) {
        return axios.get(`${API_BASE_URL}/entity-logs`, { params });
    },

    getEntityHistory(entityType, entityId, params = {}) {
        return axios.get(`${API_BASE_URL}/entity-logs/${entityType}/${entityId}`, { params });
    },

    getEntityChanges(entityType, entityId, params = {}) {
        return axios.get(`${API_BASE_URL}/entity-logs/${entityType}/${entityId}/changes`, { params });
    },

    // User Activity
    getUserActivity(userId, params = {}) {
        return axios.get(`${API_BASE_URL}/user-activity/${userId}`, { params });
    },

    getUserTimeline(userId, params = {}) {
        return axios.get(`${API_BASE_URL}/user-activity/${userId}/timeline`, { params });
    },

    getUserStats(userId, params = {}) {
        return axios.get(`${API_BASE_URL}/user-activity/${userId}/stats`, { params });
    },

    // Module Logs
    getModuleLogs(module, params = {}) {
        return axios.get(`${API_BASE_URL}/module-logs/${module}`, { params });
    },

    getModuleActions(module, params = {}) {
        return axios.get(`${API_BASE_URL}/module-logs/${module}/actions`, { params });
    },

    // Statistics
    getStatistics(params = {}) {
        return axios.get(`${API_BASE_URL}/statistics`, { params });
    },

    getActivityByModule(params = {}) {
        return axios.get(`${API_BASE_URL}/statistics/by-module`, { params });
    },

    getActivityByAction(params = {}) {
        return axios.get(`${API_BASE_URL}/statistics/by-action`, { params });
    },

    getTopUsers(params = {}) {
        return axios.get(`${API_BASE_URL}/statistics/top-users`, { params });
    },

    getActivityTrends(params = {}) {
        return axios.get(`${API_BASE_URL}/statistics/trends`, { params });
    },

    // Export
    exportLogs(params = {}, format = 'csv') {
        return axios.get(`${API_BASE_URL}/export`, {
            params: { ...params, format },
            responseType: 'blob'
        });
    },

    // Search
    searchLogs(params = {}) {
        return axios.post(`${API_BASE_URL}/search`, params);
    },

    // Restore (if applicable)
    restoreFromLog(logId) {
        return axios.post(`${API_BASE_URL}/${logId}/restore`);
    }
};

export default auditService;
