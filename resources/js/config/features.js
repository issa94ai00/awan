// Maps the admin SPA's URL prefixes to the ERP feature key that guards them.
// The keys match the `key` column of the `features` table and the `group` of
// each collapsible sidebar entry, so the router guard and the sidebar agree
// with the API's `feature` middleware.
//
// Order matters only in that the first matching prefix wins, so keep more
// specific paths before the broader ones that contain them.
const FEATURE_PATHS = [
    ['content', ['/admin/categories', '/admin/products', '/admin/special-offers', '/admin/secondary-navbar']],
    ['sales', ['/admin/sales']],
    ['rma', ['/admin/rma']],
    ['purchases', ['/admin/purchases', '/admin/purchase-requests']],
    ['accounting', ['/admin/accounting']],
    ['inventory', ['/admin/inventory', '/admin/stock']],
    ['wms', ['/admin/wms']],
    ['hr', ['/admin/hr']],
    ['crm', ['/admin/crm']],
    ['production', ['/admin/production']],
    ['reports', ['/admin/reports']],
    ['bi_analytics', ['/admin/analytics']],
    ['workflows', ['/admin/workflows']],
    ['notifications_management', ['/admin/notifications']],
    ['audit_logs', ['/admin/audit']],
    ['pos', ['/admin/pos']],
    ['inquiries', ['/admin/inquiries']],
    ['visitors', ['/admin/visitors']],
    ['system', ['/admin/roles', '/admin/permissions', '/admin/users', '/admin/currencies', '/admin/features']],
];

/**
 * The feature key that guards an admin path, or null when the path belongs to
 * no managed module (the dashboard, page-level screens) and is therefore open.
 */
export function featureForPath(path) {
    const target = String(path || '');
    for (const [key, prefixes] of FEATURE_PATHS) {
        for (const prefix of prefixes) {
            if (target === prefix || target.startsWith(prefix + '/')) {
                return key;
            }
        }
    }
    return null;
}

export default FEATURE_PATHS;
