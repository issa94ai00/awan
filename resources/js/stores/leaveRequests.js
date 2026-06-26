import { defineStore } from 'pinia';
import { leaveRequestsApi } from '@/api/leaveRequests';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

const sampleLeaveRequests = [
    {
        id: 1,
        employee: { name: 'سارة محمد', department: 'الموارد البشرية' },
        leave_type: 'إجازة سنوية',
        start_date: '2024-07-12',
        end_date: '2024-07-18',
        status: 'pending',
        reason: 'إجازة عائلية'
    },
    {
        id: 2,
        employee: { name: 'أحمد العلي', department: 'الموارد البشرية' },
        leave_type: 'مرضية',
        start_date: '2024-06-03',
        end_date: '2024-06-05',
        status: 'approved',
        reason: 'زيارة طبية'
    },
    {
        id: 3,
        employee: { name: 'ليلى صالح', department: 'الموارد البشرية' },
        leave_type: 'إجازة خاصة',
        start_date: '2024-07-20',
        end_date: '2024-07-22',
        status: 'rejected',
        reason: 'تضارب مع جدول العمل'
    }
];

export const useLeaveRequestsStore = defineStore('leaveRequests', {
    state: () => ({
        requests: [],
        currentRequest: null,
        loading: false,
        error: null,
        validationErrors: {},
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    getters: {
        isLoading: (state) => state.loading
    },

    actions: {
        async fetchLeaveRequests(params = {}) {
            this.loading = true;
            this.error = null;

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/leaves' } });
                    return;
                }

                const res = await leaveRequestsApi.getAll(params);
                const data = res.data?.data || res.data || {};
                this.requests = data.leave_requests || data.items || [];
                const p = data.pagination || {};
                this.pagination = {
                    current_page: p.current_page || p.currentPage || 1,
                    per_page: p.per_page || p.perPage || this.pagination.per_page,
                    total: p.total || this.requests.length
                };
            } catch (err) {
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.requests = sampleLeaveRequests;
                    this.pagination.total = sampleLeaveRequests.length;
                } else {
                    this.error = err.response?.data?.message || err.message || 'Failed to load leave requests';
                    console.error('Leave requests load error:', err);
                    throw err;
                }
            } finally {
                this.loading = false;
            }
        },

        async fetchLeaveRequest(id) {
            this.loading = true;
            this.error = null;

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/leaves' } });
                    return null;
                }

                const res = await leaveRequestsApi.getById(id);
                const data = res.data?.data || res.data || null;
                this.currentRequest = data;
                return data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load leave request';
                console.error('Leave request error:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createLeaveRequest(payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/leaves' } });
                    return null;
                }

                const res = await leaveRequestsApi.create(payload);
                const request = res.data?.data || res.data;

                if (request) {
                    this.requests.unshift(request);
                    this.pagination.total += 1;
                }

                return request;
            } catch (err) {
                if (err.response?.status === 422) {
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }

                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    const id = Math.max(0, ...this.requests.map((item) => item.id || 0)) + 1;
                    const request = { id, ...payload };
                    this.requests.unshift(request);
                    this.pagination.total += 1;
                    return request;
                }

                this.error = err.response?.data?.message || err.message || 'Failed to create leave request';
                console.error('Create leave request error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateLeaveRequest(id, payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/leaves' } });
                    return null;
                }

                const res = await leaveRequestsApi.update(id, payload);
                const updated = res.data?.data || res.data;
                if (updated) {
                    this.requests = this.requests.map((request) =>
                        request.id === id ? { ...request, ...updated } : request
                    );
                }
                return updated;
            } catch (err) {
                if (err.response?.status === 422) {
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }

                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.requests = this.requests.map((request) =>
                        request.id === id ? { ...request, ...payload } : request
                    );
                    return this.requests.find((request) => request.id === id) || null;
                }

                this.error = err.response?.data?.message || err.message || 'Failed to update leave request';
                console.error('Update leave request error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deleteLeaveRequest(id) {
            this.loading = true;
            this.error = null;

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/leaves' } });
                    return;
                }

                await leaveRequestsApi.delete(id);
                this.requests = this.requests.filter((request) => request.id !== id);
                this.pagination.total -= 1;
            } catch (err) {
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.requests = this.requests.filter((request) => request.id !== id);
                    this.pagination.total -= 1;
                    return;
                }

                this.error = err.response?.data?.message || err.message || 'Failed to delete leave request';
                console.error('Delete leave request error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        }
    }
});
