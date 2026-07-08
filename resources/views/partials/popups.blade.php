{{-- Active pop-ups serialised for the storefront engine (see app.js). --}}
@php($popups = \App\Models\Popup::activePayload())
@if ($popups)
    <script type="application/json" id="popups-data" data-is-home="{{ request()->routeIs('home') ? '1' : '0' }}">{!! json_encode($popups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif
