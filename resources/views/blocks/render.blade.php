{{-- Dispatcher: renders a single block by type, ignoring unknown types.
     Honours the block-level `_v` visibility flag:
       all      → always rendered
       desktop  → wrapped in `hidden md:block` (visible on ≥md)
       mobile   → wrapped in `md:hidden` (visible on <md)
       auth     → only rendered for signed-in users
       guest    → only rendered for guests
     Auth visibility is enforced server-side so guest-only marketing CTAs
     don't leak into the HTML for logged-in users (and vice versa). --}}
@php($type = $block['type'] ?? null)
@php($vis  = (string) ($block['_v'] ?? 'all'))
@php($bid  = (string) ($block['_bid'] ?? ''))
@if ($type && view()->exists('blocks.'.$type))
    @if ($vis === 'auth' && ! auth()->check())
        {{-- skipped: requires sign-in --}}
    @elseif ($vis === 'guest' && auth()->check())
        {{-- skipped: visible to guests only --}}
    @else
        {{-- Stable id carrier for the canvas editor + universal style layer.
             display:contents when no _style overrides (byte-identical live
             layout); a real box with inline spacing/size/bg when set. --}}
        @php($wrapStyle = \App\Support\Blocks\BlockRegistry::wrapStyle($block['data'] ?? []))
        @php($wrapStyleM = $bid ? \App\Support\Blocks\BlockRegistry::wrapStyleMobile($block['data'] ?? []) : '')
        @if ($wrapStyleM !== '')
            <style>@media (max-width:767px){[data-bid="{{ $bid }}"]{ {!! $wrapStyleM !!} }}</style>
        @endif
        @php($typo = $bid ? \App\Support\Blocks\BlockRegistry::typographyStyle($block['data'] ?? []) : '')
        @if ($typo !== '')
            <style>[data-bid="{{ $bid }}"] :is(h1,h2,h3,h4,h5,h6,p,li){ {!! $typo !!} }</style>
        @endif
        @php($typoM = $bid ? \App\Support\Blocks\BlockRegistry::typographyStyleMobile($block['data'] ?? []) : '')
        @if ($typoM !== '')
            <style>@media (max-width:767px){[data-bid="{{ $bid }}"] :is(h1,h2,h3,h4,h5,h6,p,li){ {!! $typoM !!} }}</style>
        @endif
        <div @if ($bid) data-bid="{{ $bid }}" @endif style="{{ $wrapStyle }}">
            @if ($vis === 'desktop')
                <div class="hidden md:block">@include('blocks.'.$type, ['data' => (array) ($block['data'] ?? [])])</div>
            @elseif ($vis === 'mobile')
                <div class="md:hidden">@include('blocks.'.$type, ['data' => (array) ($block['data'] ?? [])])</div>
            @else
                @include('blocks.'.$type, ['data' => (array) ($block['data'] ?? [])])
            @endif
        </div>
    @endif
@endif
