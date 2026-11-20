<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemDiagnosticsController extends Controller
{
    public function __construct(private readonly SystemDiagnosticsService $diagnostics) {}

    /** Last run plus the score history; null snapshot until the first run. */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ($this->diagnostics->latest() ?? ['issues' => [], 'sections' => [], 'score' => null, 'generated_at' => null])
                + ['history' => $this->diagnostics->history()],
        ]);
    }

    public function run(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->diagnostics->run()]);
    }

    public function export(): StreamedResponse
    {
        $rows = $this->diagnostics->exportRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads Arabic correctly
            fputcsv($out, ['الخطورة', 'القسم', 'الفحص', 'العدد', 'الجدول', 'التفصيل', 'الإصلاح المقترح']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'system-diagnostics-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
