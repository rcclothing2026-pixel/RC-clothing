<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Chiaco Hero banner — config + colour helpers. Mirrors the reference design
 * (BrandBook v01): accent palette red/dark/pink with soft 3D gradients. The
 * whole hero config lives in a single Setting JSON ('hero.config') and is
 * edited in the dedicated Hero admin.
 */
class Hero
{
    public const ACCENTS = ['red', 'dark', 'pink'];

    /** Per-slide transition styles. */
    public const MOTIONS = ['drift', 'slide', 'fade', 'zoom'];

    /** accent name => flat hex */
    public static function hex(string $name): string
    {
        return ['red' => '#CC3333', 'dark' => '#282828', 'pink' => '#E0D5D9'][$name] ?? '#CC3333';
    }

    /** accent name => soft 3D radial gradient (floaters / tiles / hero shape) */
    public static function grad(string $name): string
    {
        return [
            'red' => 'radial-gradient(circle at 38% 30%, #ef6a5e 0%, #CC3333 52%, #8f2222 100%)',
            'dark' => 'radial-gradient(circle at 38% 30%, #5a5a5a 0%, #282828 55%, #050505 100%)',
            'pink' => 'radial-gradient(circle at 38% 30%, #f3ecef 0%, #E0D5D9 55%, #c2afb6 100%)',
        ][$name] ?? self::grad('red');
    }

    /** Generate a soft 3D radial gradient from any 6-digit hex colour. */
    public static function gradFromHex(string $hex): string
    {
        $hex = '#'.ltrim($hex, '#');
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return self::grad('red');
        }
        $light = self::mix($hex, '#ffffff', 0.35);
        $dark  = self::mix($hex, '#000000', 0.25);

        return "radial-gradient(circle at 38% 30%, {$light} 0%, {$hex} 52%, {$dark} 100%)";
    }

    /** Mix two hex colours by $pct (0..1). */
    public static function mix(string $hex, string $with, float $pct): string
    {
        $hex = ltrim($hex, '#');
        $with = ltrim($with, '#');
        $a = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $b = [hexdec(substr($with, 0, 2)), hexdec(substr($with, 2, 2)), hexdec(substr($with, 4, 2))];
        $r = [];
        foreach ($a as $i => $v) {
            $r[] = (int) round($v + ($b[$i] - $v) * $pct);
        }

        return sprintf('#%02x%02x%02x', $r[0], $r[1], $r[2]);
    }

    /** The full hero config (stored config merged over defaults). */
    public static function config(): array
    {
        $stored = Setting::get('hero.config');
        if (! is_array($stored) || empty($stored['slides'])) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $stored);
    }

    public static function save(array $config): void
    {
        Setting::put('hero.config', $config);
    }

    /** Default hero config (graphics identical to the reference; Persian copy). */
    public static function defaults(): array
    {
        return [
            'version' => 1,
            'storeTitle' => 'چیاکو',
            'brandLine' => 'فروشگاهِ',
            'rotationSeconds' => 5,
            'animationSpeedMs' => 800,
            'autoplay' => true,
            'pauseOnHover' => true,
            'logoLink' => '/',
            'avatarLink' => '/account',
            'bgPattern' => 'none', // none | gate | quatrefoil — brand pattern behind the hero
            // Bottom navigator (floating pill): a link per icon.
            'toolbar' => ['sizes' => '#', 'edit' => '#', 'account' => '/account', 'cta' => ''],
            'slides' => [
                [
                    'id' => 's1', 'headline' => "کالکشن\nجدید", 'accent' => 'red', 'bg_color' => '', 'motion' => 'drift', 'ctaLink' => '/shop', 'heroImg' => '', 'hero_top' => 5, 'hero_left' => 24, 'hero_width' => 400,
                    'floaters' => [
                        ['id' => 's1f1', 'color' => 'dark', 'color_hex' => '', 'link' => '#', 'top' => 6,  'left' => 30, 'size' => 70, 'blur' => 1.2],
                        ['id' => 's1f2', 'color' => 'pink', 'color_hex' => '', 'link' => '#', 'top' => 44, 'left' => 16, 'size' => 96, 'blur' => 1.0],
                        ['id' => 's1f3', 'color' => 'red',  'color_hex' => '', 'link' => '#', 'top' => 12, 'left' => 8,  'size' => 34, 'blur' => 0.6],
                    ],
                    'products' => [
                        ['id' => 's1p1', 'name' => 'مانتو جدید', 'dims' => '۱۱۰ × ۱۱۰', 'no' => '۰۱', 'color' => 'red', 'img' => '', 'link' => '/shop'],
                        ['id' => 's1p2', 'name' => 'شال و روسری', 'dims' => '۱۱۰ × ۱۱۰', 'no' => '۰۲', 'color' => 'dark', 'img' => '', 'link' => '/shop'],
                        ['id' => 's1p3', 'name' => 'کیف چرم', 'dims' => '۱۱۰ × ۱۱۰', 'no' => '۰۳', 'color' => 'pink', 'img' => '', 'link' => '/shop'],
                    ],
                ],
                [
                    'id' => 's2', 'headline' => "مینیمالِ\nلاکچری", 'accent' => 'dark', 'bg_color' => '', 'motion' => 'slide', 'ctaLink' => '/shop', 'heroImg' => '', 'hero_top' => 5, 'hero_left' => 24, 'hero_width' => 400,
                    'floaters' => [
                        ['id' => 's2f1', 'color' => 'red',  'color_hex' => '', 'link' => '#', 'top' => 8,  'left' => 28, 'size' => 80, 'blur' => 1.4],
                        ['id' => 's2f2', 'color' => 'pink', 'color_hex' => '', 'link' => '#', 'top' => 50, 'left' => 12, 'size' => 56, 'blur' => 0.8],
                        ['id' => 's2f3', 'color' => 'dark', 'color_hex' => '', 'link' => '#', 'top' => 20, 'left' => 6,  'size' => 40, 'blur' => 0.5],
                    ],
                    'products' => [
                        ['id' => 's2p1', 'name' => 'پیراهن ابریشمی', 'dims' => '۱۱۰ × ۱۱۰', 'no' => '۰۱', 'color' => 'dark', 'img' => '', 'link' => '/shop'],
                        ['id' => 's2p2', 'name' => 'شلوار کتان', 'dims' => '۱۱۰ × ۱۱۰', 'no' => '۰۲', 'color' => 'red', 'img' => '', 'link' => '/shop'],
                        ['id' => 's2p3', 'name' => 'کفش جیر', 'dims' => '۱۱۰ × ۱۱۰', 'no' => '۰۳', 'color' => 'pink', 'img' => '', 'link' => '/shop'],
                    ],
                ],
            ],
        ];
    }
}
