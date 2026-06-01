<template>
    <aside class="admin-sidebar" :class="{ collapsed: collapsed }">
        <div class="sidebar-header">
            <div class="logo">
                <el-icon :size="28"><Box /></el-icon>
                <span v-if="!collapsed">أوان التكادوم</span>
            </div>
            <el-button
                v-if="!collapsed"
                :icon="Fold"
                circle
                size="small"
                @click="toggleSidebar"
            />
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li>
                    <router-link to="/admin/dashboard" class="nav-link" :class="{ active: isActive('/admin/dashboard') }">
                        <el-icon><Odometer /></el-icon>
                        <span v-if="!collapsed">لوحة التحكم</span>
                    </router-link>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('content') }">
                    <div class="nav-group-header" @click="toggleGroup('content')">
                        <el-icon><Box /></el-icon>
                        <span v-if="!collapsed">إدارة المحتوى</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('content') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('content')">
                        <li>
                            <router-link to="/admin/categories" class="nav-link">
                                <el-icon><Folder /></el-icon>
                                <span v-if="!collapsed">الفئات</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/products" class="nav-link">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">المنتجات</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('sales') }">
                    <div class="nav-group-header" @click="toggleGroup('sales')">
                        <el-icon><ShoppingCart /></el-icon>
                        <span v-if="!collapsed">المبيعات</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('sales') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('sales')">
                        <li>
                            <router-link to="/admin/sales" class="nav-link" :class="{ active: isActive('/admin/sales') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/invoices" class="nav-link" :class="{ active: isActive('/admin/sales/invoices') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">الفواتير</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/customers" class="nav-link" :class="{ active: isActive('/admin/sales/customers') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">العملاء</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/quotes" class="nav-link" :class="{ active: isActive('/admin/sales/quotes') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">عروض الأسعار</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/sales-orders" class="nav-link" :class="{ active: isActive('/admin/sales/sales-orders') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">طلبات البيع</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/sales/payments" class="nav-link" :class="{ active: isActive('/admin/sales/payments') }">
                                <el-icon><Wallet /></el-icon>
                                <span v-if="!collapsed">المدفوعات</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('purchases') }">
                    <div class="nav-group-header" @click="toggleGroup('purchases')">
                        <el-icon><ShoppingBag /></el-icon>
                        <span v-if="!collapsed">المشتريات</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('purchases') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('purchases')">
                        <li>
                            <router-link to="/admin/purchases" class="nav-link" :class="{ active: isActive('/admin/purchases') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/suppliers" class="nav-link" :class="{ active: isActive('/admin/purchases/suppliers') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">الموردين</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/orders" class="nav-link" :class="{ active: isActive('/admin/purchases/orders') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">طلبات الشراء</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/purchases/receipts" class="nav-link" :class="{ active: isActive('/admin/purchases/receipts') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">إيصالات الاستلام</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('accounting') }">
                    <div class="nav-group-header" @click="toggleGroup('accounting')">
                        <el-icon><Coin /></el-icon>
                        <span v-if="!collapsed">المحاسبة</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('accounting') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('accounting')">
                        <li>
                            <router-link to="/admin/accounting" class="nav-link" :class="{ active: isActive('/admin/accounting') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/journal" class="nav-link" :class="{ active: isActive('/admin/accounting/journal') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">دفتر اليومية</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/ledger" class="nav-link" :class="{ active: isActive('/admin/accounting/ledger') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">دفتر الأستاذ</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/accounting/trial-balance" class="nav-link" :class="{ active: isActive('/admin/accounting/trial-balance') }">
                                <el-icon><Document /></el-icon>
                                <span v-if="!collapsed">ميزان المراجعة</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('inventory') }">
                    <div class="nav-group-header" @click="toggleGroup('inventory')">
                        <el-icon><Box /></el-icon>
                        <span v-if="!collapsed">المخزون</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('inventory') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('inventory')">
                        <li>
                            <router-link to="/admin/inventory" class="nav-link" :class="{ active: isActive('/admin/inventory') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/inventory/movements" class="nav-link" :class="{ active: isActive('/admin/inventory/movements') }">
                                <el-icon><Refresh /></el-icon>
                                <span v-if="!collapsed">حركات المخزون</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('hr') }">
                    <div class="nav-group-header" @click="toggleGroup('hr')">
                        <el-icon><UserFilled /></el-icon>
                        <span v-if="!collapsed">الموارد البشرية</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('hr') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('hr')">
                        <li>
                            <router-link to="/admin/hr" class="nav-link" :class="{ active: isActive('/admin/hr') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/employees" class="nav-link" :class="{ active: isActive('/admin/hr/employees') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">الموظفين</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/attendance" class="nav-link" :class="{ active: isActive('/admin/hr/attendance') }">
                                <el-icon><Clock /></el-icon>
                                <span v-if="!collapsed">الحضور والانصراف</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/leaves" class="nav-link" :class="{ active: isActive('/admin/hr/leaves') }">
                                <el-icon><Calendar /></el-icon>
                                <span v-if="!collapsed">الإجازات</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/hr/payrolls" class="nav-link" :class="{ active: isActive('/admin/hr/payrolls') }">
                                <el-icon><Wallet /></el-icon>
                                <span v-if="!collapsed">الرواتب</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('crm') }">
                    <div class="nav-group-header" @click="toggleGroup('crm')">
                        <el-icon><ChatDotRound /></el-icon>
                        <span v-if="!collapsed">إدارة علاقات العملاء</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('crm') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('crm')">
                        <li>
                            <router-link to="/admin/crm" class="nav-link" :class="{ active: isActive('/admin/crm') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/crm/customers" class="nav-link" :class="{ active: isActive('/admin/crm/customers') }">
                                <el-icon><User /></el-icon>
                                <span v-if="!collapsed">العملاء</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/crm/tickets" class="nav-link" :class="{ active: isActive('/admin/crm/tickets') }">
                                <el-icon><Ticket /></el-icon>
                                <span v-if="!collapsed">التذاكر</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('production') }">
                    <div class="nav-group-header" @click="toggleGroup('production')">
                        <el-icon><Tools /></el-icon>
                        <span v-if="!collapsed">الإنتاج</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('production') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('production')">
                        <li>
                            <router-link to="/admin/production" class="nav-link" :class="{ active: isActive('/admin/production') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('reports') }">
                    <div class="nav-group-header" @click="toggleGroup('reports')">
                        <el-icon><DataAnalysis /></el-icon>
                        <span v-if="!collapsed">التقارير</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('reports') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('reports')">
                        <li>
                            <router-link to="/admin/reports" class="nav-link" :class="{ active: isActive('/admin/reports') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">نظرة عامة</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/sales" class="nav-link" :class="{ active: isActive('/admin/reports/sales') }">
                                <el-icon><TrendCharts /></el-icon>
                                <span v-if="!collapsed">تقرير المبيعات</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/inventory" class="nav-link" :class="{ active: isActive('/admin/reports/inventory') }">
                                <el-icon><Box /></el-icon>
                                <span v-if="!collapsed">تقرير المخزون</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/financial" class="nav-link" :class="{ active: isActive('/admin/reports/financial') }">
                                <el-icon><Coin /></el-icon>
                                <span v-if="!collapsed">التقرير المالي</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/reports/payroll" class="nav-link" :class="{ active: isActive('/admin/reports/payroll') }">
                                <el-icon><Wallet /></el-icon>
                                <span v-if="!collapsed">تقرير الرواتب</span>
                            </router-link>
                        </li>
                    </ul>
                </li>

                <li>
                    <router-link to="/admin/pos" class="nav-link" :class="{ active: isActive('/admin/pos') }">
                        <el-icon><Monitor /></el-icon>
                        <span v-if="!collapsed">نقطة البيع</span>
                    </router-link>
                </li>

                <li>
                    <router-link to="/admin/inquiries" class="nav-link" :class="{ active: isActive('/admin/inquiries') }">
                        <el-icon><ChatLineRound /></el-icon>
                        <span v-if="!collapsed">الاستفسارات</span>
                    </router-link>
                </li>

                <li>
                    <router-link to="/admin/visitors" class="nav-link" :class="{ active: isActive('/admin/visitors') }">
                        <el-icon><View /></el-icon>
                        <span v-if="!collapsed">الزوار</span>
                    </router-link>
                </li>

                <li class="nav-group" :class="{ open: isGroupOpen('system') }">
                    <div class="nav-group-header" @click="toggleGroup('system')">
                        <el-icon><Setting /></el-icon>
                        <span v-if="!collapsed">النظام</span>
                        <el-icon class="toggle-icon" :class="{ rotated: isGroupOpen('system') }"><ArrowDown /></el-icon>
                    </div>
                    <ul class="nav-group-items" v-show="isGroupOpen('system')">
                        <li>
                            <router-link to="/admin/roles" class="nav-link" :class="{ active: isActive('/admin/roles') }">
                                <el-icon><Lock /></el-icon>
                                <span v-if="!collapsed">الأدوار</span>
                            </router-link>
                        </li>
                        <li>
                            <router-link to="/admin/permissions" class="nav-link" :class="{ active: isActive('/admin/permissions') }">
                                <el-icon><Key /></el-icon>
                                <span v-if="!collapsed">الصلاحيات</span>
                            </router-link>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <el-button
                v-if="collapsed"
                :icon="Expand"
                circle
                size="small"
                @click="toggleSidebar"
            />
            <router-link v-if="!collapsed" to="/admin/settings" class="footer-link" :class="{ active: isActive('/admin/settings') }">
                <el-icon><Setting /></el-icon>
                <span>الإعدادات</span>
            </router-link>
        </div>
    </aside>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
    Box, Fold, Expand, Odometer, Folder, ShoppingCart,
    TrendCharts, Document, ArrowDown, Setting, ShoppingBag,
    Coin, Wallet, UserFilled, User, ChatDotRound, Tools,
    DataAnalysis, Monitor, ChatLineRound, View, Lock, Key,
    Clock, Calendar, Ticket, Refresh
} from '@element-plus/icons-vue';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:collapsed']);

const route = useRoute();
const openGroups = ref(['content']);

const isActive = (path) => {
    return route.path === path || route.path.startsWith(path + '/');
};

const isGroupOpen = (group) => {
    return openGroups.value.includes(group);
};

const toggleGroup = (group) => {
    const index = openGroups.value.indexOf(group);
    if (index > -1) {
        openGroups.value.splice(index, 1);
    } else {
        openGroups.value = [group];
    }
};

const toggleSidebar = () => {
    emit('update:collapsed', !props.collapsed);
    localStorage.setItem('sidebarCollapsed', !props.collapsed);
};

watch(() => props.collapsed, (newVal) => {
    if (newVal) {
        openGroups.value = [];
    }
});
</script>

<style scoped>
.admin-sidebar {
    width: 260px;
    background: linear-gradient(180deg, #1a2332 0%, #2d3e50 100%);
    color: white;
    position: fixed;
    right: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.admin-sidebar.collapsed {
    width: 70px;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
    font-weight: 600;
    font-size: 1.125rem;
}

.sidebar-nav {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
}

.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.5rem;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: all 0.3s ease;
    border-right: 3px solid transparent;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: white;
    font-weight: 600;
    border-right-color: #d4af37;
}

.nav-group {
    margin-bottom: 0.25rem;
}

.nav-group-header {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    color: rgba(255, 255, 255, 0.85);
    border-right: 3px solid transparent;
}

.nav-group-header:hover {
    background: rgba(255, 255, 255, 0.08);
    color: white;
    transform: translateX(-4px);
}

.toggle-icon {
    margin-left: auto;
    transition: transform 0.3s ease;
    font-size: 0.75rem;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

.nav-group-items {
    padding-right: 1.5rem;
    list-style: none;
    padding: 0;
    margin: 0.25rem 0;
}

.nav-group-items li {
    margin-bottom: 0.25rem;
}

.nav-group-items .nav-link {
    padding: 0.625rem 1.5rem;
    font-size: 0.875rem;
}

.sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: center;
}

.footer-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.footer-link:hover {
    color: white;
}

.footer-link.active {
    background: rgba(255, 255, 255, 0.12);
    color: white;
    font-weight: 600;
    border-right-color: #d4af37;
}

@media (max-width: 992px) {
    .admin-sidebar {
        transform: translateX(100%);
    }
    
    .admin-sidebar:not(.collapsed) {
        transform: translateX(0);
    }
}
</style>
