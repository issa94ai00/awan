import { defineStore } from 'pinia';
import notificationsService from '@/services/notifications';

let pollTimer = null;

// Gentle Web Audio API synthesizer for clean, zero-asset notification chimes
function playNotificationChime() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const ctx = new AudioContextClass();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }
        const now = ctx.currentTime;
        
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gain = ctx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12); // A5

        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(880, now + 0.12);
        osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.28); // D6

        gain.gain.setValueAtTime(0.06, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(ctx.destination);

        osc1.start(now);
        osc2.start(now + 0.12);
        osc1.stop(now + 0.12);
        osc2.stop(now + 0.45);
    } catch (e) {
        // AudioContext blocked by browser autoplay policy until user gesture
    }
}

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        items: [],
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 0,
            per_page: 10,
        },
        summary: {
            total: 0,
            unread: 0,
            read: 0,
        },
        unreadCount: 0,
        systemAlerts: [],
        systemAlertsCount: 0,
        loading: false,
        alertsLoading: false,
        soundEnabled: localStorage.getItem('notification_sound_enabled') !== 'false',
        previousUnreadCount: 0,
        lastAlertsFetch: null,
    }),

    getters: {
        totalBadgeCount(state) {
            return state.unreadCount + (state.systemAlertsCount > 0 ? 1 : 0);
        },
        hasCriticalAlerts(state) {
            return state.systemAlerts.some((a) => a.severity === 'critical');
        },
    },

    actions: {
        async fetchRecent(params = {}) {
            this.loading = true;
            try {
                const res = await notificationsService.getNotifications({ per_page: 10, ...params });
                const resData = res.data || {};
                const rows = Array.isArray(resData.data) ? resData.data : (Array.isArray(resData) ? resData : []);
                this.items = rows;
                this.pagination = {
                    current_page: resData.current_page || 1,
                    last_page: resData.last_page || 1,
                    total: resData.total ?? rows.length,
                    per_page: resData.per_page || 10,
                };
                if (resData.summary) {
                    this.summary = resData.summary;
                    this.unreadCount = resData.summary.unread ?? 0;
                }
            } finally {
                this.loading = false;
            }
        },

        async fetchUnreadCount(triggerSound = true) {
            if (!localStorage.getItem('token')) {
                this.unreadCount = 0;
                return;
            }
            try {
                const res = await notificationsService.getUnreadCount();
                const newCount = res.data?.count ?? 0;

                if (triggerSound && this.soundEnabled && newCount > this.unreadCount && this.previousUnreadCount > 0) {
                    playNotificationChime();
                }

                this.previousUnreadCount = this.unreadCount;
                this.unreadCount = newCount;
            } catch (e) {
                // Silent fail - badge stays at last known value
            }
        },

        async fetchSystemAlerts() {
            if (!localStorage.getItem('token')) {
                this.systemAlerts = [];
                this.systemAlertsCount = 0;
                return;
            }
            this.alertsLoading = true;
            try {
                const res = await notificationsService.getSystemAlerts();
                const data = res.data || {};
                this.systemAlerts = data.alerts || [];
                this.systemAlertsCount = data.total_alerts || this.systemAlerts.length;
                this.lastAlertsFetch = new Date();
            } catch (e) {
                // Silent fail
            } finally {
                this.alertsLoading = false;
            }
        },

        async fetchStats() {
            if (!localStorage.getItem('token')) return;
            try {
                const res = await notificationsService.getStats();
                const data = res.data || {};
                this.summary = {
                    total: data.total ?? 0,
                    unread: data.unread ?? 0,
                    read: data.read ?? 0,
                };
                this.unreadCount = data.unread ?? 0;
                if (typeof data.system_alerts_count === 'number') {
                    this.systemAlertsCount = data.system_alerts_count;
                }
            } catch (e) {
                // Silent fail
            }
        },

        async markAsRead(id) {
            await notificationsService.markAsRead(id);
            const item = this.items.find((n) => n.id === id);
            if (item && !item.is_read) {
                item.is_read = true;
                item.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.summary.unread = Math.max(0, this.summary.unread - 1);
                this.summary.read = (this.summary.read || 0) + 1;
            }
        },

        async markAllAsRead() {
            await notificationsService.markAllAsRead();
            this.items.forEach((n) => {
                n.is_read = true;
                n.read_at = n.read_at || new Date().toISOString();
            });
            this.unreadCount = 0;
            this.summary.unread = 0;
            this.summary.read = this.summary.total;
        },

        async markMultipleAsRead(ids) {
            if (!ids || ids.length === 0) return;
            await notificationsService.markMultipleAsRead(ids);
            let markedCount = 0;
            this.items.forEach((n) => {
                if (ids.includes(n.id) && !n.is_read) {
                    n.is_read = true;
                    n.read_at = new Date().toISOString();
                    markedCount++;
                }
            });
            this.unreadCount = Math.max(0, this.unreadCount - markedCount);
            this.summary.unread = Math.max(0, this.summary.unread - markedCount);
            this.summary.read = (this.summary.read || 0) + markedCount;
        },

        async removeNotification(id) {
            await notificationsService.deleteNotification(id);
            const item = this.items.find((n) => n.id === id);
            this.items = this.items.filter((n) => n.id !== id);
            if (item && !item.is_read) {
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.summary.unread = Math.max(0, this.summary.unread - 1);
            }
            this.summary.total = Math.max(0, (this.summary.total || 1) - 1);
        },

        async deleteMultiple(ids) {
            if (!ids || ids.length === 0) return;
            await notificationsService.deleteMultiple(ids);
            let unreadDeleted = 0;
            this.items.forEach((n) => {
                if (ids.includes(n.id) && !n.is_read) {
                    unreadDeleted++;
                }
            });
            this.items = this.items.filter((n) => !ids.includes(n.id));
            this.unreadCount = Math.max(0, this.unreadCount - unreadDeleted);
            this.summary.unread = Math.max(0, this.summary.unread - unreadDeleted);
            this.summary.total = Math.max(0, (this.summary.total || 0) - ids.length);
        },

        async deleteAllRead() {
            await notificationsService.deleteAllRead();
            const readCount = this.items.filter((n) => n.is_read).length;
            this.items = this.items.filter((n) => !n.is_read);
            this.summary.read = 0;
            this.summary.total = Math.max(0, (this.summary.total || 0) - readCount);
        },

        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            localStorage.setItem('notification_sound_enabled', String(this.soundEnabled));
            if (this.soundEnabled) {
                playNotificationChime();
            }
            return this.soundEnabled;
        },

        playTestSound() {
            playNotificationChime();
        },

        startPolling(intervalMs = 35000) {
            this.stopPolling();
            this.fetchUnreadCount(false);
            this.fetchSystemAlerts();
            pollTimer = setInterval(() => {
                this.fetchUnreadCount(true);
                this.fetchSystemAlerts();
            }, intervalMs);
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
