@extends('layouts.app')

@section('title', ($page->seo_title ?: $page->title).' | چیاکو')
@section('meta_description', $page->seo_description ?? '')

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
    <div @if ($bgStyle !== '') style="{{ $bgStyle }}" @endif>
        @foreach ($page->blockList() as $block)
            @include('blocks.render', ['block' => $block])
        @endforeach
    </div>
@endsection
