<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit entry
     */
    public function log(
        $action,
        $entityType = null,
        $entityId = null,
        $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        $module = null,
        $userId = null,
        ?array $metadata = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'module' => $module,
            'metadata' => $metadata,
        ]);
    }


    /**
     * Log create action
     */
    public function logCreate($entity, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_CREATE,
            get_class($entity),
            $entity->id,
            $description ?? "Created {$this->getEntityName($entity)}",
            null,
            $entity->toArray(),
            $module,
            $userId
        );
    }

    /**
     * Log update action
     */
    public function logUpdate($entity, array $oldValues, array $newValues, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_UPDATE,
            get_class($entity),
            $entity->id,
            $description ?? "Updated {$this->getEntityName($entity)}",
            $oldValues,
            $newValues,
            $module,
            $userId
        );
    }

    /**
     * Log delete action
     */
    public function logDelete($entityType, $entityId, $oldValues = null, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_DELETE,
            $entityType,
            $entityId,
            $description ?? "Deleted entity",
            $oldValues,
            null,
            $module,
            $userId
        );
    }

    /**
     * Log view action
     */
    public function logView($entity, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_VIEW,
            get_class($entity),
            $entity->id,
            $description ?? "Viewed {$this->getEntityName($entity)}",
            null,
            null,
            $module,
            $userId
        );
    }

    /**
     * Log login action
     */
    public function logLogin($userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_LOGIN,
            null,
            null,
            'User logged in',
            null,
            null,
            AuditLog::MODULE_USERS,
            $userId
        );
    }

    /**
     * Log logout action
     */
    public function logLogout($userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_LOGOUT,
            null,
            null,
            'User logged out',
            null,
            null,
            AuditLog::MODULE_USERS,
            $userId
        );
    }

    /**
     * Log failed login attempt
     */
    public function logFailedLogin($identifier, $reason = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_FAILED_LOGIN,
            \App\Models\User::class,
            $userId,
            "محاولة تسجيل دخول فاشلة ({$identifier})",
            null,
            null,
            AuditLog::MODULE_SECURITY,
            $userId,
            [
                'identifier' => $identifier,
                'reason' => $reason ?? 'invalid_credentials',
            ]
        );
    }

    /**
     * Log password change action
     */
    public function logPasswordChange($userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_PASSWORD_CHANGE,
            \App\Models\User::class,
            $userId ?? auth()->id(),
            'تم تغيير كلمة المرور وتحديث بيانات الأمان',
            null,
            null,
            AuditLog::MODULE_SECURITY,
            $userId ?? auth()->id()
        );
    }

    /**
     * Log session revocation
     */
    public function logRevokeSession($userId = null, $description = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_REVOKE_SESSION,
            \App\Models\User::class,
            $userId ?? auth()->id(),
            $description ?? 'تم إنهاء جلسة / جهاز للمستخدم',
            null,
            null,
            AuditLog::MODULE_SECURITY,
            $userId ?? auth()->id()
        );
    }


    /**
     * Log export action
     */
    public function logExport($module, $description = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_EXPORT,
            null,
            null,
            $description ?? "Exported data from {$module}",
            null,
            null,
            $module,
            $userId
        );
    }

    /**
     * Log import action
     */
    public function logImport($module, $description = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_IMPORT,
            null,
            null,
            $description ?? "Imported data to {$module}",
            null,
            null,
            $module,
            $userId
        );
    }

    /**
     * Log approve action
     */
    public function logApprove($entity, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_APPROVE,
            get_class($entity),
            $entity->id,
            $description ?? "Approved {$this->getEntityName($entity)}",
            null,
            null,
            $module,
            $userId
        );
    }

    /**
     * Log reject action
     */
    public function logReject($entity, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_REJECT,
            get_class($entity),
            $entity->id,
            $description ?? "Rejected {$this->getEntityName($entity)}",
            null,
            null,
            $module,
            $userId
        );
    }

    /**
     * Log cancel action
     */
    public function logCancel($entity, $description = null, $module = null, $userId = null): AuditLog
    {
        return $this->log(
            AuditLog::ACTION_CANCEL,
            get_class($entity),
            $entity->id,
            $description ?? "Cancelled {$this->getEntityName($entity)}",
            null,
            null,
            $module,
            $userId
        );
    }

    /**
     * Get audit logs for an entity
     */
    public function getEntityLogs($entityType, $entityId, $limit = 50)
    {
        return AuditLog::byEntity($entityType, $entityId)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs for a user
     */
    public function getUserLogs($userId, $limit = 50)
    {
        return AuditLog::byUser($userId)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs by module
     */
    public function getModuleLogs($module, $limit = 50)
    {
        return AuditLog::byModule($module)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent audit logs
     */
    public function getRecentLogs($days = 7, $limit = 100)
    {
        return AuditLog::recent($days)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get today's audit logs
     */
    public function getTodayLogs($limit = 100)
    {
        return AuditLog::today()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs by action
     */
    public function getLogsByAction($action, $limit = 50)
    {
        return AuditLog::byAction($action)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit statistics
     */
    public function getStatistics($days = 30): array
    {
        $logs = AuditLog::recent($days)->with('user')->get();

        $byAction = $logs->groupBy('action')->map->count();
        $byModule = $logs->groupBy('module')->map->count();
        $byUser = $logs->groupBy(fn ($item) => $item->user?->name ?? 'النظام')->map->count();

        // Daily activity trend for the period
        $timeline = [];
        $windowDays = min(max((int)$days, 1), 60);
        for ($i = $windowDays - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $timeline[$date] = 0;
        }
        foreach ($logs as $log) {
            $date = $log->created_at?->format('Y-m-d');
            if ($date && isset($timeline[$date])) {
                $timeline[$date]++;
            }
        }

        // Security events count (failed logins, deletes, password changes, etc.)
        $securityEventsCount = $logs->filter(fn ($l) => in_array($l->action, [
            AuditLog::ACTION_FAILED_LOGIN,
            AuditLog::ACTION_DELETE,
            AuditLog::ACTION_PASSWORD_CHANGE,
            AuditLog::ACTION_REVOKE_SESSION,
        ]))->count();

        // Top users by activity
        $topUsers = $logs->groupBy('user_id')->map(function ($userLogs) {
            $first = $userLogs->first();
            return [
                'user_id' => $first->user_id,
                'user' => $first->user?->name ?? 'النظام (System)',
                'email' => $first->user?->email ?? '-',
                'actions_count' => $userLogs->count(),
                'last_active' => $userLogs->sortByDesc('created_at')->first()?->created_at?->format('Y-m-d H:i') ?? '—',
                'modules' => $userLogs->pluck('module')->unique()->filter()->values()->all(),
            ];
        })->sortByDesc('actions_count')->take(10)->values()->all();

        return [
            'total_logs' => $logs->count(),
            'active_users' => $logs->pluck('user_id')->filter()->unique()->count(),
            'active_modules' => $logs->pluck('module')->filter()->unique()->count(),
            'security_events' => $securityEventsCount,
            'today_logs' => AuditLog::today()->count(),
            'by_action' => $byAction,
            'by_module' => $byModule,
            'by_user' => $byUser,
            'trends' => [
                'dates' => array_keys($timeline),
                'counts' => array_values($timeline),
            ],
            'top_users' => $topUsers,
        ];
    }

    /**
     * Clean up old audit logs
     */
    public function cleanupOldLogs($days = 90): int
    {
        return AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }

    /**
     * Get entity name for description
     */
    protected function getEntityName($entity): string
    {
        if (method_exists($entity, 'name')) {
            return $entity->name;
        }

        if (method_exists($entity, 'title')) {
            return $entity->title;
        }

        if (method_exists($entity, 'order_number')) {
            return $entity->order_number;
        }

        return class_basename($entity) . ' #' . $entity->id;
    }

    /**
     * Get activity timeline for entity
     */
    public function getActivityTimeline($entityType, $entityId): array
    {
        $logs = $this->getEntityLogs($entityType, $entityId);

        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'action_text' => $log->action_text,
                'description' => $log->description,
                'user' => $log->user?->name ?? 'System',
                'changes' => $log->changes,
                'created_at' => $log->created_at,
                'ip_address' => $log->ip_address,
            ];
        })->toArray();
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary($userId, $days = 30): array
    {
        $logs = AuditLog::byUser($userId)->recent($days)->get();

        $actionCounts = $logs->groupBy('action')->map->count();
        $moduleCounts = $logs->groupBy('module')->map->count();
        $daysCount = max((int)$days, 1);

        return [
            'total_actions' => $logs->count(),
            'by_action' => $actionCounts,
            'by_module' => $moduleCounts,
            'last_active' => $logs->sortByDesc('created_at')->first()?->created_at?->format('Y-m-d H:i') ?? '—',
            'avg_daily' => round($logs->count() / $daysCount, 1),
            'most_active_module' => $moduleCounts->sortDesc()->keys()->first() ?? 'none',
        ];
    }

}
