{{-- Admin-chosen typography. Turns the font settings (admin → ظاهر فروشگاه →
     فونت‌ها) into @font-face + :root variable overrides, emitted in <head> AFTER
     the compiled CSS so it wins. The whole site already reads --font-sans (body)
     and --font-display (headings), so overriding those restyles everything at
     once. Nothing set ⇒ nothing emitted ⇒ byte-identical to the built-in look. --}}
@php
    // Re-strip defensively — this value is echoed into a <style> block.
    $famCss = fn ($f) => trim(preg_replace('/["\'\\\\{};<>\r\n\t]+/', '', (string) $f) ?? '');
    $fmt = fn ($url) => match (strtolower(pathinfo((string) parse_url((string) $url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
        'woff'  => 'woff',
        'ttf'   => 'truetype',
        'otf'   => 'opentype',
        default => 'woff2',
    };
    $mime = fn ($url) => match (strtolower(pathinfo((string) parse_url((string) $url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        default => 'font/woff2',
    };

    $bodyFam = $famCss($site['site.font_body_family'] ?? '');
    $bodyUrl = trim((string) ($site['site.font_body_url'] ?? ''));
    $headFam = $famCss($site['site.font_heading_family'] ?? '');
    $headUrl = trim((string) ($site['site.font_heading_url'] ?? ''));
    $basePx  = (int) ($site['site.font_base_px'] ?? 0);
    $basePx  = ($basePx >= 10 && $basePx <= 32) ? $basePx : 0; // 0 ⇒ no override
@endphp
{{-- Preload uploaded fonts so they're fetched at top priority — this, plus
     font-display:block below, stops the flash-of-fallback-then-swap (FOUT):
     text waits briefly for the real font instead of painting the default. --}}
@if ($bodyUrl !== '' && $bodyFam !== '')<link rel="preload" as="font" type="{{ $mime($bodyUrl) }}" href="{{ e($bodyUrl) }}" crossorigin>@endif
@if ($headUrl !== '' && $headFam !== '')<link rel="preload" as="font" type="{{ $mime($headUrl) }}" href="{{ e($headUrl) }}" crossorigin>@endif
@if ($bodyFam !== '' || $headFam !== '' || $basePx)
<style>
@if ($bodyUrl !== '' && $bodyFam !== '')
@font-face { font-family: "{{ $bodyFam }}"; src: url("{{ e($bodyUrl) }}") format("{{ $fmt($bodyUrl) }}"); font-display: block; }
@endif
@if ($headUrl !== '' && $headFam !== '')
@font-face { font-family: "{{ $headFam }}"; src: url("{{ e($headUrl) }}") format("{{ $fmt($headUrl) }}"); font-display: block; }
@endif
:root {
@if ($basePx) font-size: {{ $basePx }}px; @endif
@if ($bodyFam !== '') --font-sans: "{{ $bodyFam }}", ui-sans-serif, system-ui, sans-serif; @endif
@if ($headFam !== '') --font-display: "{{ $headFam }}", ui-sans-serif, system-ui, sans-serif; @endif
}
</style>
@endif
