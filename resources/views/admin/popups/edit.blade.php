@extends('admin.layout')

@section('title', $popup->exists ? 'ویرایش پاپ‌آپ' : 'پاپ‌آپ جدید')

@section('content')
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

                <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
                    <label class="mb-1 block text-sm font-medium text-brand-700">کد HTML پاپ‌آپ</label>
                    <textarea id="popup-html" name="html" rows="14" dir="ltr" class="w-full rounded-lg border border-brand-200 px-3 py-2 font-mono text-xs" placeholder="&lt;div&gt;...&lt;/div&gt;">{{ old('html', $popup->html) }}</textarea>
                    <p class="mt-1 text-xs text-brand-400">هر HTML دلخواه (تصویر، متن، دکمه با لینک). برای بستن از دکمه‌ای با <code dir="ltr">data-popup-close</code> استفاده کنید.</p>
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

    <script>
        (function () {
            const ta = document.getElementById('popup-html');
            const frame = document.getElementById('popup-preview');
            function render() {
                const v = (ta.value || '').trim();
                // A pasted full HTML document renders as-is (its own <style>/<head>);
                // a plain snippet gets wrapped in a minimal centred shell. This
                // matches exactly how the live popup renders it.
                if (/<\s*(!doctype|html|head|body|style)\b/i.test(v)) {
                    frame.srcdoc = v;
                } else {
                    frame.srcdoc = '<!doctype html><html dir="rtl"><head><meta charset="utf-8">' +
                        '<style>body{font-family:Tahoma,sans-serif;margin:0;display:grid;place-items:center;min-height:100%;padding:16px;box-sizing:border-box}img{max-width:100%}</style>' +
                        '</head><body>' + (v || '<p style="color:#999">پیش‌نمایش اینجا نمایش داده می‌شود…</p>') + '</body></html>';
                }
            }
            ta.addEventListener('input', render);
            render();
        })();
    </script>
@endsection
