<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lightweight GD-based image pipeline. Generates WebP siblings for an
 * uploaded image at three widths (original, 1200, 600) so the front-end
 * can serve responsive srcsets. No external dependencies — everything is
 * stdlib (intentional, since the Iran host can't always pull composer
 * packages). All errors are swallowed: a bad image must never fail the
 * upload itself.
 *
 * Naming convention next to a `foo.jpg` source:
 *   foo.jpg.webp          ← full-size WebP, same dimensions
 *   foo.jpg.1200.webp     ← max-width 1200px WebP
 *   foo.jpg.600.webp      ← max-width 600px WebP
 */
class ImageOptimizer
{
    public const WIDTHS = [600, 1200];

    public const QUALITY = 82;

    /**
     * Generate the WebP variants for a stored file path on the public disk.
     * Returns true on full success, false on any failure (logged).
     */
    public static function optimize(string $relativePath, string $disk = 'public'): bool
    {
        if (! function_exists('imagewebp')) {
            return false; // GD without WebP — opt out silently
        }
        try {
            $abs = Storage::disk($disk)->path($relativePath);
            if (! is_file($abs) || ! is_readable($abs)) {
                return false;
            }
            $src = self::loadGd($abs);
            if ($src === null) {
                return false;
            }

            // Full-size .webp sibling
            imagewebp($src, $abs.'.webp', self::QUALITY);

            // Downscaled variants
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            foreach (self::WIDTHS as $w) {
                if ($srcW <= $w) {
                    continue;
                }
                $h = (int) round($srcH * ($w / $srcW));
                $dst = imagecreatetruecolor($w, $h);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
                imagewebp($dst, $abs.'.'.$w.'.webp', self::QUALITY);
                imagedestroy($dst);
            }
            imagedestroy($src);

            // Bust the per-image existence cache so subsequent renders pick
            // up the freshly written variants without a server restart.
            Cache::forget(self::cacheKey($relativePath));

            return true;
        } catch (Throwable $e) {
            Log::warning('[image-optimizer] failed', ['path' => $relativePath, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Given a public URL (e.g. /storage/products/foo.jpg), return a
     * Tailwind-friendly srcset string when WebP variants exist on disk,
     * or null otherwise. The result is cached for a day per image — the
     * filesystem checks are cheap individually but a 24-card listing
     * would otherwise hit 96 stats per request.
     */
    public static function srcsetFor(string $url): ?string
    {
        $rel = self::urlToRelativePath($url);
        if ($rel === null) {
            return null;
        }

        return Cache::remember(self::cacheKey($rel), now()->addDay(), function () use ($rel, $url) {
            $disk = Storage::disk('public');
            $parts = [];
            foreach (self::WIDTHS as $w) {
                if ($disk->exists($rel.'.'.$w.'.webp')) {
                    $parts[] = $disk->url($rel.'.'.$w.'.webp').' '.$w.'w';
                }
            }
            if ($disk->exists($rel.'.webp')) {
                $parts[] = $disk->url($rel.'.webp').' 1600w';
            }

            return $parts ? implode(', ', $parts) : null;
        });
    }

    /** Return the full-size .webp URL if it exists, or null. */
    public static function webpUrl(string $url): ?string
    {
        $rel = self::urlToRelativePath($url);
        if ($rel === null) {
            return null;
        }
        $disk = Storage::disk('public');
        if ($disk->exists($rel.'.webp')) {
            return $disk->url($rel.'.webp');
        }

        return null;
    }

    /**
     * Best small thumbnail URL for a stored image URL: the width-$w WebP
     * variant if it exists, else the full-size WebP, else the original URL
     * unchanged. Used by the StoqS thumbnail API so the POS pulls a ~600w
     * WebP instead of the full-res original.
     */
    public static function thumbUrl(string $url, int $w = 600): string
    {
        $rel = self::urlToRelativePath($url);
        if ($rel !== null) {
            $disk = Storage::disk('public');
            if ($disk->exists($rel.'.'.$w.'.webp')) {
                return $disk->url($rel.'.'.$w.'.webp');
            }
            if ($disk->exists($rel.'.webp')) {
                return $disk->url($rel.'.webp');
            }
        }

        return $url;
    }

    private static function loadGd(string $abs): ?\GdImage
    {
        $mime = mime_content_type($abs) ?: '';
        $img = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($abs),
            'image/png' => @imagecreatefrompng($abs),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : null,
            default => null,
        };

        return $img ?: null;
    }

    /** Translate a public URL (or absolute path) into a disk-relative path. */
    private static function urlToRelativePath(string $url): ?string
    {
        if ($url === '' || str_starts_with($url, 'data:')) {
            return null;
        }
        // Strip scheme+host so http(s)://site/storage/x.jpg → /storage/x.jpg
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $prefix = '/storage/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        return substr($path, strlen($prefix));
    }

    private static function cacheKey(string $rel): string
    {
        return 'image-opt:srcset:'.md5($rel);
    }
}
