<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\ManualRiskAuditService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $perPage = min(max((int)($request->per_page ?? 25), 5), 100);
        $logs = $this->buildQuery($request)->latest()->paginate($perPage);

        return response()->json($logs);
    }

    public function export(Request $request)
    {
        $logs = $this->buildQuery($request)->latest()->limit(5000)->get();

        $csv = fopen('php://temp', 'r+');
        // Output UTF-8 BOM so spreadsheet viewers render Arabic characters accurately
        fputs($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['المعرف', 'التاريخ والوقت', 'المستخدم', 'البريد الإلكتروني', 'الإجراء', 'مستوى الخطورة', 'الوحدة', 'الكيان', 'الوصف', 'عنوان IP', 'المتصفح والجهاز']);

        foreach ($logs as $log) {
            fputcsv($csv, [
                $log->id,
                $log->created_at?->format('Y-m-d H:i:s'),
                $log->user?->name ?? 'النظام',
                $log->user?->email ?? '-',
                $log->action_text ?? $log->action,
                $log->severity,
                $log->module_text ?? $log->module,
                $log->entity_type . ($log->entity_id ? " #{$log->entity_id}" : ''),
                $log->description,
                $log->ip_address,
                $log->user_agent,
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $filename = 'audit-logs-' . now()->format('Y-m-d_H-i') . '.csv';

        return response($content, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function show($id)
    {
        $log = AuditLog::with('user')->findOrFail($id);

        return response()->json($log);
    }

    public function getEntityLogs(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', '%' . $request->entity_type . '%');
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        $perPage = min(max((int)($request->per_page ?? 20), 5), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function getUserLogs($userId, Request $request)
    {
        $query = AuditLog::with('user')->byUser($userId);

        if ($request->filled('module')) {
            $query->byModule($request->module);
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        $perPage = min(max((int)($request->per_page ?? 20), 5), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function getModuleLogs($module, Request $request)
    {
        $query = AuditLog::with('user')->byModule($module);

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        $perPage = min(max((int)($request->per_page ?? 20), 5), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function getRecentLogs(Request $request)
    {
        $days = (int)($request->days ?? 7);
        $logs = $this->auditService->getRecentLogs($days);

        return response()->json($logs);
    }

    public function getTodayLogs()
    {
        $logs = $this->auditService->getTodayLogs();

        return response()->json($logs);
    }

    public function getStatistics(Request $request)
    {
        $days = (int)($request->days ?? 30);
        $statistics = $this->auditService->getStatistics($days);

        return response()->json($statistics);
    }

    public function riskScan(ManualRiskAuditService $manualRiskAuditService)
    {
        $result = $manualRiskAuditService->scan();

        return response()->json($result);
    }

    public function exportRiskScan(ManualRiskAuditService $manualRiskAuditService)
    {
        $result = $manualRiskAuditService->scan();
        $issues = $result['issues'] ?? [];

        $csv = fopen('php://temp', 'r+');
        fputs($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['type', 'severity', 'reference', 'message', 'details']);

        foreach ($issues as $issue) {
            fputcsv($csv, [
                $issue['type'] ?? '',
                $issue['severity'] ?? '',
                $issue['reference'] ?? '',
                $issue['message'] ?? '',
                json_encode($issue['details'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response($content, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="manual-risk-audit.csv"');
    }

    public function reconciliationSummary(ManualRiskAuditService $manualRiskAuditService)
    {
        return response()->json($manualRiskAuditService->reconciliationSummary());
    }

    public function getActivityTimeline(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
        ]);

        $timeline = $this->auditService->getActivityTimeline(
            $validated['entity_type'],
            $validated['entity_id']
        );

        return response()->json($timeline);
    }

    public function getUserActivitySummary($userId, Request $request)
    {
        $days = (int)($request->days ?? 30);
        $user = \App\Models\User::find($userId);
        $summary = $this->auditService->getUserActivitySummary($userId, $days);
        $summary['user_name'] = $user?->name ?? 'User #' . $userId;
        $summary['user_email'] = $user?->email ?? '';

        return response()->json($summary);
    }

    public function getMyActivitySummary(Request $request)
    {
        $days = (int)($request->days ?? 30);
        $summary = $this->auditService->getUserActivitySummary(auth()->id(), $days);
        $summary['user_name'] = auth()->user()?->name ?? 'Current User';
        $summary['user_email'] = auth()->user()?->email ?? '';

        return response()->json($summary);
    }

    public function cleanupOldLogs(Request $request)
    {
        $days = (int)($request->days ?? 90);
        $deleted = $this->auditService->cleanupOldLogs($days);

        return response()->json(['deleted' => $deleted]);
    }

    protected function buildQuery(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('user_agent', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('module')) {
            $query->byModule($request->module);
        }

        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        if ($request->filled('severity')) {
            if ($request->severity === 'critical') {
                $query->whereIn('action', [AuditLog::ACTION_FAILED_LOGIN, 'force_delete']);
            } elseif ($request->severity === 'warning') {
                $query->whereIn('action', [
                    AuditLog::ACTION_DELETE,
                    AuditLog::ACTION_CANCEL,
                    AuditLog::ACTION_REJECT,
                    AuditLog::ACTION_PASSWORD_CHANGE,
                    AuditLog::ACTION_REVOKE_SESSION,
                ]);
            } elseif ($request->severity === 'info') {
                $query->whereNotIn('action', [
                    AuditLog::ACTION_FAILED_LOGIN,
                    'force_delete',
                    AuditLog::ACTION_DELETE,
                    AuditLog::ACTION_CANCEL,
                    AuditLog::ACTION_REJECT,
                    AuditLog::ACTION_PASSWORD_CHANGE,
                    AuditLog::ACTION_REVOKE_SESSION,
                ]);
            }
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', '%' . $request->entity_type . '%');
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        } elseif ($request->filled('days')) {
            $query->recent((int)$request->days);
        }

        return $query;
    }

}
