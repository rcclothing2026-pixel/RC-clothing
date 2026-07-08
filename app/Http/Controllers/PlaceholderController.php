<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Generates lightweight inline SVG placeholder imagery so the storefront looks
 * complete without external image CDNs (which can be blocked in Iran). These
 * are replaced by real product photos uploaded via the admin.
 */
class PlaceholderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $w = (int) min(2000, max(100, $request->integer('w', 800)));
        $h = (int) min(2600, max(100, $request->integer('h', 1000)));
        $label = mb_substr((string) $request->string('label', 'Chiiaco'), 0, 40);
        $seed = crc32((string) $request->string('seed', $label));

        // Derive two pleasant brand-ish hues from the seed.
        $hue = $seed % 360;
        $c1 = "hsl($hue,18%,82%)";
        $c2 = 'hsl('.(($hue + 40) % 360).',22%,68%)';
        $text = "hsl($hue,20%,32%)";

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="$w" height="$h" viewBox="0 0 $w $h">
              <defs>
                <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0" stop-color="$c1"/>
                  <stop offset="1" stop-color="$c2"/>
                </linearGradient>
              </defs>
              <rect width="$w" height="$h" fill="url(#g)"/>
              <text x="50%" y="50%" fill="$text" font-family="Vazirmatn, sans-serif"
                    font-size="34" font-weight="600" text-anchor="middle" dominant-baseline="middle"
                    direction="rtl">$label</text>
            </svg>
            SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
