import api from '@/api/index';

const BASE = '/notifications';

export const notificationsService = {
    // Notifications Feed & Operations
    getNotifications(params = {}) {
        return api.get(`${BASE}`, { params });
    },

    getNotification(id) {
        return api.get(`${BASE}/${id}`);
    },

    markAsRead(id) {
        return api.post(`${BASE}/${id}/read`);
    },

    markAllAsRead() {
        return api.post(`${BASE}/read-all`);
    },

    markMultipleAsRead(ids) {
        return api.post(`${BASE}/mark-multiple-read`, { ids });
    },

    deleteNotification(id) {
        return api.delete(`${BASE}/${id}`);
    },

    deleteMultiple(ids) {
        return api.post(`${BASE}/delete-multiple`, { ids });
    },

    deleteAllRead() {
        return api.delete(`${BASE}/read-all`);
    },

    sendNotification(data) {
        return api.post(`${BASE}/send`, data);
    },

    sendBulkNotification(data) {
        return api.post(`${BASE}/send-bulk`, data);
    },

    getUnreadCount() {
        return api.get(`${BASE}/unread-count`);
    },

    getStats() {
        return api.get(`${BASE}/stats`);
    },

    getSystemAlerts() {
        return api.get(`${BASE}/system-alerts`);
    },

    getUsers() {
        return api.get(`${BASE}/users`);
    },

    // Templates
    getTemplates(params = {}) {
        return api.get(`${BASE}/templates`, { params });
    },

    getTemplate(id) {
        return api.get(`${BASE}/templates/${id}`);
    },

    createTemplate(data) {
        return api.post(`${BASE}/templates`, data);
    },

    updateTemplate(id, data) {
        return api.put(`${BASE}/templates/${id}`, data);
    },

    deleteTemplate(id) {
        return api.delete(`${BASE}/templates/${id}`);
    },

    // Preferences
    getPreferences() {
        return api.get(`${BASE}/preferences`);
    },

    updatePreferences(data) {
        return api.put(`${BASE}/preferences`, data);
    },
};

export default notificationsService;
