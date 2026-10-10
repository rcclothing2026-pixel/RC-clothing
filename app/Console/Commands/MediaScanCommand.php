<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaScanCommand extends Command
{
    protected $signature = 'media:scan {--disk=public : Disk to scan} {--dry-run : Only report, do not insert}';

    protected $description = 'Scan storage for untracked files and create Media records';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $dryRun = $this->option('dry-run');
        $storage = Storage::disk($disk);
        $root = $storage->path('');
        $created = 0;
        $skipped = 0;

        $known = Media::where('disk', $disk)
            ->get(['filename', 'directory'])
            ->mapWithKeys(fn ($m) => [($m->directory . '/' . $m->filename) => true]);

        $directories = ['/', 'products', 'categories', 'hero', 'pages', 'footer'];

        foreach ($directories as $dir) {
            if (!$storage->exists($dir)) {
                continue;
            }

            $files = $storage->files($dir);

            foreach ($files as $relativePath) {
                $filename = basename($relativePath);
                $directory = dirname($relativePath);
                $directory = $directory === '.' ? '/' : $directory;
                $key = ($directory ? $directory . '/' : '') . $filename;

                if ($known->has($key)) {
                    $skipped++;
                    continue;
                }

                $absolutePath = $root . '/' . $relativePath;

                if ($dryRun) {
                    $this->line("Would add: {$relativePath}");
                    continue;
                }

                $media = Media::createFromStoredFile($absolutePath, $relativePath, $directory, $disk);
                if ($media) {
                    $this->line("Added: {$media->original_name} ({$media->directory})");
                    $created++;
                }
            }
        }

        $this->info("Done. Created: {$created}, Skipped (already tracked): {$skipped}");

        return self::SUCCESS;
    }
}
