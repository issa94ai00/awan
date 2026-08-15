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
        $query = AuditLog::with('user');

        if ($request->action) {
            $query->byAction($request->action);
        }

        if ($request->module) {
            $query->byModule($request->module);
        }

        if ($request->user_id) {
            $query->byUser($request->user_id);
        }

        if ($request->entity_type && $request->entity_id) {
            $query->byEntity($request->entity_type, $request->entity_id);
        }

        if ($request->days) {
            $query->recent($request->days);
        }

        return response()->json($query->latest()->paginate(50));
    }

    public function show($id)
    {
        $log = AuditLog::with('user')->findOrFail($id);

        return response()->json($log);
    }

    public function getEntityLogs(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
        ]);

        $logs = $this->auditService->getEntityLogs(
            $validated['entity_type'],
            $validated['entity_id']
        );

        return response()->json($logs);
    }

    public function getUserLogs($userId)
    {
        $logs = $this->auditService->getUserLogs($userId);

        return response()->json($logs);
    }

    public function getModuleLogs($module)
    {
        $logs = $this->auditService->getModuleLogs($module);

        return response()->json($logs);
    }

    public function getRecentLogs(Request $request)
    {
        $days = $request->days ?? 7;
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
        $days = $request->days ?? 30;
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
        $days = $request->days ?? 30;
        $summary = $this->auditService->getUserActivitySummary($userId, $days);

        return response()->json($summary);
    }

    public function getMyActivitySummary(Request $request)
    {
        $days = $request->days ?? 30;
        $summary = $this->auditService->getUserActivitySummary(auth()->id(), $days);

        return response()->json($summary);
    }

    public function cleanupOldLogs(Request $request)
    {
        $days = $request->days ?? 90;
        $deleted = $this->auditService->cleanupOldLogs($days);

        return response()->json(['deleted' => $deleted]);
    }
}
