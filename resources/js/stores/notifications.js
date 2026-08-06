import { defineStore } from 'pinia';
import notificationsService from '@/services/notifications';

let pollTimer = null;

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        items: [],
        pagination: {},
        unreadCount: 0,
        loading: false,
    }),
    actions: {
        async fetchRecent(params = {}) {
            this.loading = true;
            try {
                const res = await notificationsService.getNotifications({ per_page: 8, ...params });
                const payload = res.data?.data ?? res.data;
                this.items = payload?.data ?? payload ?? [];
                this.pagination = {
                    current_page: payload?.current_page,
                    last_page: payload?.last_page,
                    total: payload?.total,
                };
            } finally {
                this.loading = false;
            }
        },

        async fetchUnreadCount() {
            if (!localStorage.getItem('token')) {
                this.unreadCount = 0;
                return;
            }
            try {
                const res = await notificationsService.getUnreadCount();
                this.unreadCount = res.data?.count ?? 0;
            } catch (e) {
                // Silent fail - badge just stays at its last known value.
            }
        },

        async markAsRead(id) {
            await notificationsService.markAsRead(id);
            const item = this.items.find((n) => n.id === id);
            if (item && !item.is_read) {
                item.is_read = true;
                item.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }
        },

        async markAllAsRead() {
            await notificationsService.markAllAsRead();
            this.items.forEach((n) => {
                n.is_read = true;
            });
            this.unreadCount = 0;
        },

        async removeNotification(id) {
            await notificationsService.deleteNotification(id);
            const item = this.items.find((n) => n.id === id);
            this.items = this.items.filter((n) => n.id !== id);
            if (item && !item.is_read) {
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }
        },

        startPolling(intervalMs = 45000) {
            this.stopPolling();
            this.fetchUnreadCount();
            pollTimer = setInterval(() => this.fetchUnreadCount(), intervalMs);
        },

        stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        },
    },
});

export default useNotificationsStore;
