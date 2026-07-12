{{-- The whole shop as a page-builder block: filter sidebar + product grid +
     live AJAX filtering. Renders the shared shop content, which needs the shop
     data ShopController provides ($products, $categories, …). When that data
     isn't present — e.g. the admin page-editor previewing this block on its own,
     or the block dropped on a non-shop page — it shows a placeholder instead of
     erroring. The real listing only renders on /shop. --}}
@isset($products)
    @include('shop._content')
@else
    <div class="mx-auto max-w-7xl px-4 py-16 text-center text-sm text-brand-400">
        لیست محصولات فروشگاه اینجا نمایش داده می‌شود (فقط در صفحهٔ فروشگاه فعال است).
    </div>
@endisset
