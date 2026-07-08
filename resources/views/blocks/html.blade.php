{{--
    Raw HTML block. Whatever the admin writes is rendered verbatim (admin-only,
    trusted content — same {!! !!} trust model as the hero/rich_text blocks).
    Lets a page be authored fully in HTML from the page builder.
--}}
@php($max = match ($data['width'] ?? 'narrow') {
    'full' => 'max-w-none',
    'wide' => 'max-w-6xl',
    default => 'max-w-3xl',
})
@if (!empty($data['html']))
    <section class="mx-auto {{ $max }} px-4 py-12 sm:px-6">{!! $data['html'] !!}</section>
@endif
