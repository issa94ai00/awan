import { defineStore } from 'pinia';
import { ticketsApi } from '@/api/tickets';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

const sampleTickets = [
    {
        id: 1,
        customer: { name: 'شركة التقنية الحديثة' },
        subject: 'مشكلة في فاتورة المبيعات',
        message: 'يرجى مراجعة الفاتورة رقم 1023، قيمة الضريبة غير صحيحة.',
        status: 'open',
        priority: 'high',
        updated_at: '2026-06-01 14:20:00'
    },
    {
        id: 2,
        customer: { name: 'مؤسسة البرمجة العربية' },
        subject: 'طلب إضافة منتج جديد',
        message: 'هل يمكن إضافة حقل مخصص في صفحة الفواتير؟',
        status: 'pending',
        priority: 'medium',
        updated_at: '2026-06-02 09:30:00'
    }
];

export const useTicketsStore = defineStore('tickets', {
    state: () => ({
        tickets: [],
        currentTicket: null,
        loading: false,
        error: null,
        validationErrors: {},
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    getters: {
        isLoading: (state) => state.loading
    },

    actions: {
        async fetchTickets(params = {}) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/crm/tickets' } });
                    return;
                }

                const res = await ticketsApi.getAll(params);
                const data = res.data?.data || res.data || {};
                this.tickets = data.tickets || data.items || [];
                const p = data.pagination || {};
                this.pagination = {
                    current_page: p.current_page || p.currentPage || 1,
                    per_page: p.per_page || p.perPage || this.pagination.per_page,
                    total: p.total || this.tickets.length
                };
            } catch (err) {
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.tickets = sampleTickets;
                    this.pagination.total = sampleTickets.length;
                } else {
                    this.error = err.response?.data?.message || err.message || 'Failed to load tickets';
                    console.error('Tickets load error:', err);
                    throw err;
                }
            } finally {
                this.loading = false;
            }
        },

        async fetchTicket(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/crm/tickets' } });
                    return null;
                }

                const res = await ticketsApi.getById(id);
                const data = res.data?.data || res.data || null;
                this.currentTicket = data;
                return data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load ticket';
                console.error('Ticket record error:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createTicket(payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};

            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/crm/tickets' } });
                    return null;
                }

                const res = await ticketsApi.create(payload);
                const ticket = res.data?.data || res.data;
                if (ticket) {
                    this.tickets.unshift(ticket);
                    this.pagination.total += 1;
                }
                return ticket;
            } catch (err) {
                if (err.response?.status === 422) {
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    const id = Math.max(0, ...this.tickets.map((item) => item.id || 0)) + 1;
                    const ticket = { id, ...payload, customer: { name: 'غير معروف' } };
                    this.tickets.unshift(ticket);
                    this.pagination.total += 1;
                    return ticket;
                }
                this.error = err.response?.data?.message || err.message || 'Failed to create ticket';
                console.error('Create ticket error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateTicket(id, payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};

            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/crm/tickets' } });
                    return null;
                }

                const res = await ticketsApi.update(id, payload);
                const updated = res.data?.data || res.data;
                if (updated) {
                    this.tickets = this.tickets.map((ticket) =>
                        ticket.id === id ? { ...ticket, ...updated } : ticket
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
                    this.tickets = this.tickets.map((ticket) =>
                        ticket.id === id ? { ...ticket, ...payload } : ticket
                    );
                    return this.tickets.find((ticket) => ticket.id === id) || null;
                }
                this.error = err.response?.data?.message || err.message || 'Failed to update ticket';
                console.error('Update ticket error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deleteTicket(id) {
            this.loading = true;
            this.error = null;
            try {
                const auth = useAuthStore();
                const token = localStorage.getItem('token') || (auth.user ? '1' : null);
                if (!token) {
                    router.push({ path: '/login', query: { redirect: '/admin/crm/tickets' } });
                    return;
                }

                await ticketsApi.delete(id);
                this.tickets = this.tickets.filter((ticket) => ticket.id !== id);
                this.pagination.total -= 1;
            } catch (err) {
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.tickets = this.tickets.filter((ticket) => ticket.id !== id);
                    this.pagination.total -= 1;
                    return;
                }
                this.error = err.response?.data?.message || err.message || 'Failed to delete ticket';
                console.error('Delete ticket error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        }
    }
});
