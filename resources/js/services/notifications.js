import axios from 'axios';

const API_BASE_URL = '/api/admin/notifications';

export const notificationsService = {
    // Notifications
    getNotifications(params = {}) {
        return axios.get(`${API_BASE_URL}`, { params });
    },

    getNotification(id) {
        return axios.get(`${API_BASE_URL}/${id}`);
    },

    markAsRead(id) {
        return axios.post(`${API_BASE_URL}/${id}/mark-read`);
    },

    markAllAsRead() {
        return axios.post(`${API_BASE_URL}/mark-all-read`);
    },

    deleteNotification(id) {
        return axios.delete(`${API_BASE_URL}/${id}`);
    },

    sendNotification(data) {
        return axios.post(`${API_BASE_URL}/send`, data);
    },

    getUnreadCount() {
        return axios.get(`${API_BASE_URL}/unread-count`);
    },

    // Templates
    getTemplates(params = {}) {
        return axios.get(`${API_BASE_URL}/templates`, { params });
    },

    getTemplate(id) {
        return axios.get(`${API_BASE_URL}/templates/${id}`);
    },

    createTemplate(data) {
        return axios.post(`${API_BASE_URL}/templates`, data);
    },

    updateTemplate(id, data) {
        return axios.put(`${API_BASE_URL}/templates/${id}`, data);
    },

    deleteTemplate(id) {
        return axios.delete(`${API_BASE_URL}/templates/${id}`);
    },

    previewTemplate(id, data = {}) {
        return axios.post(`${API_BASE_URL}/templates/${id}/preview`, data);
    },

    duplicateTemplate(id) {
        return axios.post(`${API_BASE_URL}/templates/${id}/duplicate`);
    },

    // Preferences
    getPreferences() {
        return axios.get(`${API_BASE_URL}/preferences`);
    },

    updatePreferences(data) {
        return axios.put(`${API_BASE_URL}/preferences`, data);
    },

    getUserPreferences(userId) {
        return axios.get(`${API_BASE_URL}/preferences/${userId}`);
    },

    updateUserPreferences(userId, data) {
        return axios.put(`${API_BASE_URL}/preferences/${userId}`, data);
    },

    // Notification Channels
    getChannels() {
        return axios.get(`${API_BASE_URL}/channels`);
    },

    testChannel(channel, data) {
        return axios.post(`${API_BASE_URL}/channels/${channel}/test`, data);
    },

    // Notification History
    getHistory(params = {}) {
        return axios.get(`${API_BASE_URL}/history`, { params });
    },

    getNotificationHistory(id) {
        return axios.get(`${API_BASE_URL}/history/${id}`);
    },

    resendNotification(id) {
        return axios.post(`${API_BASE_URL}/history/${id}/resend`);
    }
};

export default notificationsService;
