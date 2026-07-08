@extends('admin.layout')

@section('title', $page->exists ? 'ویرایش صفحه' : 'صفحه جدید')

@section('content')
    {{-- HTML-editor assets, rendered once in the real DOM (not inside a block
         <template>) so runtime-cloned editors can be initialised via rteScan(). --}}
    @include('admin.partials.html-editor-assets')

    {{-- Accordion + visual-control styles for the page-builder editor. --}}
    <style>
        .block-card.is-collapsed > .b-body { display: none; }
        .block-card:not(.is-collapsed) > .b-head .b-chevron { transform: rotate(90deg); }
        .block-card:not(.is-collapsed) { box-shadow: 0 0 0 2px rgb(204 51 51 / 0.25); }
        /* 3×3 position-grid control */
        .pos-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; width: 92px; }
        .pos-grid button { aspect-ratio: 1; border: 1px solid #e5dada; border-radius: 6px; background: #fff; font-size: 11px; line-height: 1; color: #b3a3a3; cursor: pointer; transition: all .15s; }
        .pos-grid button:hover { border-color: #cc3333; }
        .pos-grid button.is-active { background: #282828; border-color: #282828; color: #fff; }
        /* range slider + bubble */
        .range-row { display: flex; align-items: center; gap: 8px; }
        .range-row input[type=range] { flex: 1; accent-color: #cc3333; }
        .range-row output { min-width: 42px; text-align: center; font-variant-numeric: tabular-nums; font-size: 12px; color: #282828; }
        /* field tabs inside a block */
        .b-tab { color: #8a7a7a; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .b-tab.is-active { color: #282828; background: #fff; border-bottom-color: #cc3333; }
        /* live-preview saved/dirty pill */
        .pb-dirty-dot { display: inline-block; width: 7px; height: 7px; border-radius: 9999px; background: #cc3333; }
    </style>

    {{-- Page-builder shell. We break out of the admin layout's
         `mx-auto max-w-6xl` padding (negative margins) and fill the entire
         remaining viewport. Three zones:
           · top bar — save, breakpoint switcher, sidebar toggle
           · left sidebar — tray + form fields (visually LEFT in RTL via
             flex-row-reverse, scrolls independently)
           · main canvas — always-on live preview iframe with breakpoint sizing
         The preview reloads on save automatically (form posts → redirect →
         full page render → iframe refreshes). --}}
    <div x-data="{
            sidebarOpen: true,
            fs: false,      // full-screen preview (for precise drag positioning)
            bp: 'desktop',  // desktop | tablet | mobile
            panelW: Number(localStorage.getItem('pb_panel_w')) || 440,  // editor panel width (resizable)
            width() { return this.bp === 'mobile' ? 390 : this.bp === 'tablet' ? 768 : '100%' },
            reload() { const f = document.getElementById('preview-frame'); if (f) f.src = f.src.split('?')[0] + '?t=' + Date.now() },
            startResize(e) {
                const startX = e.clientX, startW = this.panelW;
                const move = (ev) => {
                    // RTL: panel is on the LEFT, so dragging the handle left widens it.
                    const w = startW + (startX - ev.clientX);
                    this.panelW = Math.max(320, Math.min(720, w));
                };
                const up = () => {
                    document.removeEventListener('pointermove', move);
                    document.removeEventListener('pointerup', up);
                    localStorage.setItem('pb_panel_w', this.panelW);
                    document.body.style.userSelect = '';
                };
                document.body.style.userSelect = 'none';
                document.addEventListener('pointermove', move);
                document.addEventListener('pointerup', up);
            },
         }"
         class="-m-5 flex h-[calc(100vh-3.25rem)] flex-col bg-brand-50 sm:-m-8 lg:h-screen">

        {{-- TOP BAR --}}
        <header class="relative z-50 flex flex-wrap items-center justify-between gap-2 border-b border-black/40 bg-[#1e1e2d] px-3 py-2 shadow-lg">
            <div class="flex items-center gap-2">
                <button type="button" @click="sidebarOpen = !sidebarOpen"
                        class="grid h-8 w-8 place-items-center rounded-lg text-white/70 transition hover:bg-white/10"
                        title="باز/بسته کردن نوار کناری">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-sm font-semibold text-white">
                    {{ $page->exists ? 'ویرایش: '.$page->title : 'صفحه جدید' }}
                </h1>
            </div>

            @if ($page->exists)
                <div class="flex items-center gap-1 rounded-full bg-white/10 p-1 ring-1 ring-white/15">
                    <button type="button" @click="bp = 'desktop'"
                            :class="bp === 'desktop' ? 'bg-white text-[#1e1e2d]' : 'text-white/70 hover:bg-white/10'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition" title="دسکتاپ">🖥 دسکتاپ</button>
                    <button type="button" @click="bp = 'tablet'"
                            :class="bp === 'tablet' ? 'bg-white text-[#1e1e2d]' : 'text-white/70 hover:bg-white/10'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition" title="تبلت (۷۶۸px)">📱 تبلت</button>
                    <button type="button" @click="bp = 'mobile'"
                            :class="bp === 'mobile' ? 'bg-white text-[#1e1e2d]' : 'text-white/70 hover:bg-white/10'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition" title="موبایل (۳۹۰px)">📱 موبایل</button>
                </div>
            @endif

            <div class="flex items-center gap-1.5">
                @if ($page->exists)
                    <button type="button" @click="reload()"
                            class="grid h-8 w-8 place-items-center rounded-lg text-white/70 transition hover:bg-white/10" title="بازخوانی پیش‌نمایش">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"/></svg>
                    </button>
                    <button type="button" @click="fs = !fs"
                            class="grid h-8 w-8 place-items-center rounded-lg text-white/70 transition hover:bg-white/10" title="پیش‌نمایش تمام‌صفحه">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                    </button>
                    <a href="{{ route('admin.pages.revisions', $page) }}" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white/80 transition hover:bg-white/20" title="تاریخچهٔ تغییرات">📜 تاریخچه</a>
                    <a href="{{ $page->is_home ? url('/') : url('/page/'.$page->slug) }}" target="_blank" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white/80 transition hover:bg-white/20">↗ تب جدید</a>
                @endif
                <button type="submit" form="page-form" class="rounded-lg bg-accent-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-accent-500">💾 ذخیره</button>
            </div>
        </header>

        {{-- BODY: natural RTL flow — first child lands on the right, last on
             the left. So <section> (preview) comes first → ends up on the
             right and gets the lion's share of viewport; <aside> (sidebar)
             comes last → narrow bar on the LEFT. --}}
        <div class="flex flex-1 overflow-hidden">

            {{-- LIVE PREVIEW canvas (right in RTL, large) --}}
            @if ($page->exists)
                <section class="flex flex-1 items-stretch justify-center overflow-auto bg-brand-100 p-4"
                         :class="fs ? 'fixed inset-x-0 bottom-0 top-[49px] z-30' : ''">
                    {{-- Exit full-screen — floats bottom-corner over the canvas. --}}
                    <button type="button" x-show="fs" @click="fs = false" x-cloak
                            class="fixed bottom-4 end-4 z-[61] rounded-lg bg-brand-900/90 px-3 py-1.5 text-xs font-semibold text-white shadow-lg hover:bg-brand-900">
                        ✕ خروج از تمام‌صفحه
                    </button>
                    <div class="mx-auto bg-white shadow-lg ring-1 ring-brand-200 transition-[width] duration-300"
                         :style="'width: ' + (typeof width() === 'number' ? width() + 'px' : width()) + '; max-width: 100%;'">
                        <iframe id="preview-frame"
                                src="{{ $page->is_home ? url('/') : url('/page/'.$page->slug) }}"
                                class="block h-full min-h-full w-full"
                                title="پیش‌نمایش زنده"></iframe>
                    </div>
                </section>
            @else
                <section class="flex flex-1 items-center justify-center bg-brand-100 p-8 text-center text-sm text-brand-500">
                    صفحه را ابتدا ذخیره کنید تا پیش‌نمایش زنده فعال شود.
                </section>
            @endif

            {{-- EDITOR PANEL (left in RTL) — resizable. Width is driven by an
                 inline style bound to Alpine `panelW` (persisted in
                 localStorage) so there's no Tailwind purge risk. A drag handle
                 on its inner edge lets the admin widen it up to 720px. --}}
            <aside x-show="sidebarOpen"
                   :style="'width:' + panelW + 'px'"
                   :class="fs ? 'fixed bottom-0 left-0 top-[49px] z-40 !overflow-y-auto shadow-2xl' : ''"
                   class="relative shrink-0 overflow-hidden border-r border-brand-200 bg-white">
                {{-- Resize handle — sits on the panel's preview-facing edge
                     (start = right in RTL). --}}
                <div @pointerdown="startResize($event)"
                     class="absolute inset-y-0 start-0 z-20 w-1.5 cursor-col-resize bg-transparent transition hover:bg-accent-300"
                     title="کشیدن برای تغییر عرض"></div>
                <div class="flex h-full flex-col">
                    <div class="flex-1 overflow-y-auto p-3">
                        <form method="POST"
                              action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
                              id="page-form">
                            @csrf
                            @if ($page->exists) @method('PUT') @endif

                            {{-- Page meta (collapsible) --}}
                            <details {{ $page->exists ? '' : 'open' }} class="mb-3 rounded-card bg-brand-50 p-3 ring-1 ring-brand-100">
                                <summary class="cursor-pointer text-xs font-semibold text-brand-700">⚙ تنظیمات صفحه</summary>
                                <div class="mt-3 grid gap-2">
                                    <input type="text" name="title" value="{{ old('title', $page->title) }}" required placeholder="عنوان صفحه" class="w-full rounded-lg border border-brand-200 px-3 py-1.5 text-xs">
                                    <input type="text" name="slug" value="{{ old('slug', $page->slug) }}" dir="ltr" placeholder="اسلاگ (مثلاً lookbook)" class="w-full rounded-lg border border-brand-200 px-3 py-1.5 text-xs">
                                    <input type="text" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}" placeholder="عنوان سئو" class="w-full rounded-lg border border-brand-200 px-3 py-1.5 text-xs">
                                    <input type="text" name="seo_description" value="{{ old('seo_description', $page->seo_description) }}" placeholder="توضیح متا" class="w-full rounded-lg border border-brand-200 px-3 py-1.5 text-xs">
                                    <div class="flex flex-wrap gap-3 pt-1 text-[11px] text-brand-700">
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published ?? true))> منتشر</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="is_home" value="1" @checked(old('is_home', $page->is_home))> صفحه اصلی</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="show_in_nav" value="1" @checked(old('show_in_nav', $page->show_in_nav))> نمایش در منو</label>
                                    </div>

                                    {{-- Per-page background: colour + optional full-bleed image. --}}
                                    <div class="mt-2 border-t border-brand-200 pt-2">
                                        <span class="mb-1.5 block text-[11px] font-semibold text-brand-600">پس‌زمینهٔ صفحه</span>
                                        <div class="flex items-center gap-2">
                                            @php($_bgColor = old('bg_color', $page->bg_color))
                                            <input type="color" value="{{ $_bgColor ?: '#ffffff' }}"
                                                   oninput="this.nextElementSibling.value = this.value; this.nextElementSibling.dispatchEvent(new Event('input', {bubbles:true}))"
                                                   class="h-8 w-10 cursor-pointer rounded border border-brand-200 bg-white p-0.5">
                                            <input type="text" name="bg_color" value="{{ $_bgColor }}" dir="ltr" placeholder="#ffffff — رنگ پس‌زمینه"
                                                   class="flex-1 rounded-lg border border-brand-200 px-3 py-1.5 text-xs">
                                        </div>
                                        {{-- Background image (upload). Uses the same .img-field markup the
                                             block image field uses, so the shared upload handler covers it. --}}
                                        <div class="img-field mt-2 flex items-center gap-2">
                                            <input type="hidden" name="bg_image" value="{{ old('bg_image', $page->bg_image) }}" class="img-url">
                                            <div class="img-preview grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-brand-100 text-[10px] text-brand-300 ring-1 ring-brand-200">
                                                @if ($page->bg_image)<img src="{{ $page->bg_image }}" alt="" class="h-full w-full object-cover">@else بدون تصویر @endif
                                            </div>
                                            <label class="shrink-0 cursor-pointer rounded-lg bg-brand-100 px-3 py-1.5 text-[11px] font-medium text-brand-700 hover:bg-brand-200">
                                                تصویر پس‌زمینه
                                                <input type="file" accept="image/*" class="img-file hidden">
                                            </label>
                                            <button type="button" class="img-clear text-[11px] text-red-400 hover:underline {{ $page->bg_image ? '' : 'hidden' }}">حذف</button>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            {{-- Tray: drag a block / pattern into the list --}}
                            <details open class="mb-3 rounded-card bg-brand-50 p-3 ring-1 ring-brand-100">
                                <summary class="mb-2 cursor-pointer text-xs font-semibold text-brand-700">➕ افزودن بلاک — بکشید و رها کنید</summary>
                                <input type="search" id="widget-search" placeholder="🔍 جستجوی بلاک…" autocomplete="off"
                                       class="mb-2 w-full rounded-lg border border-brand-200 px-3 py-1.5 text-xs focus:border-accent-400 focus:outline-none">
                                <div class="grid grid-cols-3 gap-1.5">
                                    @foreach ($registry as $type => $def)
                                        <button type="button" draggable="true" data-tray-type="{{ $type }}"
                                                class="block-tray-card flex cursor-grab flex-col items-center gap-0.5 rounded-md bg-white px-2 py-2 text-center text-[10px] text-brand-700 ring-1 ring-brand-100 transition hover:ring-accent-300 active:cursor-grabbing">
                                            <span class="text-base">{{ $def['icon'] }}</span>
                                            <span class="line-clamp-1 text-[10px] font-medium">{{ $def['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                @if (! empty($patterns) && count($patterns))
                                    <div class="mt-3 border-t border-brand-200 pt-2">
                                        <p class="mb-1.5 flex items-center justify-between text-[10px] font-semibold uppercase tracking-wide text-brand-500">
                                            <span>💾 الگوهای ذخیره‌شده</span>
                                            <a href="{{ route('admin.patterns.index') }}" class="text-[10px] font-normal normal-case text-accent-600 hover:underline">مدیریت</a>
                                        </p>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            @foreach ($patterns as $pat)
                                                <button type="button" draggable="true" data-pattern-id="{{ $pat->id }}"
                                                        data-pattern-blocks='@json($pat->blocks)'
                                                        class="block-tray-card flex cursor-grab flex-col items-center gap-0.5 rounded-md bg-accent-50 px-2 py-2 text-center text-[10px] text-brand-800 ring-1 ring-accent-200 transition hover:ring-accent-400 active:cursor-grabbing">
                                                    <span class="text-base">{{ $pat->icon }}</span>
                                                    <span class="line-clamp-1 text-[10px] font-medium">{{ $pat->name }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </details>

                            {{-- Block list (live editor cards) --}}
                            <h2 class="mb-2 mt-4 text-xs font-semibold text-brand-700">🧱 بلاک‌های صفحه <span class="font-normal text-brand-400">— روی هر بلاک بزنید تا باز شود</span></h2>
                            <div id="blocks-list" class="space-y-2">
                                @foreach ($page->blockList() as $i => $block)
                                    @include('admin.pages._block', ['type' => $block['type'], 'data' => $block['data'], 'i' => $i, 'vis' => $block['_v'] ?? 'all', 'bid' => $block['_bid'] ?? ''])
                                @endforeach
                            </div>
                            <p id="empty-hint" class="mt-3 rounded-card border border-dashed border-brand-200 p-4 text-center text-xs text-brand-400 {{ count($page->blocks ?? []) ? 'hidden' : '' }}">
                                هنوز بلاکی اضافه نشده. از بالا یک بلاک را بکشید.
                            </p>
                        </form>

                        {{-- Templates — rendered OUTSIDE #page-form because each
                             template-apply <form> has its own POST action.
                             Nested forms are illegal HTML; the browser would
                             silently submit the outer page-form instead. --}}
                        @if ($page->exists && ! empty($templates))
                            <details class="mt-3 rounded-card bg-gradient-to-tl from-accent-50 to-brand-50 ring-1 ring-brand-100">
                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-brand-700">✨ شروع از قالب آماده</summary>
                                <div class="grid gap-2 p-3">
                                    @foreach ($templates as $slug => $tpl)
                                        <form method="POST" action="{{ route('admin.pages.applyTemplate', $page) }}"
                                              class="rounded-lg bg-white p-3 ring-1 ring-brand-100"
                                              onsubmit="return confirm('قالب «{{ $tpl['label'] }}» اعمال شود؟ (جایگزینی بلاک‌های فعلی را پاک می‌کند)')">
                                            @csrf
                                            <input type="hidden" name="template" value="{{ $slug }}">
                                            <p class="text-xs font-semibold text-brand-900">{{ $tpl['label'] }}</p>
                                            <p class="mt-0.5 text-[10px] leading-5 text-brand-500">{{ $tpl['description'] }}</p>
                                            <div class="mt-2 flex gap-1">
                                                <button type="submit" name="mode" value="replace" class="rounded-md bg-brand-900 px-2 py-1 text-[10px] font-medium text-white hover:bg-brand-800">جایگزینی</button>
                                                <button type="submit" name="mode" value="append" class="rounded-md bg-brand-100 px-2 py-1 text-[10px] font-medium text-brand-700 hover:bg-brand-200">افزودن</button>
                                            </div>
                                        </form>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        @if ($page->exists)
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="mt-6 border-t border-brand-100 pt-4" onsubmit="return confirm('این صفحه حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">حذف صفحه</button>
                            </form>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- Block field templates (one per type) — kept outside the main wrapper
         so they're never visible; the JS clones from these on drag/drop. --}}
    @foreach ($registry as $type => $def)
        <template id="tpl-{{ $type }}">@include('admin.pages._block', ['type' => $type, 'data' => [], 'i' => '__I__'])</template>
    @endforeach

    <script>
        (function () {
            const list = document.getElementById('blocks-list');
            const hint = document.getElementById('empty-hint');
            // Wider drop zone — the whole form. Dropping anywhere in the sidebar
            // appends the new block to the list (or inserts at the indicator).
            const dropZone = document.getElementById('page-form') || list;
            const uploadUrl = @json(route('admin.pages.upload'));
            const previewUrl = @json(route('admin.pages.preview'));
            const previewBlockUrl = @json(route('admin.pages.previewBlock'));
            const csrf = @json(csrf_token());
            const pageForm = document.getElementById('page-form');

            function refreshHint() { hint.classList.toggle('hidden', list.children.length > 0); }

            // ----- Widget search: filter the block tray by name -----
            const widgetSearch = document.getElementById('widget-search');
            if (widgetSearch) {
                widgetSearch.addEventListener('input', () => {
                    const q = widgetSearch.value.trim().toLowerCase();
                    document.querySelectorAll('.block-tray-card[data-tray-type]').forEach((card) => {
                        card.classList.toggle('hidden', q !== '' && !card.textContent.toLowerCase().includes(q));
                    });
                });
            }

            // ----- ⋮ options menu -----
            document.addEventListener('click', (e) => {
                const dots = e.target.closest('.b-dots');
                // Close any open menu unless we're clicking inside one.
                if (!e.target.closest('.b-menu')) {
                    document.querySelectorAll('.b-menu:not(.hidden)').forEach((m) => {
                        if (!dots || dots.nextElementSibling !== m) m.classList.add('hidden');
                    });
                }
                if (dots) {
                    e.stopPropagation();
                    dots.nextElementSibling?.classList.toggle('hidden');
                }
            });

            // ----- field tabs (🎨/🖥/📱) inside a block -----
            list.addEventListener('click', (e) => {
                const tab = e.target.closest('.b-tab');
                if (!tab) return;
                const card = tab.closest('.block-card');
                const key = tab.dataset.tab;
                card.querySelectorAll('.b-tab').forEach((t) => t.classList.toggle('is-active', t === tab));
                card.querySelectorAll('.b-tab-panel').forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== key));
            });

            // ----- Live preview (no save needed) -----
            // Debounced: on any field change, serialise the form and POST it to
            // the preview endpoint, then swap the iframe's srcdoc with the
            // rendered (unsaved) page. Falls back silently on error.
            let previewTimer = null;
            function schedulePreview() {
                if (pbEditing) return; // don't blow away an in-progress inline edit
                const frame = document.getElementById('preview-frame');
                if (!frame) return; // new (unsaved) page has no iframe yet
                clearTimeout(previewTimer);
                previewTimer = setTimeout(runPreview, 180);
            }
            async function runPreview() {
                const frame = document.getElementById('preview-frame');
                if (!frame || !pageForm) return;
                reconstruct(); // make field names reflect current order before serialising
                try {
                    const res = await fetch(previewUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(pageForm),
                    });
                    if (!res.ok) return;
                    frame.removeAttribute('src');
                    frame.srcdoc = await res.text();
                    const dot = document.getElementById('pb-dirty');
                    if (dot) dot.classList.add('hidden');
                } catch (_) { /* keep last good preview */ }
            }
            // Any edit refreshes the preview. Block edits re-render ONLY that
            // block (tiny, fast payload swapped in place) instead of the whole
            // page; page-level fields fall back to the full preview.
            function onFieldEdit(e) {
                const t = e.target;
                const card = t && t.closest && t.closest('.block-card');
                if (card && t.dataset && (t.dataset.bk || t.dataset.rk)) {
                    if (t.dataset.bk && t.dataset.bk.indexOf('data._style') === 0) pbApplyStyleLive(card); // instant
                    renderBlockLive(card);
                } else {
                    schedulePreview();
                }
            }
            if (pageForm) {
                pageForm.addEventListener('input', onFieldEdit);
                pageForm.addEventListener('change', onFieldEdit);
            }

            // Harvest a block card's data (incl. _style + repeaters) for the
            // single-block preview endpoint.
            function harvestBlockData(card) {
                const data = {};
                card.querySelectorAll('[data-bk]').forEach((el) => {
                    if (el.closest('.rep-item')) return;
                    const k = el.dataset.bk;
                    if (k === 'type' || k === '_bid' || k.indexOf('data.') !== 0) return;
                    const path = k.split('.').slice(1);
                    let obj = data;
                    for (let n = 0; n < path.length - 1; n++) { obj[path[n]] = obj[path[n]] || {}; obj = obj[path[n]]; }
                    obj[path[path.length - 1]] = readVal(el);
                });
                card.querySelectorAll('.rep-container').forEach((rc) => {
                    const rows = [];
                    rc.querySelectorAll('.rep-items > .rep-item').forEach((item) => {
                        const row = {};
                        item.querySelectorAll('[data-rk]').forEach((el) => { row[el.dataset.rk] = readVal(el); });
                        rows.push(row);
                    });
                    data[rc.dataset.rep] = rows;
                });
                return data;
            }
            const blockTimers = new WeakMap();
            function renderBlockLive(card) {
                const bid = card.dataset.bid, type = card.dataset.type;
                if (!bid || !type) { schedulePreview(); return; }
                clearTimeout(blockTimers.get(card));
                blockTimers.set(card, setTimeout(async () => {
                    const frame = document.getElementById('preview-frame');
                    let doc; try { doc = frame.contentDocument; } catch (_) { return; }
                    if (!doc) return;
                    try {
                        const res = await fetch(previewBlockUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify({ type, _bid: bid, data: harvestBlockData(card) }),
                        });
                        if (!res.ok) { schedulePreview(); return; }
                        const html = await res.text();
                        const el = doc.querySelector('[data-bid="' + bid + '"]');
                        if (!el || !html.trim()) return;
                        // Drop stale sibling <style> tags + the instant-style tag for this bid.
                        while (el.previousElementSibling && el.previousElementSibling.tagName === 'STYLE') el.previousElementSibling.remove();
                        const lt = doc.getElementById('pb-live-' + bid); if (lt) lt.remove();
                        el.outerHTML = html;
                        // Freshly-injected blocks aren't seen by the page's
                        // scroll-reveal IntersectionObserver, so their .reveal
                        // elements would stay opacity:0 (invisible). Force them
                        // visible, and re-init marquees in the new node.
                        const fresh = doc.querySelector('[data-bid="' + bid + '"]');
                        if (fresh) {
                            fresh.querySelectorAll('.reveal').forEach((r) => r.classList.add('is-visible'));
                            if (fresh.classList && fresh.classList.contains('reveal')) fresh.classList.add('is-visible');
                        }
                        wireOverlayDrag(frame); // re-wire canvas hooks on the new node
                    } catch (_) { schedulePreview(); }
                }, 220));
            }

            function pbApplyStyleLive(card) {
                const bid = card.dataset.bid; if (!bid) return;
                const frame = document.getElementById('preview-frame');
                let doc; try { doc = frame.contentDocument; } catch (_) { return; }
                if (!doc) return;
                const el = doc.querySelector('[data-bid="' + bid + '"]'); if (!el) return;
                const g = (k) => { const i = card.querySelector('[data-bk="data._style.' + k + '"]'); return i ? String(i.value).trim() : ''; };
                const n = (k) => { const v = parseInt(g(k), 10); return isNaN(v) ? null : Math.max(0, v); };
                const css = [];
                if (n('mt') !== null) css.push('margin-top:' + n('mt') + 'px');
                if (n('mb') !== null) css.push('margin-bottom:' + n('mb') + 'px');
                if (n('pt') !== null) css.push('padding-top:' + n('pt') + 'px');
                if (n('pb') !== null) css.push('padding-bottom:' + n('pb') + 'px');
                if (n('px') !== null) css.push('padding-inline:' + n('px') + 'px');
                if (n('maxw')) css.push('max-width:' + n('maxw') + 'px');
                if (g('align') === 'center') css.push('margin-inline:auto');
                if (n('minh')) css.push('min-height:' + n('minh') + 'px');
                if (n('radius')) { css.push('border-radius:' + n('radius') + 'px'); css.push('overflow:hidden'); }
                if (n('bw')) css.push('border:' + n('bw') + 'px solid ' + (g('bc') || '#e5e7eb'));
                const sh = { sm: '0 1px 2px rgba(0,0,0,.08)', md: '0 6px 18px rgba(0,0,0,.10)', lg: '0 20px 45px rgba(0,0,0,.16)' }[g('shadow')];
                if (sh) css.push('box-shadow:' + sh);
                if (['right', 'center', 'left'].indexOf(g('ta')) !== -1) css.push('text-align:' + g('ta'));
                if (g('bg')) css.push('background-color:' + g('bg'));
                el.style.cssText = css.length ? css.join(';') : 'display:contents';
                // typography → scoped style tag in the iframe
                const t2 = [];
                if (g('tc')) t2.push('color:' + g('tc') + ' !important');
                if (['300', '400', '500', '600', '700', '800'].indexOf(g('fw')) !== -1) t2.push('font-weight:' + g('fw') + ' !important');
                if (g('lh')) { const lh = g('lh').replace(/[^0-9.]/g, ''); if (lh) t2.push('line-height:' + lh + ' !important'); }
                if (n('ls') !== null) t2.push('letter-spacing:' + n('ls') + 'px !important');
                let tag = doc.getElementById('pb-live-' + bid);
                if (t2.length) {
                    if (!tag) { tag = doc.createElement('style'); tag.id = 'pb-live-' + bid; doc.head.appendChild(tag); }
                    tag.textContent = '[data-bid="' + bid + '"] :is(h1,h2,h3,h4,h5,h6,p,li){' + t2.join(';') + '}';
                } else if (tag) { tag.textContent = ''; }
            }

            // ----- Drag the editorial-hero floating PNG on the preview -----
            // The preview iframe is same-origin, so we can attach drag handlers
            // to the rendered PNG. Dragging moves it LIVE (no re-render needed),
            // and on release writes x/y% into the matching block's position
            // sliders — desktop or mobile, by which image is visible. The Nth
            // [data-editorial-hero] section maps to the Nth editorial_hero card.
            const overlayFrame = document.getElementById('preview-frame');
            if (overlayFrame) {
                overlayFrame.addEventListener('load', () => { wireOverlayDrag(overlayFrame); initCanvas(overlayFrame); });
                try {
                    if (overlayFrame.contentDocument &&
                        overlayFrame.contentDocument.readyState === 'complete') { wireOverlayDrag(overlayFrame); initCanvas(overlayFrame); }
                } catch (_) { /* skip */ }
            }
            function wireOverlayDrag(frame) {
                let doc;
                try { doc = frame.contentDocument; } catch (_) { return; }
                if (!doc) return;
                const sections = [...doc.querySelectorAll('[data-editorial-hero]')];
                const cards = [...list.querySelectorAll('.block-card[data-type="editorial_hero"]')];
                sections.forEach((section, n) => {
                    const card = cards[n];
                    if (!card) return;
                    section.querySelectorAll('[data-eh-overlay]').forEach((el) => {
                        if (el.offsetParent === null || el.dataset.ehWired === '1') return; // hidden / already wired
                        el.dataset.ehWired = '1';
                        el.style.pointerEvents = 'auto';
                        el.style.cursor = 'move';
                        el.style.touchAction = 'none';
                        el.style.outline = '2px dashed rgba(59,130,246,.7)';
                        el.style.outlineOffset = '2px';
                        el.title = 'برای جابه‌جایی بکشید';
                        el.addEventListener('dragstart', (e) => e.preventDefault());
                        el.addEventListener('click', (e) => { e.preventDefault(); }); // never navigate in the editor
                        el.addEventListener('pointerdown', (e) => startOverlayDrag(e, el, section, card));
                    });
                });
            }
            function startOverlayDrag(e, el, section, card) {
                e.preventDefault();
                const bp = el.dataset.ehBp === 'mobile' ? 'mobile' : 'desktop';
                const unitEl = card.querySelector('[data-bk="data.overlay_unit"]');
                const unit = unitEl && unitEl.value === 'px' ? 'px' : '%';
                try { el.setPointerCapture(e.pointerId); } catch (_) {}
                let xy = null;
                const move = (ev) => {
                    const r = section.getBoundingClientRect();
                    if (!r.width || !r.height) return;
                    let x, y;
                    if (unit === 'px') {           // exact pixels from the banner's top-left
                        x = Math.max(0, Math.round(ev.clientX - r.left));
                        y = Math.max(0, Math.round(ev.clientY - r.top));
                    } else {                        // percent (scales with the banner)
                        x = Math.max(0, Math.min(100, Math.round(((ev.clientX - r.left) / r.width) * 100)));
                        y = Math.max(0, Math.min(100, Math.round(((ev.clientY - r.top) / r.height) * 100)));
                    }
                    el.style.left = x + unit;
                    el.style.top = y + unit;
                    xy = { x, y };
                };
                const up = () => {
                    el.removeEventListener('pointermove', move);
                    el.removeEventListener('pointerup', up);
                    if (!xy) return;
                    setOverlaySlider(card, 'overlay_x_' + bp, xy.x);
                    setOverlaySlider(card, 'overlay_y_' + bp, xy.y);
                };
                el.addEventListener('pointermove', move);
                el.addEventListener('pointerup', up);
            }
            function setOverlaySlider(card, key, val) {
                const input = card.querySelector('[data-bk="data.' + key + '"]');
                if (!input) return;
                input.value = val;
                input.dispatchEvent(new Event('input', { bubbles: true })); // updates <output> + schedules preview
            }

            // ================= E2: canvas selection layer (Elementor-style) =====
            // Click a block on the canvas → select it (highlight + toolbar) and
            // open its settings card in the sidebar. Hover → outline. All drawn
            // inside the same-origin iframe, keyed by data-bid (E1).
            let pbSelBid = null;
            let pbEditing = false; // true while inline-editing text on the canvas (suppresses re-render)
            function pbCardByBid(bid) { return bid ? list.querySelector('.block-card[data-bid="' + bid + '"]') : null; }
            // display:contents wrappers have no box → use the union of their children.
            function pbRect(el) {
                if (!el) return null;
                let r = el.getBoundingClientRect();
                if ((!r.width || !r.height) && el.children.length) {
                    let t = Infinity, l = Infinity, b = -Infinity, rt = -Infinity;
                    [...el.children].forEach((c) => {
                        const cr = c.getBoundingClientRect();
                        if (!cr.width && !cr.height) return;
                        t = Math.min(t, cr.top); l = Math.min(l, cr.left);
                        b = Math.max(b, cr.bottom); rt = Math.max(rt, cr.right);
                    });
                    if (b > t) r = { top: t, left: l, bottom: b, right: rt, width: rt - l, height: b - t };
                }
                return r;
            }
            function initCanvas(frame) {
                let doc, win;
                try { doc = frame.contentDocument; win = frame.contentWindow; } catch (_) { return; }
                if (!doc || !doc.body || doc.__pbCanvas) return;
                doc.__pbCanvas = true;

                const st = doc.createElement('style');
                st.textContent = `
                    .pb-ov{position:absolute;pointer-events:none;z-index:2147483646;box-sizing:border-box;transition:all .05s linear}
                    .pb-hover{outline:2px solid rgba(59,130,246,.5);outline-offset:-2px}
                    .pb-sel{outline:2px solid #2563eb;outline-offset:-2px}
                    .pb-tag{position:absolute;top:-22px;inset-inline-start:0;background:#2563eb;color:#fff;font:600 11px/1.6 sans-serif;
                        padding:0 6px;border-radius:4px 4px 0 0;white-space:nowrap;pointer-events:none}
                    .pb-tools{position:absolute;top:-30px;inset-inline-end:0;display:flex;gap:2px;background:#2563eb;border-radius:6px;
                        padding:2px;pointer-events:auto;z-index:2147483647;box-shadow:0 2px 8px rgba(0,0,0,.25)}
                    .pb-tools button{all:unset;cursor:pointer;color:#fff;font-size:13px;line-height:1;padding:4px 6px;border-radius:4px}
                    .pb-tools button:hover{background:rgba(255,255,255,.2)}
                `;
                doc.head.appendChild(st);

                const hover = doc.createElement('div'); hover.className = 'pb-ov pb-hover'; hover.style.display = 'none';
                const sel = doc.createElement('div'); sel.className = 'pb-ov pb-sel'; sel.style.display = 'none';
                sel.innerHTML = '<span class="pb-tag"></span><div class="pb-tools">'
                    + '<button data-a="settings" title="تنظیمات">✎</button>'
                    + '<button data-a="up" title="بالا">↑</button>'
                    + '<button data-a="down" title="پایین">↓</button>'
                    + '<button data-a="add" title="افزودن بلاک زیر">＋</button>'
                    + '<button data-a="dup" title="تکثیر">⧉</button>'
                    + '<button data-a="del" title="حذف">🗑</button></div>';
                doc.body.appendChild(hover); doc.body.appendChild(sel);

                const place = (box, el) => {
                    const r = pbRect(el); if (!r) { box.style.display = 'none'; return; }
                    const sx = win.scrollX || doc.documentElement.scrollLeft || 0;
                    const sy = win.scrollY || doc.documentElement.scrollTop || 0;
                    box.style.display = 'block';
                    box.style.left = (r.left + sx) + 'px'; box.style.top = (r.top + sy) + 'px';
                    box.style.width = r.width + 'px'; box.style.height = r.height + 'px';
                };
                const repositionSel = () => {
                    if (!pbSelBid) { sel.style.display = 'none'; return; }
                    const wrap = doc.querySelector('[data-bid="' + pbSelBid + '"]');
                    if (wrap) place(sel, wrap); else sel.style.display = 'none';
                };

                doc.addEventListener('pointermove', (e) => {
                    const w = e.target.closest && e.target.closest('[data-bid]');
                    if (!w || w.getAttribute('data-bid') === pbSelBid) { hover.style.display = 'none'; return; }
                    place(hover, w);
                });
                doc.addEventListener('pointerleave', () => { hover.style.display = 'none'; });

                doc.addEventListener('click', (e) => {
                    if (pbEditing) return;                          // let clicks place the caret while editing
                    if (e.target.closest && e.target.closest('.pb-tools')) return; // handled below
                    if (e.target.closest && e.target.closest('[data-edit][contenteditable]')) return;
                    const w = e.target.closest && e.target.closest('[data-bid]');
                    if (!w) return;
                    e.preventDefault(); e.stopPropagation();       // don't follow links while editing
                    pbSelect(w.getAttribute('data-bid'), doc, sel);
                }, true);

                // Inline text editing — double-click any [data-edit] element to
                // edit it in place; on blur, write back to its sidebar field.
                doc.addEventListener('dblclick', (e) => {
                    const t = e.target.closest && e.target.closest('[data-edit]');
                    if (!t) return;
                    const wrap = t.closest('[data-bid]'); if (!wrap) return;
                    const card = pbCardByBid(wrap.getAttribute('data-bid')); if (!card) return;
                    const key = t.getAttribute('data-edit');
                    const isHtml = t.hasAttribute('data-edit-html');
                    e.preventDefault(); e.stopPropagation();
                    pbEditing = true;
                    t.setAttribute('contenteditable', 'true');
                    t.style.outline = '2px solid #16a34a'; t.style.outlineOffset = '2px';
                    t.focus();
                    const finish = () => {
                        t.removeAttribute('contenteditable');
                        t.style.outline = ''; t.style.outlineOffset = '';
                        pbEditing = false;
                        const field = card.querySelector('[data-bk="data.' + key + '"]');
                        if (field) {
                            field.value = isHtml ? t.innerHTML : t.innerText;
                            field.dispatchEvent(new Event('input', { bubbles: true })); // save + re-render
                        }
                    };
                    t.addEventListener('blur', finish, { once: true });
                    t.addEventListener('keydown', (ev) => {
                        if (ev.key === 'Escape' || (!isHtml && ev.key === 'Enter')) { ev.preventDefault(); t.blur(); }
                    });
                }, true);

                // E5 — widget menu for "add block below". Populated from the
                // sidebar tray so it always mirrors the real block catalog.
                const menu = doc.createElement('div');
                menu.style.cssText = 'position:absolute;z-index:2147483647;display:none;background:#fff;border-radius:8px;'
                    + 'box-shadow:0 8px 24px rgba(0,0,0,.25);max-height:320px;overflow:auto;width:230px;padding:6px;'
                    + 'pointer-events:auto;direction:rtl;font-family:sans-serif';
                doc.body.appendChild(menu);
                const openWidgetMenu = () => {
                    menu.innerHTML = '';
                    document.querySelectorAll('.block-tray-card[data-tray-type]').forEach((tc) => {
                        const b = doc.createElement('button');
                        b.textContent = tc.textContent.trim();
                        b.style.cssText = 'all:unset;display:block;width:100%;box-sizing:border-box;padding:7px 10px;'
                            + 'font:500 13px sans-serif;color:#1f2937;border-radius:6px;cursor:pointer';
                        b.addEventListener('mouseenter', () => b.style.background = '#eff6ff');
                        b.addEventListener('mouseleave', () => b.style.background = '');
                        b.addEventListener('click', () => { menu.style.display = 'none'; pbInsertBlock(tc.dataset.trayType, pbCardByBid(pbSelBid)); });
                        menu.appendChild(b);
                    });
                    const r = sel.getBoundingClientRect();
                    menu.style.left = (sel.offsetLeft) + 'px';
                    menu.style.top = (sel.offsetTop + sel.offsetHeight + 4) + 'px';
                    menu.style.display = 'block';
                };
                doc.addEventListener('pointerdown', (e) => { if (!menu.contains(e.target)) menu.style.display = 'none'; }, true);

                sel.querySelector('.pb-tools').addEventListener('click', (e) => {
                    const btn = e.target.closest('button'); if (!btn) return;
                    e.preventDefault();
                    if (btn.dataset.a === 'add') { openWidgetMenu(); return; }
                    pbToolAction(btn.dataset.a);
                });

                // E6 — right-click selects a block; keyboard shortcuts.
                doc.addEventListener('contextmenu', (e) => {
                    const w = e.target.closest && e.target.closest('[data-bid]');
                    if (!w) return;
                    e.preventDefault();
                    pbSelect(w.getAttribute('data-bid'), doc, sel);
                });
                doc.addEventListener('keydown', (e) => {
                    if (pbEditing || !pbSelBid) return;
                    const card = pbCardByBid(pbSelBid); if (!card) return;
                    if (e.key === 'Delete') { e.preventDefault(); pbToolAction('del'); }
                    else if ((e.metaKey || e.ctrlKey) && (e.key === 'd' || e.key === 'D')) { e.preventDefault(); pbToolAction('dup'); }
                });

                win.addEventListener('scroll', repositionSel, true);
                win.addEventListener('resize', repositionSel);
                // restore selection after a preview re-render
                if (pbSelBid) pbSelect(pbSelBid, doc, sel);
            }
            // Insert a fresh block card (from the hidden #tpl-<type> template)
            // after the given card, then open it. reconstruct() assigns its id.
            function pbInsertBlock(type, afterCard) {
                const tpl = document.getElementById('tpl-' + type);
                if (!tpl || !tpl.content.firstElementChild) return;
                const node = tpl.content.firstElementChild.cloneNode(true);
                if (afterCard && afterCard.parentNode === list) list.insertBefore(node, afterCard.nextSibling);
                else list.appendChild(node);
                refreshHint();
                reconstruct();
                openCard(node, true);
                node.scrollIntoView({ block: 'center' });
                schedulePreview();
            }
            function pbSelect(bid, doc, sel) {
                pbSelBid = bid;
                const wrap = doc.querySelector('[data-bid="' + bid + '"]');
                const card = pbCardByBid(bid);
                if (sel && wrap) {
                    const tag = sel.querySelector('.pb-tag');
                    if (tag && card) tag.textContent = (card.querySelector('.b-head .truncate') || {}).textContent || '';
                    // reposition
                    const win = doc.defaultView;
                    const r = pbRect(wrap);
                    if (r) {
                        sel.style.display = 'block';
                        sel.style.left = (r.left + (win.scrollX || 0)) + 'px';
                        sel.style.top = (r.top + (win.scrollY || 0)) + 'px';
                        sel.style.width = r.width + 'px'; sel.style.height = r.height + 'px';
                    }
                }
                if (card) { openCard(card, true); card.scrollIntoView({ block: 'nearest' }); }
            }
            function pbToolAction(a) {
                const card = pbCardByBid(pbSelBid); if (!card) return;
                if (a === 'settings') { openCard(card, true); card.scrollIntoView({ block: 'center' }); }
                else if (a === 'up' && card.previousElementSibling) { card.parentNode.insertBefore(card, card.previousElementSibling); schedulePreview(); }
                else if (a === 'down' && card.nextElementSibling) { card.parentNode.insertBefore(card.nextElementSibling, card); schedulePreview(); }
                else if (a === 'dup') {
                    const clone = card.cloneNode(true);
                    clone.removeAttribute('data-bid');
                    const bidInput = clone.querySelector('[data-bk="_bid"]'); if (bidInput) bidInput.value = '';
                    card.parentNode.insertBefore(clone, card.nextElementSibling);
                    pbSelBid = null; schedulePreview();
                }
                else if (a === 'del') { pbSelBid = null; card.remove(); refreshHint(); schedulePreview(); }
            }

            // Rebuild every field's name from its data-bk / data-rk attribute so
            // block order and repeater-row order are captured on submit.
            function reconstruct() {
                [...list.children].forEach((card, i) => {
                    // Backfill a stable block id for any freshly-inserted card
                    // (tray drop, pattern insert). One place covers every path.
                    if (card.classList && card.classList.contains('block-card') && !card.dataset.bid) {
                        const bid = 'b' + Math.random().toString(36).slice(2, 12);
                        card.dataset.bid = bid;
                        const bidInput = card.querySelector('[data-bk="_bid"]');
                        if (bidInput) bidInput.value = bid;
                    }
                    card.querySelectorAll('[data-bk]').forEach(el => {
                        if (el.closest('.rep-item')) return;
                        el.name = el.dataset.bk === 'type'
                            ? `blocks[${i}][type]`
                            : `blocks[${i}][${el.dataset.bk.split('.').join('][')}]`;
                    });
                    card.querySelectorAll('.rep-container').forEach(rc => {
                        const rep = rc.dataset.rep;
                        [...rc.querySelectorAll('.rep-items > .rep-item')].forEach((item, j) => {
                            item.querySelectorAll('[data-rk]').forEach(el => {
                                el.name = `blocks[${i}][data][${rep}][${j}][${el.dataset.rk}]`;
                            });
                        });
                    });
                });
            }

            // The old «select+add to end» control was removed in favour of
            // drag-from-tray. Null-check so we don't crash when the elements
            // aren't in the DOM.
            document.getElementById('add-btn')?.addEventListener('click', () => {
                const type = document.getElementById('add-type')?.value;
                if (!type) return;
                const tpl = document.getElementById('tpl-' + type);
                if (!tpl) return;
                const node = tpl.content.firstElementChild.cloneNode(true);
                list.appendChild(node);
                refreshHint();
                openCard(node, true);  // expand the freshly-added block
                schedulePreview();
            });

            // Drag-and-drop:
            //  · existing block-cards reorder via DOM moves (form serialises in
            //    DOM order on submit, no index field to keep in sync)
            //  · tray cards (data-tray-type) clone the matching <template> and
            //    insert it at the drop position
            //  · pattern cards (data-pattern-id) fetch server-rendered HTML
            let draggingCard = null;
            let trayType = null;
            let trayPatternId = null;

            document.addEventListener('dragstart', (e) => {
                const patBtn = e.target.closest('[data-pattern-id]');
                if (patBtn) {
                    trayPatternId = patBtn.dataset.patternId;
                    patBtn.classList.add('opacity-60');
                    e.dataTransfer.effectAllowed = 'copy';
                    try { e.dataTransfer.setData('text/plain', 'pattern:' + trayPatternId); } catch (_) {}
                    return;
                }
                const tray = e.target.closest('[data-tray-type]');
                if (tray) {
                    trayType = tray.dataset.trayType;
                    tray.classList.add('opacity-60');
                    e.dataTransfer.effectAllowed = 'copy';
                    try { e.dataTransfer.setData('text/plain', 'tray:' + trayType); } catch (_) {}
                    return;
                }
                const card = e.target.closest('.block-card');
                if (card) {
                    draggingCard = card;
                    card.classList.add('opacity-50');
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', 'block'); } catch (_) {}
                }
            });
            document.addEventListener('dragend', () => {
                if (draggingCard) draggingCard.classList.remove('opacity-50');
                document.querySelectorAll('[data-tray-type].opacity-60, [data-pattern-id].opacity-60').forEach((el) => el.classList.remove('opacity-60'));
                list.querySelectorAll('.block-card').forEach((c) => c.classList.remove('ring-2', 'ring-accent-500'));
                document.querySelectorAll('.tray-drop-preview').forEach((el) => el.remove());
                draggingCard = null;
                trayType = null;
                trayPatternId = null;
            });

            dropZone.addEventListener('dragover', (e) => {
                if (!draggingCard && !trayType && !trayPatternId) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = (trayType || trayPatternId) ? 'copy' : 'move';
                const target = e.target.closest('.block-card');

                if (draggingCard) {
                    if (!target || target === draggingCard) return;
                    const rect = target.getBoundingClientRect();
                    const after = (e.clientY - rect.top) > (rect.height / 2);
                    target.parentNode.insertBefore(draggingCard, after ? target.nextSibling : target);
                    return;
                }

                document.querySelectorAll('.tray-drop-preview').forEach((el) => el.remove());
                const indicator = document.createElement('div');
                indicator.className = 'tray-drop-preview h-1 rounded-full bg-accent-500';
                if (target) {
                    const rect = target.getBoundingClientRect();
                    const after = (e.clientY - rect.top) > (rect.height / 2);
                    target.parentNode.insertBefore(indicator, after ? target.nextSibling : target);
                } else {
                    list.appendChild(indicator);
                }
            });

            dropZone.addEventListener('dragenter', (e) => {
                if (!draggingCard) return;
                const target = e.target.closest('.block-card');
                if (target && target !== draggingCard) target.classList.add('ring-2', 'ring-accent-500');
            });
            dropZone.addEventListener('dragleave', (e) => {
                if (!draggingCard) return;
                const target = e.target.closest('.block-card');
                if (target) target.classList.remove('ring-2', 'ring-accent-500');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                if (trayType) {
                    const tpl = document.getElementById('tpl-' + trayType);
                    if (tpl) {
                        const node = tpl.content.firstElementChild.cloneNode(true);
                        const indicator = document.querySelector('.tray-drop-preview');
                        if (indicator) {
                            indicator.replaceWith(node);
                        } else {
                            list.appendChild(node);
                        }
                        refreshHint();
                        openCard(node, true);  // expand the freshly-dropped block
                        schedulePreview();
                    }
                }
                if (trayPatternId) {
                    const id = trayPatternId;
                    const indicator = document.querySelector('.tray-drop-preview');
                    const placeholder = document.createElement('div');
                    placeholder.className = 'pattern-loading rounded-card border border-dashed border-accent-300 bg-accent-50 p-4 text-center text-xs text-accent-700';
                    placeholder.textContent = 'در حال بارگذاری الگو…';
                    if (indicator) indicator.replaceWith(placeholder); else list.appendChild(placeholder);
                    fetch(`/admin/patterns/${id}/render`, { headers: { 'Accept': 'application/json' } })
                        .then((r) => r.json())
                        .then((json) => {
                            if (!json.ok || !json.html) { placeholder.remove(); alert('بارگذاری الگو ناموفق بود.'); return; }
                            const tmp = document.createElement('div');
                            tmp.innerHTML = json.html;
                            const nodes = [...tmp.children];
                            nodes.forEach((n) => placeholder.parentNode.insertBefore(n, placeholder));
                            placeholder.remove();
                            schedulePreview();
                            refreshHint();
                            if (window.rteScan) window.rteScan();
                        })
                        .catch(() => { placeholder.remove(); alert('خطا در بارگذاری الگو.'); });
                }
                if (draggingCard) draggingCard.classList.remove('opacity-50');
                document.querySelectorAll('[data-tray-type].opacity-60, [data-pattern-id].opacity-60').forEach((el) => el.classList.remove('opacity-60'));
                list.querySelectorAll('.block-card').forEach((c) => c.classList.remove('ring-2', 'ring-accent-500'));
                document.querySelectorAll('.tray-drop-preview').forEach((el) => el.remove());
                draggingCard = null;
                trayType = null;
                trayPatternId = null;
            });

            list.addEventListener('click', (e) => {
                if (e.target.classList.contains('rep-add')) {
                    const rc = e.target.closest('.rep-container');
                    const tpl = rc.querySelector('.rep-tpl');
                    rc.querySelector('.rep-items').appendChild(tpl.content.firstElementChild.cloneNode(true));
                    return;
                }
                if (e.target.classList.contains('rep-del')) { e.target.closest('.rep-item').remove(); return; }
                const card = e.target.closest('.block-card');
                if (!card) return;
                if (e.target.classList.contains('b-del')) { card.remove(); refreshHint(); schedulePreview(); return; }
                if (e.target.classList.contains('b-up') && card.previousElementSibling) { card.parentNode.insertBefore(card, card.previousElementSibling); schedulePreview(); return; }
                if (e.target.classList.contains('b-down') && card.nextElementSibling) { card.parentNode.insertBefore(card.nextElementSibling, card); schedulePreview(); return; }
                if (e.target.classList.contains('b-save-pattern')) { saveCardAsPattern(card); return; }

                // Accordion toggle — clicking the header row (but not a control
                // button / select / the drag grip) expands this card and
                // collapses the others, so the editor focuses one block at a
                // time (layers-spine feel).
                if (e.target.closest('.b-head') && !e.target.closest('button, select, .b-grip')) {
                    openCard(card, card.classList.contains('is-collapsed'));
                }
            });

            // 3×3 position-grid control — clicking a cell sets the hidden
            // .pos-value input and moves the active highlight. Works for both
            // block-level and repeater (per-slide) position fields.
            list.addEventListener('click', (e) => {
                const cell = e.target.closest('.pos-cell');
                if (!cell) return;
                const ctl = cell.closest('.pos-control');
                ctl.querySelector('.pos-value').value = cell.dataset.pos;
                ctl.querySelectorAll('.pos-cell').forEach((c) => c.classList.remove('is-active'));
                cell.classList.add('is-active');
            });

            // Expand one card, collapse the rest. When `expand` is false we
            // just collapse it (toggle off).
            function openCard(card, expand) {
                if (expand) {
                    list.querySelectorAll('.block-card').forEach((c) => c.classList.add('is-collapsed'));
                    card.classList.remove('is-collapsed');
                    if (window.rteScan) window.rteScan();
                } else {
                    card.classList.add('is-collapsed');
                }
            }

            // Save a block card as a reusable pattern.
            function saveCardAsPattern(card) {
                const name = prompt('نام الگو؟ (مثلاً «بنر کشف کالکشن»)');
                if (!name) return;
                const type = card.dataset.type;
                const data = {};
                const reps = {};
                card.querySelectorAll('[data-bk]').forEach((el) => {
                    if (el.closest('.rep-item')) return;
                    const k = el.dataset.bk;
                    if (k === 'type') return;
                    const key = k.startsWith('data.') ? k.slice(5) : k;
                    data[key] = readVal(el);
                });
                card.querySelectorAll('.rep-container').forEach((rc) => {
                    const rep = rc.dataset.rep;
                    const rows = [];
                    rc.querySelectorAll('.rep-items > .rep-item').forEach((item) => {
                        const row = {};
                        item.querySelectorAll('[data-rk]').forEach((el) => { row[el.dataset.rk] = readVal(el); });
                        rows.push(row);
                    });
                    reps[rep] = rows;
                });
                Object.assign(data, reps);
                fetch(@json(route('admin.patterns.store')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ name, icon: '💾', blocks: [{ type, data }] }),
                })
                .then((r) => r.json())
                .then((json) => {
                    if (json.ok) alert('الگو ذخیره شد. صفحه را نوسازی کنید تا در فهرست ظاهر شود.');
                    else alert('ذخیرهٔ الگو ناموفق بود.');
                })
                .catch(() => alert('خطا در ذخیرهٔ الگو.'));
            }
            function readVal(el) {
                if (el.type === 'checkbox') return el.checked ? (el.value || '1') : '';
                return el.value ?? '';
            }

            // Searchable single-pickers
            list.addEventListener('focusin', (e) => {
                if (e.target.classList.contains('picker-search')) {
                    e.target.closest('[data-picker]').querySelector('.picker-list').classList.remove('hidden');
                }
            });
            list.addEventListener('input', (e) => {
                if (!e.target.classList.contains('picker-search')) return;
                const q = e.target.value.trim().toLowerCase();
                e.target.closest('[data-picker]').querySelectorAll('.picker-opt').forEach((opt) => {
                    opt.classList.toggle('hidden', q && !opt.textContent.toLowerCase().includes(q));
                });
            });
            list.addEventListener('click', (e) => {
                if (!e.target.classList.contains('picker-opt')) return;
                const picker = e.target.closest('[data-picker]');
                picker.querySelector('.picker-value').value = e.target.dataset.value;
                picker.querySelector('.picker-search').value = e.target.dataset.label;
                picker.querySelector('.picker-list').classList.add('hidden');
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-picker]')) {
                    list.querySelectorAll('.picker-list').forEach((l) => l.classList.add('hidden'));
                }
                if (!e.target.closest('[data-multipicker]')) {
                    list.querySelectorAll('.mp-list').forEach((l) => l.classList.add('hidden'));
                }
            });

            // Multi-select pickers
            function mpSync(picker) {
                const vals = [...picker.querySelectorAll('.mp-chip')].map((c) => c.dataset.value);
                picker.querySelector('.mp-value').value = vals.join(',');
                picker.querySelector('.mp-chips').classList.toggle('hidden', vals.length === 0);
            }
            function mpFilter(picker) {
                const q = picker.querySelector('.mp-search').value.trim().toLowerCase();
                const chosen = new Set([...picker.querySelectorAll('.mp-chip')].map((c) => c.dataset.value));
                picker.querySelectorAll('.mp-opt').forEach((opt) => {
                    const hide = chosen.has(opt.dataset.value) || (q && !opt.textContent.toLowerCase().includes(q));
                    opt.classList.toggle('hidden', hide);
                });
            }
            function mpAdd(picker, value, label) {
                if (!value) return;
                if ([...picker.querySelectorAll('.mp-chip')].some((c) => c.dataset.value === value)) return;
                const span = document.createElement('span');
                span.className = 'mp-chip inline-flex items-center gap-1 rounded-full bg-accent-50 px-2.5 py-1 text-xs font-medium text-accent-700 ring-1 ring-accent-200';
                span.dataset.value = value;
                span.innerHTML = '<span class="mp-chip-label"></span><button type="button" class="mp-chip-del leading-none text-accent-400 hover:text-accent-700" aria-label="حذف">×</button>';
                span.querySelector('.mp-chip-label').textContent = label;
                picker.querySelector('.mp-chips').appendChild(span);
                picker.querySelector('.mp-search').value = '';
                mpSync(picker);
                mpFilter(picker);
            }
            list.addEventListener('focusin', (e) => {
                if (!e.target.classList.contains('mp-search')) return;
                const picker = e.target.closest('[data-multipicker]');
                picker.querySelector('.mp-list').classList.remove('hidden');
                mpFilter(picker);
            });
            list.addEventListener('input', (e) => {
                if (e.target.classList.contains('mp-search')) mpFilter(e.target.closest('[data-multipicker]'));
            });
            list.addEventListener('keydown', (e) => {
                if (!e.target.classList.contains('mp-search') || e.key !== 'Enter') return;
                e.preventDefault();
                const picker = e.target.closest('[data-multipicker]');
                const opt = picker.querySelector('.mp-opt:not(.hidden)');
                if (opt) mpAdd(picker, opt.dataset.value, opt.dataset.label);
            });
            list.addEventListener('click', (e) => {
                if (e.target.classList.contains('mp-opt')) {
                    const picker = e.target.closest('[data-multipicker]');
                    mpAdd(picker, e.target.dataset.value, e.target.dataset.label);
                    picker.querySelector('.mp-search').focus();
                    return;
                }
                if (e.target.classList.contains('mp-chip-del')) {
                    const picker = e.target.closest('[data-multipicker]');
                    e.target.closest('.mp-chip').remove();
                    mpSync(picker);
                    mpFilter(picker);
                }
            });

            // Image uploads — listen on the whole form so it covers both block
            // image fields (inside #blocks-list) AND the page-background image
            // field (in the page-meta section, outside the list).
            pageForm.addEventListener('change', async (e) => {
                if (!e.target.classList.contains('img-file')) return;
                const file = e.target.files[0];
                if (!file) return;
                const field = e.target.closest('.img-field');
                const urlInput = field.querySelector('.img-url');
                const preview = field.querySelector('.img-preview');
                const clearBtn = field.querySelector('.img-clear');
                // Client-side guard: the server rejects images over 4 MB, so say so
                // up front instead of a mystery failure.
                const MAX_MB = 4;
                if (file.size > MAX_MB * 1024 * 1024) {
                    preview.textContent = 'خطا';
                    alert(`حجم این تصویر ${(file.size / 1048576).toFixed(1)} مگابایت است؛ حداکثر مجاز ${MAX_MB} مگابایت.\nلطفاً تصویر را فشرده یا کوچک‌تر کنید و دوباره تلاش کنید.`);
                    e.target.value = '';
                    return;
                }
                preview.innerHTML = '...';
                const fd = new FormData(); fd.append('file', file);
                try {
                    const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd });
                    let json = null;
                    try { json = await res.json(); } catch (_) { /* non-JSON error page */ }

                    if (!res.ok || !json || !json.url) {
                        let msg;
                        if (res.status === 413)      msg = 'حجم فایل بیش از حد مجاز سرور است (خطای ۴۱۳). تصویر را کوچک‌تر کنید.';
                        else if (res.status === 419) msg = 'نشست شما منقضی شده (خطای ۴۱۹). صفحه را تازه کنید و دوباره وارد شوید.';
                        else if (json && json.errors)  msg = Object.values(json.errors).flat().join('\n');
                        else if (json && json.message) msg = json.message;
                        else                           msg = `خطای سرور (کد ${res.status || '؟'})`;
                        throw new Error(msg);
                    }

                    urlInput.value = json.url;
                    schedulePreview();
                    preview.innerHTML = `<img src="${json.url}" class="h-full w-full object-cover">`;
                    if (clearBtn) clearBtn.classList.remove('hidden');
                } catch (err) {
                    urlInput.value = '';
                    preview.textContent = 'خطا';
                    alert('بارگذاری تصویر ناموفق بود:\n\n' + (err.message || err));
                } finally {
                    e.target.value = ''; // let the same file be re-picked after a fix
                }
            });
            pageForm.addEventListener('click', (e) => {
                if (!e.target.classList.contains('img-clear')) return;
                const field = e.target.closest('.img-field');
                field.querySelector('.img-url').value = '';
                field.querySelector('.img-preview').textContent = 'بدون تصویر';
                e.target.classList.add('hidden');
                schedulePreview();
            });

            document.getElementById('page-form').addEventListener('submit', reconstruct);
        })();
    </script>
@endsection
