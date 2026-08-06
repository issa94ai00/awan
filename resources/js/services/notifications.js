import api from '@/api/index';

const BASE = '/notifications';

export const notificationsService = {
    // Notifications
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

    deleteNotification(id) {
        return api.delete(`${BASE}/${id}`);
    },

    sendNotification(data) {
        return api.post(`${BASE}/send`, data);
    },

    getUnreadCount() {
        return api.get(`${BASE}/unread-count`);
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

    previewTemplate(id, data = {}) {
        return api.post(`${BASE}/templates/${id}/preview`, data);
    },

    duplicateTemplate(id) {
        return api.post(`${BASE}/templates/${id}/duplicate`);
    },

    // Preferences
    getPreferences() {
        return api.get(`${BASE}/preferences`);
    },

    updatePreferences(data) {
        return api.put(`${BASE}/preferences`, data);
    },

    getUserPreferences(userId) {
        return api.get(`${BASE}/preferences/${userId}`);
    },

    updateUserPreferences(userId, data) {
        return api.put(`${BASE}/preferences/${userId}`, data);
    },

    // Notification Channels
    getChannels() {
        return api.get(`${BASE}/channels`);
    },

    testChannel(channel, data) {
        return api.post(`${BASE}/channels/${channel}/test`, data);
    },

    // Notification History
    getHistory(params = {}) {
        return api.get(`${BASE}/history`, { params });
    },

    getNotificationHistory(id) {
        return api.get(`${BASE}/history/${id}`);
    },

    resendNotification(id) {
        return api.post(`${BASE}/history/${id}/resend`);
    }
};

export default notificationsService;
