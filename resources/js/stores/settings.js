import { defineStore } from 'pinia';
import settingsApi from '@/api/settings';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        data: {},
        loading: false,
    }),
    actions: {
        async fetch() {
            this.loading = true;
            try {
                const res = await settingsApi.get();
                this.data = res.data.data?.settings || {};
            } catch (err) {
                console.warn('Failed to load settings from API, using default settings.', err);
                this.data = {};
            } finally {
                this.loading = false;
            }
        },

        async save(payload) {
            this.loading = true;
            try {
                const res = await settingsApi.update(payload);
                this.data = res.data.data?.settings || this.data;
                return res;
            } finally {
                this.loading = false;
            }
        }
    }
});
