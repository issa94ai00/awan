<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'icon',
        'group',
        'is_enabled',
        'is_core',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_core' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'feature_role');
    }

    /**
     * The feature keys the given user may open. Super admins get every enabled
     * feature; everyone else gets the enabled features any of their roles is granted.
     *
     * @return array<int, string>
     */
    public static function accessibleKeysFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $enabled = static::where('is_enabled', true)->pluck('key')->all();

        if ($user->isSuperAdmin()) {
            return array_values($enabled);
        }

        $roleIds = $user->roleIds();
        if (! $roleIds) {
            return [];
        }

        return static::where('is_enabled', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->pluck('key')
            ->all();
    }

    /**
     * Whether the given user may open the feature named by key.
     */
    public static function accessible(string $key, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $feature = static::where('key', $key)->first();

        if (! $feature || ! $feature->is_enabled) {
            return false;
        }

        if ($feature->is_core || $user->isSuperAdmin()) {
            return true;
        }

        return $feature->roles()->whereIn('roles.id', $user->roleIds())->exists();
    }

    /**
     * API paths more than one module reads. The sales and invoice reports
     * back both the Sales > reports screen and the Reports module, and a
     * sales rep gets them confined to their own business
     * (SalesReportController::applyOwnSalesScope).
     */
    private const SHARED_PATHS = [
        'admin/reports/sales' => ['sales', 'reports'],
        'admin/reports/invoices' => ['sales', 'reports'],
        // The client list and its add/edit live under /pos, but the CRM, sales
        // and quote screens use them too: a sales role keeps its clients while
        // the POS module is switched off. Adding one also needs customers.create.
        'pos/customers' => ['pos', 'sales', 'crm'],
        // The product search of the sales-order and quote forms: a sales role
        // sees the whole catalogue there with POS switched off.
        'pos/products/lookup' => ['pos', 'sales'],
    ];

    /**
     * Lookups every signed-in user reads whatever modules they hold: the
     * warehouse list fills the warehouse pickers and report filters of the
     * sales, inventory and report screens. Only reading them is open; writing
     * stays behind the module (and the admin gate on the routes).
     */
    private const OPEN_LOOKUPS = [
        'admin/wms/warehouses',
        'admin/wms/managers',
        'wms/warehouses',
        'wms/managers',
    ];

    public static function isOpenLookup(string $path): bool
    {
        $path = trim($path, '/');

        foreach (self::OPEN_LOOKUPS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every feature key that may open an API path — holding any one of them
     * is enough. Empty for paths that belong to no managed module.
     *
     * @return array<int, string>
     */
    public static function resolveKeysForPath(string $path): array
    {
        $path = trim($path, '/');

        foreach (self::SHARED_PATHS as $prefix => $keys) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $keys;
            }
        }

        $key = static::resolveKeyForPath($path);

        return $key === null ? [] : [$key];
    }

    /**
     * Map a request path (after /api/v1) to the feature that guards it. Returns
     * null for paths that belong to no managed module, which are left open.
     */
    public static function resolveKeyForPath(string $path): ?string
    {
        $path = trim($path, '/');

        // Only modules that already had a role gate, plus the admin-only
        // management screens, are listed. Shared lookups that every role reads
        // (analytics feeds the dashboard, the notifications header polls the
        // unread count) are deliberately left out so switching a feature off
        // for one role does not break the shell for everyone else.
        $map = [
            // Warehouse
            'admin/wms' => 'wms',
            'wms' => 'wms',
            'warehouse-bins' => 'wms',
            // Sales & returns
            'sales' => 'sales',
            'sales-orders' => 'sales',
            'invoices' => 'sales',
            'quotes' => 'sales',
            'payments' => 'sales',
            'rmas' => 'rma',
            'admin/rma' => 'rma',
            'admin/credit-notes' => 'rma',
            // Purchases
            'purchases' => 'purchases',
            'purchase-orders' => 'purchases',
            'purchase-receipts' => 'purchases',
            'purchase-returns' => 'purchases',
            'purchase-requests' => 'purchases',
            'suppliers' => 'purchases',
            'supplier-payments' => 'purchases',
            'admin/suppliers' => 'purchases',
            'admin/purchase-orders' => 'purchases',
            'admin/purchase-receipts' => 'purchases',
            'admin/purchase-returns' => 'purchases',
            'admin/supplier-payments' => 'purchases',
            // Accounting
            'accounting' => 'accounting',
            'admin/accounting' => 'accounting',
            'expenses' => 'accounting',
            'admin/expenses' => 'accounting',
            'admin/unpriced-stock' => 'accounting',
            // Inventory
            'inventory' => 'inventory',
            'admin/inventory' => 'inventory',
            'inventory-transfers' => 'inventory',
            // Human resources
            'admin/employees' => 'hr',
            'employees' => 'hr',
            'admin/attendance' => 'hr',
            'attendance' => 'hr',
            'leave-requests' => 'hr',
            'admin/leave-requests' => 'hr',
            'admin/employee-commissions' => 'hr',
            // Payrolls are gated by role:accountant on the API (salary figures
            // are an accounting concern), so they follow the accounting feature
            // rather than hr, which only admins are granted.
            'payrolls' => 'accounting',
            // CRM & production
            'admin/customers' => 'crm',
            'tickets' => 'crm',
            'admin/tickets' => 'crm',
            'production' => 'production',
            // Reports & content
            'admin/reports' => 'reports',
            'reports' => 'reports',
            'admin/products' => 'content',
            'admin/categories' => 'content',
            'admin/price-offer-lists' => 'content',
            'admin/special-offers' => 'content',
            'admin/product-variants' => 'content',
            'admin/product-units' => 'content',
            'admin/product-warehouse-assignments' => 'content',
            'admin/composite-products' => 'content',
            'admin/mrp' => 'content',
            'admin/inquiries' => 'inquiries',
            'pos' => 'pos',
            // Automation & audit (admin-only screens)
            'workflows' => 'workflows',
            'audit' => 'audit_logs',
            // System settings & the management screens themselves
            'admin/features' => 'system',
            'admin/roles' => 'system',
            'admin/permissions' => 'system',
            'admin/access' => 'system',
            'settings' => 'system',
            // Currency management carries role:accountant on the API, so it
            // belongs to accounting or accountants would lose it.
            'admin/currencies' => 'accounting',
        ];

        foreach ($map as $prefix => $key) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $key;
            }
        }

        return null;
    }

    /**
     * User-facing admin routes protected by this feature.
     *
     * @return array<int, string>
     */
    public static function pathsForKey(string $key): array
    {
        $map = [
            'dashboard' => ['/admin/dashboard'],
            'content' => ['/admin/categories', '/admin/products', '/admin/special-offers', '/admin/secondary-navbar', '/admin/product-units'],
            'sales' => ['/admin/sales', '/admin/sales-orders', '/admin/invoices', '/admin/quotes', '/admin/payments'],
            'rma' => ['/admin/rma', '/admin/credit-notes'],
            'purchases' => ['/admin/purchases', '/admin/suppliers', '/admin/purchase-orders', '/admin/purchase-receipts', '/admin/purchase-returns'],
            'accounting' => ['/admin/accounting', '/admin/expenses', '/admin/currencies', '/admin/payrolls'],
            'inventory' => ['/admin/inventory', '/admin/stock', '/admin/inventory-transfers'],
            'wms' => ['/admin/wms', '/admin/warehouse-bins'],
            'hr' => ['/admin/hr', '/admin/employees', '/admin/attendance', '/admin/leave-requests', '/admin/employee-commissions'],
            'crm' => ['/admin/crm', '/admin/customers', '/admin/tickets'],
            'production' => ['/admin/production', '/admin/mrp'],
            'reports' => ['/admin/reports'],
            'bi_analytics' => ['/admin/analytics'],
            'workflows' => ['/admin/workflows'],
            'notifications_management' => ['/admin/notifications'],
            'audit_logs' => ['/admin/audit'],
            'pos' => ['/admin/pos'],
            'inquiries' => ['/admin/inquiries'],
            'visitors' => ['/admin/visitors'],
            'system' => ['/admin/roles', '/admin/permissions', '/admin/users', '/admin/features', '/admin/settings'],
        ];

        return $map[$key] ?? [];
    }
}
