<?php

namespace App\Console\Commands;

use App\Services\SystemDiagnosticsService;
use Illuminate\Console\Command;

class SystemDiagnosticsRun extends Command
{
    protected $signature = 'diagnostics:run';

    protected $description = 'Run the read-only system health checks and store the snapshot for the dashboard trend';

    public function handle(SystemDiagnosticsService $diagnostics): int
    {
        $snapshot = $diagnostics->run();

        $this->info('score='.($snapshot['score'] ?? 'n/a').' critical='.($snapshot['totals']['critical'] ?? 0).' warning='.($snapshot['totals']['warning'] ?? 0));

        return self::SUCCESS;
    }
}
