import { defineStore } from 'pinia';
import { employeesApi } from '@/api/employees';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';

const sampleEmployees = [
    {
        id: 1,
        name: 'أحمد العلي',
        department: 'الموارد البشرية',
        position: 'مدير الموارد البشرية',
        status: 'نشط',
        email: 'ahmed.ali@example.com',
        phone: '+966 50 000 0001',
        hire_date: '2022-04-15',
        avatar: '/placeholder.jpg'
    },
    {
        id: 2,
        name: 'سارة محمد',
        department: 'الموارد البشرية',
        position: 'أخصائية توظيف',
        status: 'نشط',
        email: 'sara.mohamed@example.com',
        phone: '+966 50 000 0002',
        hire_date: '2023-01-09',
        avatar: '/placeholder.jpg'
    },
    {
        id: 3,
        name: 'خالد حسن',
        department: 'الإدارة',
        position: 'محلل رواتب',
        status: 'غير نشط',
        email: 'khaled.hassan@example.com',
        phone: '+966 50 000 0003',
        hire_date: '2021-09-12',
        avatar: '/placeholder.jpg'
    }
];

export const useEmployeesStore = defineStore('employees', {
    state: () => ({
        employees: [],
        currentEmployee: null,
        loading: false,
        error: null,
        validationErrors: {},
        pagination: { current_page: 1, per_page: 20, total: 0 }
    }),

    getters: {
        isLoading: (state) => state.loading
    },

    actions: {
        async fetchEmployees(params = {}) {
            this.loading = true;
            this.error = null;

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/employees' } });
                    return;
                }

                const res = await employeesApi.getAll(params);
                const data = res.data?.data || res.data || {};
                const list = data.employees || data.items || data || [];

                this.employees = Array.isArray(list) ? list : [];
                const p = data.pagination || data.meta?.pagination || {};
                this.pagination = {
                    current_page: p.current_page || p.currentPage || 1,
                    per_page: p.per_page || p.perPage || this.pagination.per_page,
                    total: p.total || this.employees.length
                };
            } catch (err) {
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.employees = sampleEmployees;
                    this.pagination.total = sampleEmployees.length;
                } else {
                    this.error = err.response?.data?.message || err.message || 'Failed to load employees';
                    console.error('Employees load error:', err);
                    throw err;
                }
            } finally {
                this.loading = false;
            }
        },

        async fetchEmployee(id) {
            this.loading = true;
            this.error = null;

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: `/admin/hr/employees` } });
                    return null;
                }

                const res = await employeesApi.getById(id);
                const data = res.data?.data || res.data || null;
                this.currentEmployee = data;
                return data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message || 'Failed to load employee';
                console.error('Employee load error:', err);
                return null;
            } finally {
                this.loading = false;
            }
        },

        async createEmployee(payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/employees' } });
                    return null;
                }

                const res = await employeesApi.create(payload);
                const employee = res.data?.data || res.data;
                if (employee) {
                    this.employees.unshift(employee);
                    this.pagination.total += 1;
                }
                return employee;
            } catch (err) {
                if (err.response?.status === 422) {
                    // Validation errors from server
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }

                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    const id = Math.max(0, ...this.employees.map((item) => item.id || 0)) + 1;
                    const employee = { id, status: 'نشط', avatar: '/placeholder.jpg', ...payload };
                    this.employees.unshift(employee);
                    this.pagination.total += 1;
                    return employee;
                }

                this.error = err.response?.data?.message || err.message || 'Failed to create employee';
                console.error('Create employee error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async updateEmployee(id, payload) {
            this.loading = true;
            this.error = null;
            this.validationErrors = {};

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/employees' } });
                    return null;
                }

                const res = await employeesApi.update(id, payload);
                const updated = res.data?.data || res.data;
                if (updated) {
                    this.employees = this.employees.map((employee) => (employee.id === id ? { ...employee, ...updated } : employee));
                }
                return updated;
            } catch (err) {
                if (err.response?.status === 422) {
                    this.validationErrors = err.response?.data?.errors || {};
                    this.error = err.response?.data?.message || 'Validation failed';
                    throw err;
                }

                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.employees = this.employees.map((employee) => (employee.id === id ? { ...employee, ...payload } : employee));
                    return this.employees.find((employee) => employee.id === id) || null;
                }

                this.error = err.response?.data?.message || err.message || 'Failed to update employee';
                console.error('Update employee error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async deleteEmployee(id) {
            this.loading = true;
            this.error = null;

            try {
                const auth = useAuthStore();
                if (!auth.token) {
                    router.push({ path: '/login', query: { redirect: '/admin/hr/employees' } });
                    return;
                }

                await employeesApi.delete(id);
                this.employees = this.employees.filter((employee) => employee.id !== id);
                this.pagination.total -= 1;
            } catch (err) {
                if (err.response?.status === 404 || err.code === 'ERR_NETWORK') {
                    this.employees = this.employees.filter((employee) => employee.id !== id);
                    this.pagination.total -= 1;
                    return;
                }

                this.error = err.response?.data?.message || err.message || 'Failed to delete employee';
                console.error('Delete employee error:', err);
                throw err;
            } finally {
                this.loading = false;
            }
        }
    }
});
