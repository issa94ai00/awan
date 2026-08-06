import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import i18n from './i18n';

// Import Vue components for admin pages
import WmsIndex from './views/admin/wms/Index.vue';
import WmsWarehouses from './views/admin/wms/Warehouses.vue';
import WmsWarehouseForm from './views/admin/wms/WarehouseForm.vue';
import WmsBins from './views/admin/wms/Bins.vue';
import WmsBinForm from './views/admin/wms/BinForm.vue';
import WmsPickingLists from './views/admin/wms/PickingLists.vue';
import WmsPickingForm from './views/admin/wms/PickingForm.vue';
import WmsPackingLists from './views/admin/wms/PackingLists.vue';
import WmsPackingForm from './views/admin/wms/PackingForm.vue';
import WmsCycleCounts from './views/admin/wms/CycleCounts.vue';
import WmsCycleCountForm from './views/admin/wms/CycleCountForm.vue';
import WmsPerformance from './views/admin/wms/Performance.vue';

import AnalyticsIndex from './views/admin/analytics/Index.vue';
import AnalyticsSales from './views/admin/analytics/Sales.vue';
import AnalyticsInventory from './views/admin/analytics/Inventory.vue';
import AnalyticsFinancial from './views/admin/analytics/Financial.vue';
import AnalyticsWarehouse from './views/admin/analytics/Warehouse.vue';
import AnalyticsMetrics from './views/admin/analytics/Metrics.vue';
import AnalyticsReports from './views/admin/analytics/Reports.vue';
import AnalyticsDashboards from './views/admin/analytics/Dashboards.vue';

import NotificationsIndex from './views/admin/notifications/Index.vue';
import NotificationsTemplates from './views/admin/notifications/Templates.vue';
import NotificationsTemplateForm from './views/admin/notifications/TemplateForm.vue';
import NotificationsPreferences from './views/admin/notifications/Preferences.vue';

import WorkflowsIndex from './views/admin/workflows/Index.vue';
import WorkflowsForm from './views/admin/workflows/Form.vue';
import WorkflowsShow from './views/admin/workflows/Show.vue';
import WorkflowsSteps from './views/admin/workflows/Steps.vue';
import WorkflowsExecutions from './views/admin/workflows/Executions.vue';

import AuditIndex from './views/admin/audit/Index.vue';
import AuditEntityLogs from './views/admin/audit/EntityLogs.vue';
import AuditUserActivity from './views/admin/audit/UserActivity.vue';
import AuditModuleLogs from './views/admin/audit/ModuleLogs.vue';
import AuditStatistics from './views/admin/audit/Statistics.vue';

import RmaIndex from './views/admin/rma/Index.vue';
import RmaForm from './views/admin/rma/Form.vue';
import RmaShow from './views/admin/rma/Show.vue';

// Component mapping for div IDs
const componentMap = {
    'wms-index': WmsIndex,
    'wms-warehouses': WmsWarehouses,
    'wms-warehouse-form': WmsWarehouseForm,
    'wms-bins': WmsBins,
    'wms-bin-form': WmsBinForm,
    'wms-picking': WmsPickingLists,
    'wms-picking-form': WmsPickingForm,
    'wms-packing': WmsPackingLists,
    'wms-packing-form': WmsPackingForm,
    'wms-cycle-counts': WmsCycleCounts,
    'wms-cycle-count-form': WmsCycleCountForm,
    'wms-performance': WmsPerformance,
    
    'analytics-index': AnalyticsIndex,
    'analytics-sales': AnalyticsSales,
    'analytics-inventory': AnalyticsInventory,
    'analytics-financial': AnalyticsFinancial,
    'analytics-warehouse': AnalyticsWarehouse,
    'analytics-metrics': AnalyticsMetrics,
    'analytics-reports': AnalyticsReports,
    'analytics-dashboards': AnalyticsDashboards,
    
    'notifications-index': NotificationsIndex,
    'notifications-templates': NotificationsTemplates,
    'notifications-template-form': NotificationsTemplateForm,
    'notifications-preferences': NotificationsPreferences,
    
    'workflows-index': WorkflowsIndex,
    'workflows-form': WorkflowsForm,
    'workflows-show': WorkflowsShow,
    'workflows-steps': WorkflowsSteps,
    'workflows-executions': WorkflowsExecutions,
    
    'audit-index': AuditIndex,
    'audit-entity-logs': AuditEntityLogs,
    'audit-user-activity': AuditUserActivity,
    'audit-module-logs': AuditModuleLogs,
    'audit-statistics': AuditStatistics,
    
    'rma-index': RmaIndex,
    'rma-form': RmaForm,
    'rma-show': RmaShow
};

// Mount Vue components to their respective div IDs
document.addEventListener('DOMContentLoaded', () => {
    Object.entries(componentMap).forEach(([divId, component]) => {
        const element = document.getElementById(divId);
        if (element) {
            const app = createApp(component);
            const pinia = createPinia();
            
            // Register all Element Plus icons
            for (const [key, iconComponent] of Object.entries(ElementPlusIconsVue)) {
                app.component(key, iconComponent);
            }
            
            app.use(pinia);
            app.use(i18n);
            app.use(ElementPlus);
            
            // Register global translation helper
            window.t = (key) => i18n.global.t(key);
            
            // Register global property for dynamic db translations
            app.config.globalProperties.$p = (obj, field) => {
                if (!obj) return '';
                const currentLocale = i18n.global.locale.value;
                if (currentLocale === 'en') {
                    return obj[`${field}_en`] || obj[`${field}_ar`] || obj[field] || '';
                }
                return obj[`${field}_ar`] || obj[field] || '';
            };
            
            // Handle RTL / LTR direction
            const updateDirection = (locale) => {
                const isRtl = locale === 'ar';
                document.documentElement.setAttribute('lang', locale);
                document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
            };
            
            updateDirection(i18n.global.locale.value);
            
            app.mount(element);
        }
    });
});
