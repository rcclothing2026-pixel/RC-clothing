@extends('layouts.app')

@section('title', ($page->seo_title ?: $page->title).' | Racket Club')
@php
    // An empty section would beat the layout default, so only set it when there is text.
    $__shopCat = ($activeCollection ?? null) ?: ($activeCategory ?? null);
    $__md = $page->seo_description
        ?: \Illuminate\Support\Str::limit(trim(strip_tags((string) ($__shopCat->description ?? ''))), 155, '')
        ?: ($__shopCat ? 'خرید آنلاین '.$__shopCat->name.' از راکت کلاب؛ تولید ایران، ارسال به سراسر کشور.' : '');
@endphp
@if ($__md !== '')
    @section('meta_description', $__md)
@endif

@section('content')
    @php
        // Per-page background — colour and/or full-bleed image behind all
        // blocks. Empty = the site's default paper background.
        $bgColor = trim((string) ($page->bg_color ?? ''));
        $bgImage = trim((string) ($page->bg_image ?? ''));
        $bgStyle = '';
        if ($bgColor !== '') $bgStyle .= 'background-color: '.$bgColor.';';
        if ($bgImage !== '') $bgStyle .= "background-image: url('".e($bgImage)."'); background-size: cover; background-position: center; background-attachment: fixed;";
    @endphp
    @if (request()->routeIs('home'))
        <h1 class="sr-only">{{ app()->getLocale() === 'fa' ? 'راکت کلاب؛ پوشاک و لوازم پدل و تنیس' : 'Racket Club: padel and tennis apparel and accessories, made in Iran' }}</h1>
    @endif
    <div @if ($bgStyle !== '') style="{{ $bgStyle }}" @endif>
        @foreach ($page->blockList() as $block)
            @include('blocks.render', ['block' => $block])
        @endforeach
    </div>
@endsection
