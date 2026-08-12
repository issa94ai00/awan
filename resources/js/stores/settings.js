import { defineStore } from 'pinia';
import settingsApi from '@/api/settings';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        data: {},
        currencies: [],
        baseCurrency: null,
        loading: false,
    }),
    actions: {
        async fetch() {
            this.loading = true;
            try {
                const res = await settingsApi.get();
                this.data = res.data.data?.settings || {};
                this.currencies = res.data.data?.currencies || [];
                this.baseCurrency = res.data.data?.base_currency || this.data.default_currency || null;
            } catch (err) {
                console.warn('Failed to load settings from API, using default settings.', err);
                this.data = {};
                this.currencies = [];
                this.baseCurrency = null;
            } finally {
                this.loading = false;
            }
        },

        async save(payload) {
            this.loading = true;
            try {
                const res = await settingsApi.update(payload);
                this.data = res.data.data?.settings || this.data;
                this.currencies = res.data.data?.currencies || this.currencies;
                this.baseCurrency = res.data.data?.base_currency || this.data.default_currency || this.baseCurrency;
                return res;
            } finally {
                this.loading = false;
            }
        }
    }
});
