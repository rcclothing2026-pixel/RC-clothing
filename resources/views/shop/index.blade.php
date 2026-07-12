@extends('layouts.app')

@section('title', ($activeCollection->name ?? $activeCategory->name ?? null) ? (($activeCollection->name ?? $activeCategory->name).' | Racket Club') : 'Shop | Racket Club')

{{-- Legacy/fallback shop view. /shop normally renders through the page builder
     (ShopController → Page::provisionShop → the shop_products block), so admins
     can add blocks around the listing; this is the pre-migration fallback. Both
     paths render the same shop/_content partial. --}}
@section('content')
    @include('shop._content')
@endsection
