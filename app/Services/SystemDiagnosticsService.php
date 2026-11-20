<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Live system diagnostics for the admin dashboard.
 *
 * Runs the same read-only checks that feed public/erd.html (scripts/erd/*.php,
 * SELECT queries only), folds them into one list of issues ranked by severity
 * and size, and keeps a short history of the score so a trend is visible.
 * Snapshots live in storage/app/diagnostics — no schema change needed.
 */
class SystemDiagnosticsService
{
    private const HISTORY_LIMIT = 90;

    /** script => [env var, section key, label]. */
    private const SCRIPTS = [
        'diagnose' => ['DIAG_OUT', 'state', 'سلامة البيانات'],
        'workflow' => ['WF_OUT', 'workflow', 'تدفق العمل'],
        'logic' => ['LOGIC_OUT', 'logic', 'المنطق والكلفة'],
    ];

    private const CATEGORY_LABELS = [
        'schema' => 'بنية القاعدة', 'integrity' => 'سلامة البيانات المرجعية', 'data' => 'قواعد العمل والأرصدة', 'ops' => 'التشغيل والأداء',
        'sales' => 'المبيعات', 'purchasing' => 'المشتريات', 'returns' => 'المرتجعات', 'ledger' => 'الدفاتر',
        'calc' => 'المعادلات والحسابات', 'costing' => 'الكلفة والهوامش', 'state' => 'الحالات والتسلسل الزمني',
        'reservation' => 'حجز المخزون', 'credit' => 'الائتمان والأرصدة',
    ];

    private const RANK = ['critical' => 4, 'error' => 3, 'warning' => 2, 'info' => 1, 'ok' => 0];

    private function dir(): string
    {
        $dir = storage_path('app/diagnostics');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /** The last run, or null when none has happened yet. */
    public function latest(): ?array
    {
        $file = $this->dir().'/latest.json';

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    /** Score history, oldest first. */
    public function history(): array
    {
        $file = $this->dir().'/history.json';

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    /**
     * Runs every check once. Guarded by a lock so two admins pressing the button
     * share one run instead of doubling the load on the database.
     */
    public function run(): array
    {
        $lock = Cache::lock('system-diagnostics-run', 180);
        if (! $lock->get()) {
            return $this->latest() ?? ['running' => true, 'issues' => [], 'sections' => [], 'score' => null];
        }

        try {
            @set_time_limit(170);
            $started = microtime(true);
            $issues = [];
            $sections = [];

            foreach (self::SCRIPTS as $script => [$env, $key, $label]) {
                $result = $this->runScript($script, $env);
                $sectionIssues = [];
                $counts = ['critical' => 0, 'warning' => 0, 'info' => 0, 'error' => 0, 'ok' => 0];

                foreach ($result['checks'] ?? [] as $check) {
                    $sev = $check['sev'] ?? 'ok';
                    $counts[$sev] = ($counts[$sev] ?? 0) + 1;
                    if ($sev === 'ok') {
                        continue;
                    }
                    $sectionIssues[] = [
                        'id' => $key.':'.$check['id'],
                        'section' => $key,
                        'category' => $check['cat'] ?? null,
                        'category_label' => self::CATEGORY_LABELS[$check['cat'] ?? ''] ?? ($check['cat'] ?? ''),
                        'severity' => $sev,
                        'title' => $check['title'] ?? $check['id'],
                        'why' => $check['why'] ?? '',
                        'fix' => $check['fix'] ?? '',
                        'count' => (int) ($check['count'] ?? 0),
                        'error' => $check['error'] ?? null,
                        'items' => array_slice($check['items'] ?? [], 0, 50),
                    ];
                }

                $sections[] = [
                    'key' => $key,
                    'label' => $label,
                    'score' => $result['score'] ?? null,
                    'total' => array_sum($counts),
                    'counts' => $counts,
                    'failed' => $result === null,
                ];
                $issues = array_merge($issues, $sectionIssues);
            }

            usort($issues, fn ($a, $b) => [self::RANK[$b['severity']] ?? 0, $b['count']] <=> [self::RANK[$a['severity']] ?? 0, $a['count']]);

            $totals = ['critical' => 0, 'warning' => 0, 'info' => 0, 'error' => 0];
            foreach ($issues as $i) {
                $totals[$i['severity']] = ($totals[$i['severity']] ?? 0) + 1;
            }

            $scores = array_values(array_filter(array_column($sections, 'score'), fn ($s) => $s !== null));
            // The overall figure is the weakest area pulled toward the average, so
            // one broken section is not hidden by two healthy ones.
            $overall = $scores ? (int) round((min($scores) * 0.5) + (array_sum($scores) / count($scores) * 0.5)) : null;

            $snapshot = [
                'generated_at' => now()->toIso8601String(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'score' => $overall,
                'totals' => $totals,
                'sections' => $sections,
                'issues' => $issues,
            ];

            $this->store($snapshot);

            return $snapshot + ['history' => $this->history()];
        } finally {
            $lock->release();
        }
    }

    private function store(array $snapshot): void
    {
        file_put_contents($this->dir().'/latest.json', json_encode($snapshot, JSON_UNESCAPED_UNICODE));

        $history = $this->history();
        $history[] = [
            'at' => $snapshot['generated_at'],
            'score' => $snapshot['score'],
            'totals' => $snapshot['totals'],
            'sections' => array_map(fn ($s) => ['key' => $s['key'], 'score' => $s['score']], $snapshot['sections']),
            'issue_ids' => array_map(fn ($i) => $i['id'], $snapshot['issues']),
        ];
        file_put_contents($this->dir().'/history.json', json_encode(array_slice($history, -self::HISTORY_LIMIT), JSON_UNESCAPED_UNICODE));
    }

    /** Includes one erd script in an isolated scope and reads back the JSON it writes. */
    private function runScript(string $script, string $env): ?array
    {
        $out = tempnam(sys_get_temp_dir(), 'diag_');

        try {
            putenv("$env=$out");
            ob_start();
            (static function (string $file): void {
                require $file;
            })(base_path("scripts/erd/$script.php"));
            ob_end_clean();

            $data = json_decode((string) file_get_contents($out), true);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            report($e);

            return null;
        } finally {
            putenv($env);
            @unlink($out);
        }
    }

    /** Flat rows for CSV export: one per affected record, falling back to one per issue. */
    public function exportRows(): array
    {
        $rows = [];
        foreach (($this->latest()['issues'] ?? []) as $issue) {
            $base = [$issue['severity'], $issue['category_label'], $issue['title'], $issue['count']];
            if ($issue['items'] === []) {
                $rows[] = array_merge($base, ['', '', $issue['fix']]);

                continue;
            }
            foreach ($issue['items'] as $item) {
                $rows[] = array_merge($base, [$item['table'] ?? '', $item['detail'] ?? '', $issue['fix']]);
            }
        }

        return $rows;
    }
}
