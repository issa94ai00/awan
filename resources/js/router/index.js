import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        redirect: '/admin/dashboard'
    },
    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: 'dashboard',
                name: 'admin.dashboard',
                component: () => import('@/views/admin/Dashboard.vue')
            },
            {
                path: 'products',
                name: 'admin.products.index',
                component: () => import('@/views/admin/products/Index.vue')
            },
            {
                path: 'products/create',
                name: 'admin.products.create',
                component: () => import('@/views/admin/products/Form.vue')
            },
            {
                path: 'products/:id/edit',
                name: 'admin.products.edit',
                component: () => import('@/views/admin/products/Form.vue')
            },
            {
                path: 'categories',
                name: 'admin.categories.index',
                component: () => import('@/views/admin/categories/Index.vue')
            },
            {
                path: 'categories/create',
                name: 'admin.categories.create',
                component: () => import('@/views/admin/categories/Form.vue')
            },
            {
                path: 'categories/:id/edit',
                name: 'admin.categories.edit',
                component: () => import('@/views/admin/categories/Form.vue')
            },
            {
                path: 'settings',
                name: 'admin.settings',
                component: () => import('@/views/admin/Settings.vue')
            }
        ]
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Navigation guard for authentication
router.beforeEach((to, from, next) => {
    // Check if route requires authentication
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
    
    if (requiresAuth) {
        // Check if user is authenticated
        const token = localStorage.getItem('token');
        
        if (!token) {
            // Redirect to login
            next('/login');
        } else {
            next();
        }
    } else {
        next();
    }
});

export default router;
