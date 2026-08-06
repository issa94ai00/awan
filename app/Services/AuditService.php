<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit entry
     */
    public function log($action, $entityType = null, $entityId = null, $description = null, array $oldValues = null, array $newValues = null, $module = null, $userId = null): AuditLog
    {
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
        $logs = AuditLog::recent($days);

        return [
            'total_logs' => $logs->count(),
            'by_action' => $logs->groupBy('action')->map->count(),
            'by_module' => $logs->groupBy('module')->map->count(),
            'by_user' => $logs->groupBy('user_id')->map->count(),
            'today_logs' => AuditLog::today()->count(),
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
        $logs = AuditLog::byUser($userId)->recent($days);

        return [
            'total_actions' => $logs->count(),
            'by_action' => $logs->groupBy('action')->map->count(),
            'by_module' => $logs->groupBy('module')->map->count(),
            'last_activity' => $logs->first()?->created_at,
            'most_active_module' => $logs->groupBy('module')->map->count()->sortDesc()->keys()->first(),
        ];
    }
}
