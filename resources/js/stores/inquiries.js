import { defineStore } from 'pinia';
import inquiriesApi from '@/api/inquiries';

export const useInquiriesStore = defineStore('inquiries', {
    state: () => ({
        items: [],
        pagination: {},
        statusCounts: {},
        priorityCounts: {},
        loading: false,
        current: null,
    }),
    actions: {
        // Admin methods
        async fetch(params = {}) {
            this.loading = true;
            try {
                const res = await inquiriesApi.adminIndex(params);
                this.items = res.data.data.inquiries || [];
                this.pagination = res.data.data.pagination || {};
                this.statusCounts = res.data.data.status_counts || {};
                this.priorityCounts = res.data.data.priority_counts || {};
            } finally {
                this.loading = false;
            }
        },

        async show(id) {
            this.loading = true;
            try {
                const res = await inquiriesApi.adminShow(id);
                this.current = res.data.data;
                return this.current;
            } finally {
                this.loading = false;
            }
        },

        async reply(id, message) {
            const res = await inquiriesApi.adminReply(id, { message });
            return res.data;
        },

        async update(id, payload) {
            const res = await inquiriesApi.adminUpdate(id, payload);
            return res.data;
        },

        async remove(id) {
            const res = await inquiriesApi.adminDelete(id);
            return res.data;
        },

        async close(id) {
            const res = await inquiriesApi.adminClose(id);
            return res.data;
        },

        async reopen(id) {
            const res = await inquiriesApi.adminReopen(id);
            return res.data;
        },

        async assign(id, assignedTo) {
            const res = await inquiriesApi.adminAssign(id, { assigned_to: assignedTo });
            return res.data;
        },

        async bulkUpdate(payload) {
            const res = await inquiriesApi.adminBulkUpdate(payload);
            return res.data;
        },

        async bulkDelete(payload) {
            const res = await inquiriesApi.adminBulkDelete(payload);
            return res.data;
        },

        // User methods
        async userFetch(params = {}) {
            this.loading = true;
            try {
                const res = await inquiriesApi.userIndex(params);
                this.items = res.data.data.inquiries || [];
                this.pagination = res.data.data.pagination || {};
            } finally {
                this.loading = false;
            }
        },

        async userShow(id) {
            this.loading = true;
            try {
                const res = await inquiriesApi.userShow(id);
                this.current = res.data.data;
                return this.current;
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            const res = await inquiriesApi.store(payload);
            return res.data;
        }
    }
});
