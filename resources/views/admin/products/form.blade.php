@extends('admin.layout')

@section('title', $product->exists ? 'ویرایش محصول' : 'محصول جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $product->exists ? 'ویرایش محصول' : 'محصول جدید' }}</h1>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-brand-500 hover:underline">→ بازگشت</a>
    </div>

    <form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
          method="POST" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[1fr_320px]">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="space-y-6">
            {{-- Core --}}
            <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">اطلاعات اصلی</h2>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm text-brand-600">نام محصول</label>
                        <input name="name" value="{{ old('name', $product->name) }}" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-brand-600">خلاصه</label>
                        <input name="summary" value="{{ old('summary', $product->summary) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-brand-600">توضیحات</label>
                        @include('admin.partials.html-editor', ['name' => 'description', 'value' => old('description', $product->description), 'rows' => 14])
                    </div>
                </div>
            </section>

            {{-- Variants / inventory (hidden when the product is a gift bundle) --}}
            <section data-variants-section class="rounded-card bg-white p-6 ring-1 ring-brand-100 {{ old('is_bundle', $product->is_bundle) ? 'hidden' : '' }}">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-brand-900">تنوع‌ها و موجودی</h2>
                    <button type="button" data-add-variant class="rounded-lg bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700">+ افزودن دستی</button>
                </div>

                {{-- Matrix builder: colours × sizes → one variant (with its own barcode) per cell --}}
                <div class="mb-4 rounded-lg border border-dashed border-brand-200 bg-brand-50/50 p-4">
                    <p class="mb-3 text-xs text-brand-500">رنگ‌ها و سایزها را وارد کنید تا برای هر ترکیب یک تنوع ساخته شود (مثلاً ۲ رنگ × ۳ سایز = ۶ تنوع). بارکد هر تنوع را از StoqS وارد کنید یا با «وارد کردن از StoqS» خودکار پر می‌شود.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs text-brand-600">رنگ‌ها (با ویرگول؛ اختیاری: نام:کدرنگ)</label>
                            <input data-matrix-colors placeholder="آبی:#1e40af، قرمز:#dc2626" class="w-full rounded-lg border border-brand-200 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-brand-600">سایزها (با ویرگول)</label>
                            <input data-matrix-sizes placeholder="S، M، L" class="w-full rounded-lg border border-brand-200 px-2 py-1.5 text-sm">
                        </div>
                    </div>
                    <button type="button" data-matrix-generate class="mt-3 rounded-lg bg-brand-900 px-4 py-1.5 text-xs font-semibold text-white">ساخت جدول تنوع</button>
                    <span data-matrix-note class="ms-2 text-xs text-brand-400"></span>
                </div>

                <div class="grid grid-cols-12 gap-2 px-1 pb-1 text-[11px] text-brand-400">
                    <span class="col-span-2">سایز</span><span class="col-span-2">رنگ</span><span class="col-span-1">کد رنگ</span>
                    <span class="col-span-3">بارکد (SKU)</span><span class="col-span-2">موجودی</span><span class="col-span-1">فعال</span><span class="col-span-1">حذف</span>
                </div>
                <div class="space-y-2" data-variant-list>
                    @foreach ($product->variants as $i => $variant)
                        <div class="grid grid-cols-12 items-center gap-2" data-variant-row>
                            <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $variant->id }}">
                            <input type="hidden" name="variants[{{ $i }}][stockkeeping_variant_id]" value="{{ $variant->stockkeeping_variant_id }}">
                            <input name="variants[{{ $i }}][size]" value="{{ $variant->size }}" placeholder="سایز" class="col-span-2 rounded-lg border border-brand-200 px-2 py-1.5 text-sm">
                            <input name="variants[{{ $i }}][color]" value="{{ $variant->color }}" placeholder="رنگ" class="col-span-2 rounded-lg border border-brand-200 px-2 py-1.5 text-sm">
                            <input name="variants[{{ $i }}][color_hex]" value="{{ $variant->color_hex }}" placeholder="#" dir="ltr" class="col-span-1 rounded-lg border border-brand-200 px-1 py-1.5 text-xs">
                            <input name="variants[{{ $i }}][sku]" value="{{ $variant->sku }}" placeholder="بارکد" dir="ltr" class="col-span-3 rounded-lg border border-brand-200 px-2 py-1.5 text-xs">
                            <input name="variants[{{ $i }}][stock_qty]" value="{{ $variant->stock_qty }}" type="number" class="col-span-2 rounded-lg border border-brand-200 px-2 py-1.5 text-sm fa-num">
                            <label class="col-span-1 flex items-center justify-center"><input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" @checked($variant->is_active)></label>
                            <label class="col-span-1 flex items-center justify-center"><input type="checkbox" name="variants[{{ $i }}][_delete]" value="1" title="حذف" class="accent-red-500"></label>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-brand-400">بارکد هر تنوع باید با بارکد همان تنوع در StoqS یکی باشد تا موجودی متناظر شود.</p>

                {{-- Template for new rows --}}
                <template data-variant-template>
                    <div class="grid grid-cols-12 items-center gap-2" data-variant-row>
                        <input name="variants[__I__][size]" placeholder="سایز" class="col-span-2 rounded-lg border border-brand-200 px-2 py-1.5 text-sm">
                        <input name="variants[__I__][color]" placeholder="رنگ" class="col-span-2 rounded-lg border border-brand-200 px-2 py-1.5 text-sm">
                        <input name="variants[__I__][color_hex]" placeholder="#" dir="ltr" class="col-span-1 rounded-lg border border-brand-200 px-1 py-1.5 text-xs">
                        <input name="variants[__I__][sku]" placeholder="بارکد" dir="ltr" class="col-span-3 rounded-lg border border-brand-200 px-2 py-1.5 text-xs">
                        <input name="variants[__I__][stock_qty]" type="number" value="0" class="col-span-2 rounded-lg border border-brand-200 px-2 py-1.5 text-sm fa-num">
                        <label class="col-span-1 flex items-center justify-center"><input type="checkbox" name="variants[__I__][is_active]" value="1" checked></label>
                        <button type="button" data-remove-row class="col-span-1 text-red-400 hover:text-red-600">✕</button>
                    </div>
                </template>
            </section>

            {{-- Bundle items: only shown when «پک هدیه» is on. The picker lists every
                 variant of every non-bundle product. Saved rows are emitted with
                 hidden inputs so the form posts them as bundle_items[][variant_id],
                 [quantity]. --}}
            <section data-bundle-section class="rounded-card bg-white p-6 ring-1 ring-brand-100 {{ old('is_bundle', $product->is_bundle) ? '' : 'hidden' }}">
                <h2 class="mb-2 text-base font-bold text-brand-900">اقلام موجود در پک هدیه</h2>
                <p class="mb-4 text-xs text-brand-500">یک یا چند تنوع از محصولات دیگر را به این پک اضافه کنید. موجودی پک به‌صورت خودکار از کمترین موجودی اقلام درون آن محاسبه می‌شود.</p>

                <div class="mb-3 flex gap-2">
                    <input type="text" data-bundle-search placeholder="جستجو نام یا بارکد..." autocomplete="off"
                           class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
                <div data-bundle-options class="mb-4 max-h-48 overflow-auto rounded-lg border border-brand-100 bg-brand-50/40">
                    @foreach ($bundleCandidates as $cand)
                        <button type="button" class="block w-full px-3 py-2 text-right text-sm text-brand-700 hover:bg-brand-100"
                                data-bundle-add data-id="{{ $cand['id'] }}" data-label="{{ $cand['label'] }}">{{ $cand['label'] }}</button>
                    @endforeach
                    @if ($bundleCandidates->isEmpty())
                        <p class="px-3 py-2 text-sm text-brand-400">هنوز تنوع قابل افزودنی وجود ندارد. ابتدا چند محصول معمولی بسازید.</p>
                    @endif
                </div>

                <div data-bundle-rows class="space-y-2">
                    @foreach (old('bundle_items', $product->bundleItems->map(fn ($bi) => ['variant_id' => $bi->product_variant_id, 'quantity' => $bi->quantity, 'label' => trim($bi->variant?->product?->name.' · '.($bi->variant?->size ?: '—').($bi->variant?->color ? ' · '.$bi->variant->color : '').($bi->variant?->sku ? ' · '.$bi->variant->sku : ''))])->all()) as $i => $row)
                        <div class="grid grid-cols-12 items-center gap-2" data-bundle-row>
                            <input type="hidden" name="bundle_items[{{ $i }}][variant_id]" value="{{ $row['variant_id'] }}">
                            <span class="col-span-8 truncate rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700">{{ $row['label'] ?? 'تنوع #'.$row['variant_id'] }}</span>
                            <label class="col-span-3 flex items-center gap-2 text-xs text-brand-500">تعداد:<input type="number" name="bundle_items[{{ $i }}][quantity]" value="{{ $row['quantity'] }}" min="1" max="99" class="w-full rounded-lg border border-brand-200 px-2 py-1.5 text-sm fa-num"></label>
                            <button type="button" data-bundle-remove class="col-span-1 text-red-400 hover:text-red-600">✕</button>
                        </div>
                    @endforeach
                </div>

                <template data-bundle-template>
                    <div class="grid grid-cols-12 items-center gap-2" data-bundle-row>
                        <input type="hidden" name="bundle_items[__I__][variant_id]" value="__VID__">
                        <span class="col-span-8 truncate rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700">__LABEL__</span>
                        <label class="col-span-3 flex items-center gap-2 text-xs text-brand-500">تعداد:<input type="number" name="bundle_items[__I__][quantity]" value="1" min="1" max="99" class="w-full rounded-lg border border-brand-200 px-2 py-1.5 text-sm fa-num"></label>
                        <button type="button" data-bundle-remove class="col-span-1 text-red-400 hover:text-red-600">✕</button>
                    </div>
                </template>
            </section>

            {{-- Images --}}
            <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-1 text-base font-bold text-brand-900">تصاویر</h2>
                <p class="mb-4 text-xs text-brand-400">ترتیب نمایش در صفحهٔ محصول از همین چیدمان است. برای جابه‌جایی، تصویر را بکشید و رها کنید. تصویر اول به‌طور خودکار تصویر «اصلی» (شاخص) محصول می‌شود.</p>

                @if ($product->images->isNotEmpty())
                    <input type="hidden" name="image_order" id="image-order" value="{{ $product->images->pluck('id')->join(',') }}">
                    <div id="img-grid" class="mb-4 grid grid-cols-4 gap-3">
                        @foreach ($product->images as $image)
                            <div class="img-item group relative cursor-move rounded-lg ring-1 ring-brand-100" draggable="true" data-id="{{ $image->id }}">
                                <span class="order-badge pointer-events-none absolute start-1 top-1 z-10 grid h-5 w-5 place-items-center rounded-full bg-brand-900/85 text-[10px] font-bold text-white">{{ $loop->iteration }}</span>
                                <span class="primary-tag pointer-events-none absolute end-1 top-1 z-10 rounded-full bg-accent-600 px-2 py-0.5 text-[10px] font-bold text-white {{ $loop->first ? '' : 'hidden' }}">اصلی</span>
                                <img src="{{ $image->path }}" class="pointer-events-none aspect-square w-full rounded-lg object-cover" alt="">
                                <label class="flex items-center justify-center gap-1 px-1 py-1 text-xs text-red-400">
                                    <input type="checkbox" name="delete_images[]" value="{{ $image->id }}"> حذف
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif

                <input type="file" name="images[]" id="img-input" accept="image/*" multiple class="block w-full text-sm text-brand-500 file:me-3 file:rounded-lg file:border-0 file:bg-brand-100 file:px-4 file:py-2 file:text-sm file:text-brand-700">
                <p class="mt-1 text-xs text-brand-400">می‌توانید چند تصویر همزمان آپلود کنید (حداکثر ۴ مگابایت). پس از ذخیره به فهرست بالا اضافه می‌شوند.</p>
                <div id="img-preview" class="mt-3 hidden grid-cols-4 gap-3"></div>
            </section>

            @push('scripts')
            <script>
            (function () {
                // --- live preview of newly picked files ---
                const input = document.getElementById('img-input');
                const preview = document.getElementById('img-preview');
                if (input && preview) {
                    input.addEventListener('change', function () {
                        preview.innerHTML = '';
                        const files = [...(input.files || [])].filter(f => f.type.startsWith('image/'));
                        preview.classList.toggle('hidden', files.length === 0);
                        preview.classList.toggle('grid', files.length > 0);
                        files.forEach(function (f) {
                            const url = URL.createObjectURL(f);
                            const d = document.createElement('div');
                            d.className = 'relative';
                            d.innerHTML = '<img src="' + url + '" class="aspect-square w-full rounded-lg object-cover ring-1 ring-brand-100">'
                                + '<span class="absolute bottom-1 start-1 rounded bg-green-600/90 px-1.5 py-0.5 text-[10px] font-bold text-white">جدید</span>';
                            preview.appendChild(d);
                        });
                    });
                }

                // --- drag-to-reorder existing images ---
                const grid = document.getElementById('img-grid');
                const orderInput = document.getElementById('image-order');
                if (grid && orderInput) {
                    let dragEl = null;

                    const sync = function () {
                        const items = [...grid.querySelectorAll('.img-item')];
                        orderInput.value = items.map(el => el.dataset.id).join(',');
                        items.forEach((el, i) => {
                            const b = el.querySelector('.order-badge');
                            if (b) b.textContent = i + 1;
                            const p = el.querySelector('.primary-tag');
                            if (p) p.classList.toggle('hidden', i !== 0);
                        });
                    };

                    const closestTo = function (x, y) {
                        let best = { d: Infinity, el: null };
                        grid.querySelectorAll('.img-item:not(.dragging)').forEach(function (el) {
                            const r = el.getBoundingClientRect();
                            const d = Math.hypot(x - (r.left + r.width / 2), y - (r.top + r.height / 2));
                            if (d < best.d) best = { d, el };
                        });
                        return best.el;
                    };

                    grid.querySelectorAll('.img-item').forEach(function (item) {
                        item.addEventListener('dragstart', function () { dragEl = item; item.classList.add('dragging', 'opacity-40'); });
                        item.addEventListener('dragend', function () { item.classList.remove('dragging', 'opacity-40'); sync(); });
                    });

                    grid.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        if (!dragEl) return;
                        const target = closestTo(e.clientX, e.clientY);
                        if (!target || target === dragEl) return;
                        const r = target.getBoundingClientRect();
                        const after = e.clientY > r.top + r.height / 2
                            || (Math.abs(e.clientY - (r.top + r.height / 2)) < r.height / 2 && e.clientX > r.left + r.width / 2);
                        grid.insertBefore(dragEl, after ? target.nextSibling : target);
                    });
                }
            })();

            // ----- Gift bundle UI: toggle, picker search, add/remove rows -----
            (function () {
                const toggle = document.querySelector('[data-bundle-toggle]');
                const variantsSection = document.querySelector('[data-variants-section]');
                const bundleSection = document.querySelector('[data-bundle-section]');
                if (!toggle || !bundleSection) return;
                toggle.addEventListener('change', () => {
                    const on = toggle.checked;
                    bundleSection.classList.toggle('hidden', !on);
                    if (variantsSection) variantsSection.classList.toggle('hidden', on);
                });

                const search = bundleSection.querySelector('[data-bundle-search]');
                const opts = bundleSection.querySelectorAll('[data-bundle-add]');
                search?.addEventListener('input', () => {
                    const q = search.value.trim().toLowerCase();
                    opts.forEach((b) => {
                        b.classList.toggle('hidden', q && !b.dataset.label.toLowerCase().includes(q));
                    });
                });

                const rows = bundleSection.querySelector('[data-bundle-rows]');
                const tpl = bundleSection.querySelector('[data-bundle-template]');
                const nextIndex = () => rows.querySelectorAll('[data-bundle-row]').length;
                opts.forEach((b) => {
                    b.addEventListener('click', () => {
                        // No duplicates — bump quantity instead.
                        const existing = rows.querySelector('input[type="hidden"][value="' + b.dataset.id + '"]')?.closest('[data-bundle-row]');
                        if (existing) {
                            const qty = existing.querySelector('input[type="number"]');
                            qty.value = Math.min(99, (parseInt(qty.value, 10) || 0) + 1);
                            return;
                        }
                        const html = tpl.innerHTML
                            .replace(/__I__/g, String(nextIndex()))
                            .replace(/__VID__/g, b.dataset.id)
                            .replace(/__LABEL__/g, b.dataset.label);
                        rows.insertAdjacentHTML('beforeend', html);
                    });
                });
                rows.addEventListener('click', (e) => {
                    if (e.target.closest('[data-bundle-remove]')) {
                        e.target.closest('[data-bundle-row]').remove();
                    }
                });
            })();
            </script>
            @endpush
        </div>

        {{-- Sidebar --}}
        <aside class="h-fit space-y-4 rounded-card bg-white p-6 ring-1 ring-brand-100">
            <div>
                <label class="mb-1 block text-sm text-brand-600">دسته‌بندی‌ها</label>
                @php($selectedCats = collect(old('category_ids', $product->exists ? $product->categories->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all())
                <select name="category_ids[]" multiple size="6" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" style="height:auto">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array((int) $category->id, $selectedCats, true))>{{ $category->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-brand-400">می‌توانید چند مورد انتخاب کنید (Ctrl/Cmd). اولین مورد، دستهٔ اصلی است.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">مجموعه</label>
                <select name="collection_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($collections as $collection)
                        <option value="{{ $collection->id }}" @selected(old('collection_id', $product->collection_id) == $collection->id)>{{ $collection->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">راهنمای سایز</label>
                <select name="size_guide_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($sizeGuides as $sg)
                        <option value="{{ $sg->id }}" @selected(old('size_guide_id', $product->size_guide_id) == $sg->id)>{{ $sg->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">قیمت (تومان)</label>
                <input name="price" type="number" value="{{ old('price', $product->price) }}" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">قیمت قبل از تخفیف (اختیاری)</label>
                <input name="compare_at_price" type="number" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <label class="flex items-center gap-2 text-sm text-brand-600"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> فعال (نمایش در فروشگاه)</label>
            <label class="flex items-center gap-2 text-sm text-brand-600"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> منتخب</label>
            <label class="flex items-center gap-2 text-sm text-brand-600"><input type="checkbox" name="is_bundle" value="1" data-bundle-toggle @checked(old('is_bundle', $product->is_bundle))> پک هدیه (ترکیبی از چند تنوع موجود)</label>

            <button class="w-full rounded-lg bg-brand-900 py-2.5 text-sm font-semibold text-white">{{ $product->exists ? 'ذخیره تغییرات' : 'ایجاد محصول' }}</button>
        </aside>
    </form>

    <script>
        (function () {
            const list = document.querySelector('[data-variant-list]');
            const tpl = document.querySelector('[data-variant-template]');
            let idx = {{ $product->variants->count() }};

            function addRow(values) {
                const html = tpl.innerHTML.replaceAll('__I__', idx++);
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstChild;
                if (values) {
                    Object.entries(values).forEach(([k, v]) => {
                        const el = row.querySelector('[name$="[' + k + ']"]');
                        if (el) el.value = v;
                    });
                }
                row.querySelector('[data-remove-row]')?.addEventListener('click', () => row.remove());
                list.appendChild(row);
                return row;
            }

            document.querySelector('[data-add-variant]')?.addEventListener('click', () => addRow());

            // Matrix: colours × sizes → a variant row per cell (each needs its own barcode).
            document.querySelector('[data-matrix-generate]')?.addEventListener('click', () => {
                const split = (s) => (s || '').split(/[،,]+/).map(x => x.trim()).filter(Boolean);
                const colorsRaw = split(document.querySelector('[data-matrix-colors]').value);
                const sizes = split(document.querySelector('[data-matrix-sizes]').value);
                const note = document.querySelector('[data-matrix-note]');

                const colors = colorsRaw.length ? colorsRaw : ['']; // allow size-only products
                let made = 0;
                colors.forEach(c => {
                    const [name, hex] = c.split(':').map(x => (x || '').trim());
                    (sizes.length ? sizes : ['']).forEach(sz => {
                        if (!name && !sz) return;
                        addRow({ size: sz, color: name || '', color_hex: hex || '' });
                        made++;
                    });
                });
                note.textContent = made ? made + ' تنوع ساخته شد — بارکدها را وارد کنید.' : 'رنگ یا سایز وارد کنید.';
            });
        })();
    </script>
@endsection
