<?php

namespace App\Console\Commands;

use App\Support\ImageStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sweep uploaded images that nothing points at any more.
 *
 * Uploads land on disk the moment a file is picked, so an admin who uploads a
 * picture and then closes the form without saving leaves it behind — and until
 * ImageStore was wired in, so did every replaced main image, every photo
 * dropped from a gallery, and every deleted product. This clears the backlog
 * and keeps catching the abandoned-form case, which no request can see.
 *
 * Only the directories this application writes to are considered
 * (ImageStore::MANAGED_PREFIXES); the shipped catalogue art under
 * public/images_items and public/assets is never touched.
 *
 * Reports by default and deletes nothing: this removes files permanently, so
 * the destructive run has to be asked for by name with --force.
 */
class ImagesPrune extends Command
{
    protected $signature = 'images:prune
        {--force : Actually delete the files instead of only listing them}
        {--days=7 : Leave files newer than this alone, in case a form is still open}';

    protected $description = 'Delete uploaded images that no product, category, offer, order or setting refers to';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->getTimestamp();
        $force = (bool) $this->option('force');
        $disk = Storage::disk('public');

        $candidates = [];
        $scanned = 0;

        foreach (ImageStore::MANAGED_PREFIXES as $prefix) {
            $directory = rtrim($prefix, '/');

            if (! $disk->directoryExists($directory)) {
                continue;
            }

            foreach ($disk->allFiles($directory) as $path) {
                // Dotfiles are housekeeping (.gitignore and friends), not images.
                if (Str::startsWith(basename($path), '.')) {
                    continue;
                }

                $scanned++;

                try {
                    // A file uploaded minutes ago may belong to a form that is
                    // still open, and no row points at it yet.
                    if ($disk->lastModified($path) > $cutoff) {
                        continue;
                    }

                    $candidates[] = $path;
                } catch (Throwable $e) {
                    $this->warn("Skipped {$path}: {$e->getMessage()}");
                }
            }
        }

        // Asked as one batch: answering costs a pass over the gallery columns
        // however many paths are in the question.
        $orphans = ImageStore::unreferenced($candidates);
        $bytes = 0;

        foreach ($orphans as $path) {
            try {
                $bytes += $disk->size($path);
            } catch (Throwable $e) {
                $this->warn("Skipped {$path}: {$e->getMessage()}");
            }
        }

        if ($orphans === []) {
            $this->info("Scanned {$scanned} file(s); nothing to prune.");

            return self::SUCCESS;
        }

        $size = number_format($bytes / 1048576, 2);
        $this->line("Scanned {$scanned} file(s); ".count($orphans)." unreferenced ({$size} MB):");

        foreach ($orphans as $path) {
            $this->line('  '.$path);
        }

        if (! $force) {
            $this->newLine();
            $this->comment('Nothing deleted. Re-run with --force to remove them.');

            return self::SUCCESS;
        }

        // Re-checked rather than deleted straight from the listing above: that
        // pass can take a while on a large disk, and a product saved in the
        // meantime may have claimed one of these files.
        $deleted = ImageStore::forget($orphans);

        $this->info("Deleted {$deleted} file(s).");

        return self::SUCCESS;
    }
}
