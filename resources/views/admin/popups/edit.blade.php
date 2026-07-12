@extends('admin.layout')

@section('title', $popup->exists ? 'ویرایش پاپ‌آپ' : 'پاپ‌آپ جدید')

@section('content')
    @php
        // Stored as one `html` column with an optional <style data-popup-css>
        // block. Split it into an HTML body + CSS for editing; the controller
        // recombines them on save. On a validation redirect, `html`/`css` come
        // back as separate old() fields (already split).
        $stored = $popup->html ?? '';
        $storedCss = '';
        $storedHtml = $stored;
        if (preg_match('/<style\s+data-popup-css\s*>(.*?)<\/style>\s*/is', $stored, $m)) {
            $storedCss = trim($m[1]);
            $storedHtml = trim(preg_replace('/<style\s+data-popup-css\s*>.*?<\/style>\s*/is', '', $stored, 1));
        }
        $seedHtml = old('html', $storedHtml);
        $seedCss = old('css', $storedCss);
    @endphp
    <form method="POST" action="{{ $popup->exists ? route('admin.popups.update', $popup) : route('admin.popups.store') }}">
        @csrf
        @if ($popup->exists) @method('PUT') @endif

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-brand-900">{{ $popup->exists ? 'ویرایش پاپ‌آپ' : 'پاپ‌آپ جدید' }}</h1>
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">ذخیره</button>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Settings + HTML editor --}}
            <div class="space-y-4">
                <div class="grid gap-4 rounded-card bg-white p-5 ring-1 ring-brand-100 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-brand-700">نام پاپ‌آپ</label>
                        <input type="text" name="name" value="{{ old('name', $popup->name) }}" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-brand-700">زمان نمایش</label>
                        <select name="trigger" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                            @foreach (\App\Models\Popup::TRIGGERS as $k => $label)<option value="{{ $k }}" @selected(old('trigger', $popup->trigger) === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-brand-700">تأخیر (ثانیه)</label>
                        <input type="number" name="delay" min="0" max="120" value="{{ old('delay', $popup->delay) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-brand-700">تکرار نمایش</label>
                        <select name="frequency" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                            @foreach (\App\Models\Popup::FREQUENCIES as $k => $label)<option value="{{ $k }}" @selected(old('frequency', $popup->frequency) === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-brand-700">صفحات</label>
                        <select name="pages" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                            @foreach (\App\Models\Popup::PAGES as $k => $label)<option value="{{ $k }}" @selected(old('pages', $popup->pages) === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-brand-700 sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $popup->is_active ?? true))> فعال</label>
                </div>

                <div class="space-y-4 rounded-card bg-white p-5 ring-1 ring-brand-100">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" data-ins-img class="rounded-lg bg-brand-900 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">＋ آپلود تصویر</button>
                        <button type="button" data-expand class="ms-auto rounded-lg bg-brand-50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-100">⤢ پیش‌نمایش تمام‌صفحه</button>
                        <input type="file" accept="image/*" id="popup-img-input" class="hidden">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-brand-700">محتوای پاپ‌آپ</label>
                        {{-- Same dual-view (بصری / کد HTML) editor as the product form.
                             Submits the body HTML as `html`. --}}
                        @include('admin.partials.html-editor', ['name' => 'html', 'value' => $seedHtml, 'rows' => 14, 'placeholder' => 'محتوای پاپ‌آپ…'])
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-brand-700">CSS (اختیاری)</label>
                        <textarea id="popup-css" name="css" dir="ltr" spellcheck="false" rows="10"
                                  class="w-full resize-y rounded-lg border border-brand-200 px-3 py-2 font-mono text-xs leading-relaxed"
                                  style="white-space:pre;overflow-wrap:normal;overflow-x:auto;tab-size:2"
                                  placeholder=".promo{ text-align:center; padding:24px }">{{ $seedCss }}</textarea>
                        <p class="mt-1 text-xs text-brand-400">استایل‌ها هنگام ذخیره به‌صورت خودکار داخل <code dir="ltr">&lt;style&gt;</code> قرار می‌گیرند. (کلید Tab دو فاصله وارد می‌کند.)</p>
                    </div>
                    <p class="text-xs text-brand-400">برای بستن، دکمه‌ای با <code dir="ltr">data-popup-close</code> بگذارید.</p>
                </div>
            </div>

            {{-- Live HTML viewer --}}
            <div class="lg:sticky lg:top-4 lg:self-start">
                <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
                    <p class="mb-2 text-sm font-medium text-brand-700">پیش‌نمایش زنده</p>
                    <div class="rounded-xl bg-brand-100 p-4">
                        <iframe id="popup-preview" class="h-[420px] w-full rounded-lg bg-white ring-1 ring-brand-200" title="پیش‌نمایش پاپ‌آپ"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($popup->exists)
        <form method="POST" action="{{ route('admin.popups.destroy', $popup) }}" class="mt-6" onsubmit="return confirm('این پاپ‌آپ حذف شود؟')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-500 hover:underline">حذف پاپ‌آپ</button>
        </form>
    @endif

    {{-- Full-size preview overlay — see the popup at real page dimensions. --}}
    <div id="popup-fs" class="fixed inset-0 z-[80] hidden bg-brand-950/70 p-3 sm:p-6">
        <div class="mx-auto flex h-full max-w-5xl flex-col">
            <div class="mb-2 flex items-center justify-between text-white">
                <span class="text-sm font-semibold">پیش‌نمایش تمام‌صفحه</span>
                <button type="button" data-fs-close class="rounded-lg bg-white/15 px-3 py-1.5 text-sm transition hover:bg-white/25">بستن ✕</button>
            </div>
            <iframe id="popup-fs-frame" class="w-full flex-1 rounded-xl bg-white ring-1 ring-white/20" title="پیش‌نمایش تمام‌صفحه"></iframe>
        </div>
    </div>

    <script>
        (function () {
            // The rich editor (بصری/کد HTML) owns the HTML; its source textarea
            // (name="html") is what submits. CSS is a separate pane. The live
            // preview merges the two; the controller merges them the same way
            // on save (a <style data-popup-css> block).
            const rteSource = document.querySelector('[data-rte-source]');
            const rteVisual = document.querySelector('[data-rte-visual]');
            const css = document.getElementById('popup-css');
            const frame = document.getElementById('popup-preview');
            const fs = document.getElementById('popup-fs');
            const fsFrame = document.getElementById('popup-fs-frame');
            const expandBtn = document.querySelector('[data-expand]');
            const form = css ? css.closest('form') : null;
            if (!rteSource || !frame) return;

            function build() {
                const c = (css.value || '').trim();
                return (c ? '<style data-popup-css>\n' + c + '\n</style>\n' : '') + (rteSource.value || '');
            }
            function render() {
                const v = build().trim();
                if (/<\s*(!doctype|html|body)\b/i.test(v)) {
                    frame.srcdoc = v;
                } else {
                    frame.srcdoc = '<!doctype html><html dir="rtl"><head><meta charset="utf-8">' +
                        '<base href="' + location.origin + '/">' +
                        '<style>body{font-family:Tahoma,sans-serif;margin:0;display:grid;place-items:center;min-height:100%;padding:16px;box-sizing:border-box}img{max-width:100%}</style>' +
                        '</head><body>' + (v || '<p style="color:#999">پیش‌نمایش اینجا نمایش داده می‌شود…</p>') + '</body></html>';
                }
                if (fs && fsFrame && !fs.classList.contains('hidden')) fsFrame.srcdoc = frame.srcdoc;
            }

            // CSS pane: Tab inserts two spaces; live-render on input.
            css.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab' || e.shiftKey) return;
                e.preventDefault();
                const s = css.selectionStart, en = css.selectionEnd;
                css.value = css.value.slice(0, s) + '  ' + css.value.slice(en);
                css.selectionStart = css.selectionEnd = s + 2; render();
            });
            css.addEventListener('input', render);
            // The RTE syncs its source on visual input; also fires on code-mode typing.
            if (rteVisual) rteVisual.addEventListener('input', render);
            rteSource.addEventListener('input', render);

            // Upload a photo → insert into the editor (visual pane if active, else source).
            var imgBtn = document.querySelector('[data-ins-img]');
            var imgInput = document.getElementById('popup-img-input');
            var token = form ? (form.querySelector('input[name=_token]') || {}).value : '';
            if (imgBtn && imgInput) {
                imgBtn.addEventListener('click', function () { imgInput.click(); });
                imgInput.addEventListener('change', function () {
                    var file = imgInput.files && imgInput.files[0];
                    if (!file) return;
                    var fd = new FormData(); fd.append('files[]', file);
                    var lbl = imgBtn.textContent; imgBtn.disabled = true; imgBtn.textContent = '… در حال آپلود';
                    fetch('{{ route('admin.media.upload') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            var u = d && d.files && d.files[0] && d.files[0].url;
                            if (!u) throw new Error('no url');
                            var abs = /^https?:/i.test(u) ? u : location.origin + u;
                            var img = '<img src="' + abs + '" alt="" style="max-width:100%;height:auto;display:block">';
                            if (rteVisual && !rteVisual.classList.contains('hidden')) {
                                rteVisual.focus();
                                document.execCommand('insertHTML', false, img);
                                rteVisual.dispatchEvent(new Event('input')); // RTE syncs source, we re-render
                            } else {
                                rteSource.value += img; render();
                            }
                        })
                        .catch(function () { alert('آپلود تصویر ناموفق بود.'); })
                        .finally(function () { imgBtn.disabled = false; imgBtn.textContent = lbl; imgInput.value = ''; });
                });
            }

            // Full-size preview overlay.
            if (expandBtn && fs) {
                expandBtn.addEventListener('click', function () { if (fsFrame) fsFrame.srcdoc = frame.srcdoc; fs.classList.remove('hidden'); });
                fs.querySelector('[data-fs-close]').addEventListener('click', function () { fs.classList.add('hidden'); });
                fs.addEventListener('click', function (e) { if (e.target === fs) fs.classList.add('hidden'); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fs.classList.add('hidden'); });
            }

            render();
        })();
    </script>
@endsection
