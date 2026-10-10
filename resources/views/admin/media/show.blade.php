@extends('admin.layout')

@section('title', $media->original_name . ' — کتابخانه رسانه')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="mb-4">
        <a href="{{ route('admin.media.index') }}" class="text-blue-600 hover:underline text-sm">&larr; بازگشت به کتابخانه</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg border overflow-hidden">
                @if($media->is_image)
                <img src="{{ $media->url }}" alt="{{ $media->alt ?: $media->original_name }}"
                     class="w-full h-auto" id="preview-image">
                @else
                <div class="flex items-center justify-center h-64 bg-gray-100 text-gray-400">
                    <div class="text-center">
                        <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-lg">{{ strtoupper(pathinfo($media->original_name, PATHINFO_EXTENSION)) }}</p>
                    </div>
                </div>
                @endif
            </div>

            @if($media->is_image)
            <div class="mt-4 bg-white rounded-lg border p-4" x-data="imageEditor()">
                <h3 class="font-semibold mb-3">ویرایش تصویر</h3>

                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">برش — X</label>
                        <input type="number" x-model="crop.x" min="0" placeholder="0" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">برش — Y</label>
                        <input type="number" x-model="crop.y" min="0" placeholder="0" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">عرض</label>
                        <input type="number" x-model="crop.width" min="1" placeholder="عرض" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">ارتفاع</label>
                        <input type="number" x-model="crop.height" min="1" placeholder="ارتفاع" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div class="flex items-end">
                        <button @click="cropImage" :disabled="loading"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm disabled:opacity-50 w-full">
                            اعمال برش
                        </button>
                    </div>
                    <div class="flex items-end">
                        <button @click="resetCrop"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded text-sm w-full">
                            Reset
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">تغییر اندازه — عرض</label>
                        <input type="number" x-model="resize.width" min="1" placeholder="عرض جدید" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">ارتفاع (اختیاری)</label>
                        <input type="number" x-model="resize.height" min="1" placeholder="ارتفاع جدید" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div class="flex items-end">
                        <button @click="resizeImage" :disabled="loading"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm disabled:opacity-50 w-full">
                            تغییر اندازه
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">چرخش (درجه)</label>
                        <input type="number" x-model="rotate.degrees" placeholder="90" class="border rounded px-2 py-1 w-full text-sm">
                    </div>
                    <div class="flex gap-2 items-end">
                        <button @click="rotateImage(-90)" :disabled="loading"
                                class="bg-gray-200 hover:bg-gray-300 px-3 py-1.5 rounded text-sm disabled:opacity-50">
                            ↺ ۹۰-
                        </button>
                        <button @click="rotateImage(90)" :disabled="loading"
                                class="bg-gray-200 hover:bg-gray-300 px-3 py-1.5 rounded text-sm disabled:opacity-50">
                            ↻ ۹۰
                        </button>
                    </div>
                    <div class="flex items-end">
                        <button @click="rotateCustom" :disabled="loading"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm disabled:opacity-50 w-full">
                            چرخش
                        </button>
                    </div>
                </div>

                <p x-show="loading" class="text-sm text-blue-600 mt-2">در حال پردازش...</p>
                <p x-show="error" x-text="error" class="text-sm text-red-600 mt-2"></p>
            </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-lg border p-4">
                <h3 class="font-semibold mb-3">جزئیات</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">نام فایل</dt>
                        <dd class="text-gray-800 text-left">{{ $media->original_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">نوع</dt>
                        <dd class="text-gray-800 text-left">{{ $media->mime_type }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">حجم</dt>
                        <dd class="text-gray-800 text-left">{{ $media->file_size_formatted }}</dd>
                    </div>
                    @if($media->width)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ابعاد</dt>
                        <dd class="text-gray-800 text-left">{{ $media->width }} × {{ $media->height }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="text-gray-500">آپلود</dt>
                        <dd class="text-gray-800 text-left">{{ \App\Support\Jalali::format($media->created_at, true) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-lg border p-4">
                <h3 class="font-semibold mb-3">متن جایگزین (ALT)</h3>
                <form method="POST" action="{{ route('admin.media.update', $media) }}">
                    @csrf
                    @method('PATCH')
                    <textarea name="alt" rows="2" class="border rounded w-full px-3 py-2 text-sm">{{ $media->alt }}</textarea>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-sm mt-2">
                        ذخیره
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-lg border p-4">
                <h3 class="font-semibold mb-3">URL مستقیم</h3>
                <input type="text" readonly value="{{ $media->url }}"
                       class="border rounded w-full px-3 py-2 text-sm bg-gray-50"
                       onclick="this.select(); navigator.clipboard?.writeText(this.value)">
            </div>

            <div class="bg-white rounded-lg border border-red-200 p-4">
                <h3 class="font-semibold text-red-600 mb-3">حذف</h3>
                <button onclick="deleteFile({{ $media->id }})"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm w-full">
                    حذف فایل
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function imageEditor() {
    return {
        loading: false,
        error: '',
        crop: { x: 0, y: 0, width: {{ $media->width ?? 100 }}, height: {{ $media->height ?? 100 }} },
        resize: { width: {{ $media->width ?? 800 }}, height: null },
        rotate: { degrees: 90 },

        resetCrop() {
            this.crop = { x: 0, y: 0, width: {{ $media->width ?? 100 }}, height: {{ $media->height ?? 100 }} };
        },

        async cropImage() {
            this.loading = true;
            this.error = '';
            try {
                const r = await fetch('{{ route('admin.media.crop', $media) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.crop),
                });
                const d = await r.json();
                if (!d.ok) { this.error = d.error; return; }
                document.getElementById('preview-image').src = d.url + '?t=' + Date.now();
                this.crop.width = d.width;
                this.crop.height = d.height;
            } catch(e) { this.error = 'خطا در ارتباط با سرور'; }
            finally { this.loading = false; }
        },

        async resizeImage() {
            this.loading = true;
            this.error = '';
            try {
                const r = await fetch('{{ route('admin.media.resize', $media) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.resize),
                });
                const d = await r.json();
                if (!d.ok) { this.error = d.error; return; }
                document.getElementById('preview-image').src = d.url + '?t=' + Date.now();
                this.resize.width = d.width;
            } catch(e) { this.error = 'خطا در ارتباط با سرور'; }
            finally { this.loading = false; }
        },

        async rotateImage(deg) {
            this.loading = true;
            this.error = '';
            try {
                const r = await fetch('{{ route('admin.media.rotate', $media) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ degrees: deg }),
                });
                const d = await r.json();
                if (!d.ok) { this.error = d.error; return; }
                document.getElementById('preview-image').src = d.url + '?t=' + Date.now();
            } catch(e) { this.error = 'خطا در ارتباط با سرور'; }
            finally { this.loading = false; }
        },

        async rotateCustom() {
            await this.rotateImage(this.rotate.degrees);
        },
    };
}

function deleteFile(id) {
    if (!confirm('آیا از حذف این فایل اطمینان دارید؟')) return;

    fetch('{{ route('admin.media.destroy', $media) }}'.replace(/\/[^/]+$/, '/' + id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: JSON.stringify({ force: false }),
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            window.location.href = '{{ route('admin.media.index') }}';
        } else if (data.references) {
            let msg = 'این فایل در موارد زیر استفاده شده است:\n';
            data.references.forEach(r => msg += '\n- ' + r.label + ' (' + r.type + ')');
            msg += '\n\nآیا برای حذف مجدداً تأیید می‌کنید؟';
            if (!confirm(msg)) return;
            return fetch('{{ route('admin.media.destroy', '') }}/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                body: JSON.stringify({ force: true }),
            }).then(r => r.json());
        } else {
            alert('خطا: ' + (data.error || 'مشخص نیست'));
        }
    }).then(data => {
        if (data?.ok) window.location.href = '{{ route('admin.media.index') }}';
    });
}
</script>
@endpush
@endsection
