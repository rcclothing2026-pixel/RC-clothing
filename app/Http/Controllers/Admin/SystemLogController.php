<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Read-only-ish viewer for storage/logs/laravel.log — so the log can be read
 * (and downloaded/cleared) from the admin panel on hosts with no shell/cPanel
 * terminal. Only tails the file so a huge log never blows up memory.
 */
class SystemLogController extends Controller
{
    /** Bytes to read from the tail of the log (256 KB is plenty of recent errors). */
    private const TAIL_BYTES = 262144;

    private function logPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    public function index(): View
    {
        $path = $this->logPath();
        $exists = File::exists($path);
        $size = $exists ? File::size($path) : 0;

        $raw = '';
        if ($exists && $size > 0) {
            $fh = fopen($path, 'rb');
            if ($fh) {
                if ($size > self::TAIL_BYTES) {
                    fseek($fh, -self::TAIL_BYTES, SEEK_END);
                    fgets($fh); // drop the partial first line
                }
                $raw = stream_get_contents($fh);
                fclose($fh);
            }
        }

        // Split into entries on the leading "[YYYY-MM-DD ..]" timestamp, newest first.
        $entries = [];
        if ($raw !== '') {
            $parts = preg_split('/\n(?=\[\d{4}-\d{2}-\d{2}[ T])/', trim($raw));
            foreach (array_reverse($parts ?: []) as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }
                $level = 'info';
                if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $chunk)) {
                    $level = 'error';
                } elseif (preg_match('/\.(WARNING|NOTICE):/', $chunk)) {
                    $level = 'warning';
                }
                $entries[] = ['level' => $level, 'text' => $chunk];
            }
        }

        return view('admin.system-log.index', [
            'entries' => array_slice($entries, 0, 200),
            'exists' => $exists,
            'size' => $size,
            'truncated' => $exists && $size > self::TAIL_BYTES,
            'path' => $path,
        ]);
    }

    public function download(): Response
    {
        $path = $this->logPath();
        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laravel.log"',
        ]);
    }

    public function clear(): RedirectResponse
    {
        $path = $this->logPath();
        if (File::exists($path)) {
            File::put($path, '');
        }

        return redirect()->route('admin.system-log.index')->with('success', 'گزارش خطاها پاک شد.');
    }
}
