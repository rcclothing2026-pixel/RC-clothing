@extends('admin.layout')

@section('title', 'فونت‌ها')

@php
    $bodyFamily = old('body_family', $settings['site.font_body_family'] ?? '');
    $headFamily = old('heading_family', $settings['site.font_heading_family'] ?? '');
    $basePx     = old('base_px', $settings['site.font_base_px'] ?? '');
    $bodyUrl    = $settings['site.font_body_url'] ?? '';
    $headUrl    = $settings['site.font_heading_url'] ?? '';
    $baseName   = fn ($u) => $u ? rawurldecode(basename(parse_url($u, PHP_URL_PATH))) : '';
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">فونت‌ها</h1>
        <p class="mt-1 text-sm text-brand-500">فونت متن و عنوان‌ها را انتخاب یا آپلود کنید و اندازهٔ پایهٔ متن را تنظیم کنید. برای بازگشت به حالت پیش‌فرض، «بازگردانی به پیش‌فرض» را بزنید.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.fonts.update') }}" enctype="multipart/form-data"
          class="max-w-3xl space-y-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf

        @if ($errors->any())
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Body font --}}
        <fieldset class="space-y-3">
            <legend class="text-sm font-bold text-brand-800">فونت متن (بدنه)</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-700">نام فونت</label>
                    <input id="f-body-family" type="text" name="body_family" value="{{ $bodyFamily }}"
                           placeholder="Vazirmatn (پیش‌فرض)"
                           class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-brand-400">خالی = فونت پیش‌فرض سایت (وزیرمتن).</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-700">آپلود فایل فونت</label>
                    <input id="f-body-file" type="file" name="body_file" accept=".woff2,.woff,.ttf,.otf"
                           class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-900 file:px-3 file:py-1 file:text-xs file:text-white">
                    <p class="mt-1 text-xs text-brand-400">woff2 / woff / ttf / otf — حداکثر ۴ مگابایت.</p>
                </div>
            </div>
            @if ($bodyUrl)
                <label class="flex items-center gap-2 text-xs text-brand-600">
                    <span class="rounded bg-brand-50 px-2 py-1 ring-1 ring-brand-100">فایل فعلی: {{ $baseName($bodyUrl) }}</span>
                    <input type="checkbox" name="body_reset" value="1" class="rounded border-brand-300"> بازگردانی به پیش‌فرض
                </label>
            @endif
        </fieldset>

        {{-- Heading font --}}
        <fieldset class="space-y-3 border-t border-brand-100 pt-5">
            <legend class="text-sm font-bold text-brand-800">فونت عنوان‌ها</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-700">نام فونت</label>
                    <input id="f-head-family" type="text" name="heading_family" value="{{ $headFamily }}"
                           placeholder="Mansory (پیش‌فرض)"
                           class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-brand-700">آپلود فایل فونت</label>
                    <input id="f-head-file" type="file" name="heading_file" accept=".woff2,.woff,.ttf,.otf"
                           class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-900 file:px-3 file:py-1 file:text-xs file:text-white">
                </div>
            </div>
            @if ($headUrl)
                <label class="flex items-center gap-2 text-xs text-brand-600">
                    <span class="rounded bg-brand-50 px-2 py-1 ring-1 ring-brand-100">فایل فعلی: {{ $baseName($headUrl) }}</span>
                    <input type="checkbox" name="heading_reset" value="1" class="rounded border-brand-300"> بازگردانی به پیش‌فرض
                </label>
            @endif
        </fieldset>

        {{-- Base size --}}
        <fieldset class="space-y-2 border-t border-brand-100 pt-5">
            <legend class="text-sm font-bold text-brand-800">اندازهٔ پایهٔ متن</legend>
            <div class="flex items-center gap-3">
                <input id="f-base" type="number" name="base_px" min="10" max="32" step="1" value="{{ $basePx }}"
                       placeholder="۱۶"
                       class="w-28 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <span class="text-sm text-brand-500">پیکسل (px)</span>
            </div>
            <p class="text-xs text-brand-400">کل سایت با این اندازه به‌صورت نسبی بزرگ/کوچک می‌شود (چون همه چیز بر پایهٔ rem است). خالی = پیش‌فرض ۱۶.</p>
        </fieldset>

        {{-- Live preview --}}
        <div class="border-t border-brand-100 pt-5">
            <p class="mb-2 text-sm font-bold text-brand-800">پیش‌نمایش زنده</p>
            <div id="f-preview" class="space-y-2 rounded-lg bg-brand-50 p-5 ring-1 ring-brand-100">
                <div id="f-preview-head" class="text-2xl font-bold text-brand-900">راکت کلاب — Racket Club 1234</div>
                <p id="f-preview-body" class="text-brand-700">نمونه متن فارسی برای بررسی فونت. The quick brown fox 0123456789.</p>
            </div>
        </div>

        <div class="border-t border-brand-100 pt-5">
            <button type="submit" class="rounded-lg bg-brand-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-800">ذخیره</button>
        </div>
    </form>

    {{-- Instant preview: reflect typed family names, uploaded files (via the
         FontFace API), and base size — before saving. Progressive enhancement. --}}
    <script>
    (function () {
        var head = document.getElementById('f-preview-head');
        var body = document.getElementById('f-preview-body');
        var wrap = document.getElementById('f-preview');
        var fallback = ', ui-sans-serif, system-ui, sans-serif';
        function q(name) { return name ? '"' + name.replace(/["\\]/g, '') + '"' + fallback : ''; }

        function bindFamily(inputId, el, dflt) {
            var i = document.getElementById(inputId);
            if (!i) return;
            var apply = function () { el.style.fontFamily = q(i.value.trim()) || dflt; };
            i.addEventListener('input', apply); apply();
        }
        bindFamily('f-body-family', body, 'inherit');
        bindFamily('f-head-family', head, 'inherit');

        function bindFile(fileId, familyId, el) {
            var f = document.getElementById(fileId);
            if (!f || !window.FontFace) return;
            f.addEventListener('change', function () {
                var file = f.files && f.files[0];
                if (!file) return;
                var url = URL.createObjectURL(file);
                var fam = 'preview_' + fileId;
                var face = new FontFace(fam, 'url(' + url + ')');
                face.load().then(function (loaded) {
                    document.fonts.add(loaded);
                    el.style.fontFamily = '"' + fam + '"' + fallback;
                    var fi = document.getElementById(familyId);
                    if (fi && !fi.value.trim()) { fi.value = file.name.replace(/\.[^.]+$/, ''); fi.dispatchEvent(new Event('input')); el.style.fontFamily = '"' + fam + '"' + fallback; }
                }).catch(function () {});
            });
        }
        bindFile('f-body-file', 'f-body-family', body);
        bindFile('f-head-file', 'f-head-family', head);

        var base = document.getElementById('f-base');
        if (base) {
            var applyBase = function () {
                var v = parseInt(base.value, 10);
                wrap.style.fontSize = (v >= 10 && v <= 32) ? v + 'px' : '';
            };
            base.addEventListener('input', applyBase); applyBase();
        }
    })();
    </script>
@endsection
