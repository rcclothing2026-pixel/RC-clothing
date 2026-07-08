<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill WebP variants for every image already uploaded to the public disk.
 * Idempotent — re-runs are cheap because we skip files whose .webp sibling
 * already exists. Use --force to regenerate everything (after changing
 * ImageOptimizer::QUALITY for instance).
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--dir=products : subdirectory under the public disk to walk} {--force : regenerate even when .webp already exists}';

    protected $description = 'Backfill WebP siblings (+ 600w/1200w) for existing product images.';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dir = trim($this->option('dir'), '/');
        $force = (bool) $this->option('force');

        $files = $disk->allFiles($dir);
        $ok = 0;
        $skip = 0;
        $fail = 0;

        $bar = $this->output->createProgressBar(count($files));
        foreach ($files as $rel) {
            // Don't try to re-optimize the .webp outputs themselves.
            if (str_ends_with($rel, '.webp')) {
                $bar->advance();
                continue;
            }
            $mime = mime_content_type($disk->path($rel)) ?: '';
            if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
                $bar->advance();
                continue;
            }
            if (! $force && $disk->exists($rel.'.webp')) {
                $skip++;
                $bar->advance();
                continue;
            }
            ImageOptimizer::optimize($rel) ? $ok++ : $fail++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Optimized: $ok · Skipped (already done): $skip · Failed: $fail");

        return self::SUCCESS;
    }
}
