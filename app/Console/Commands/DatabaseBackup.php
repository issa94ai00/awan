<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Dumps the database to a gzipped file in storage/app/backups and prunes
 * dumps past the retention window.
 *
 * Credentials come from .mysql_backup.cnf (mode 600, owned by the PHP-FPM
 * pool user) rather than argv: `ps` on a shared box shows every process's
 * command line to any local user, and argv is not a place for a password.
 */
class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--keep=14 : Days of backups to retain}';

    protected $description = 'Dump the database to storage/app/backups and prune backups older than the retention window';

    public function handle(): int
    {
        $database = config('database.connections.mysql.database');
        $credentialsFile = base_path('.mysql_backup.cnf');

        if (! is_readable($credentialsFile)) {
            $this->error("Backup credentials file not found or unreadable: {$credentialsFile}");

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);

        $stamp = now()->format('Ymd_His');
        $sqlPath = "{$dir}/{$database}_{$stamp}.sql";
        $gzPath = "{$sqlPath}.gz";

        $dumpCommand = sprintf(
            'mysqldump --defaults-extra-file=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($credentialsFile),
            escapeshellarg($database),
            escapeshellarg($sqlPath)
        );

        $process = Process::fromShellCommandline($dumpCommand);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! File::exists($sqlPath) || File::size($sqlPath) === 0) {
            @unlink($sqlPath);
            $this->error('Backup failed: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        $this->gzip($sqlPath, $gzPath);
        unlink($sqlPath);
        chmod($gzPath, 0640);

        $this->info(sprintf('Backup written: %s (%s KB)', basename($gzPath), number_format(File::size($gzPath) / 1024, 1)));

        $this->prune($dir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function gzip(string $sourcePath, string $destPath): void
    {
        $in = fopen($sourcePath, 'rb');
        $out = gzopen($destPath, 'wb9');

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
    }

    private function prune(string $dir, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->timestamp;
        $removed = 0;

        foreach (File::files($dir) as $file) {
            if (str_ends_with($file->getFilename(), '.sql.gz') && File::lastModified($file->getPathname()) < $cutoff) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("Pruned {$removed} backup(s) older than {$keepDays} days.");
        }
    }
}
