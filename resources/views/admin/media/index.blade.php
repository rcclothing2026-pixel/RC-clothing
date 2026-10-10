@php use Illuminate\Support\Str; @endphp
@extends('admin.layout')

@section('title', 'کتابخانه رسانه')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6" x-data="mediaLibrary()">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">کتابخانه رسانه</h1>
        <label class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded cursor-pointer text-sm font-medium">
            <span>آپلود فایل</span>
            <input type="file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" class="hidden"
                   @change="uploadFiles($event.target.files)">
        </label>
    </div>

    <div class="flex items-center gap-4 mb-6 flex-wrap">
        <input type="text" x-model="search" placeholder="جستجو..." class="border rounded px-3 py-2 w-64 text-sm"
               @input.debounce="doSearch">
        <select x-model="typeFilter" class="border rounded px-3 py-2 text-sm" @change="doSearch">
            <option value="">همه فایل‌ها</option>
            <option value="image">تصاویر</option>
            <option value="document">اسناد</option>
        </select>

        <template x-if="selectedIds.length">
            <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm"
                    @click="bulkDelete">حذف انتخاب شده (<span x-text="selectedIds.length"></span>)</button>
        </template>
    </div>

    <div id="upload-progress" class="hidden mb-4">
        <div class="bg-gray-200 rounded-full h-2">
            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" id="progress-bar" style="width:0"></div>
        </div>
        <p class="text-xs text-gray-500 mt-1" id="progress-text">در حال آپلود...</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($media as $item)
        <a href="{{ route('admin.media.show', $item) }}"
           class="group relative bg-white rounded-lg border overflow-hidden hover:shadow-md transition-shadow"
           :class="{ 'ring-2 ring-blue-500': selectedIds.includes({{ $item->id }}) }">
            <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                @if($item->is_image)
                <img src="{{ $item->url }}" alt="{{ $item->alt ?: $item->original_name }}"
                     class="w-full h-full object-cover">
                @else
                <div class="text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-xs">{{ Str::upper(pathinfo($item->original_name, PATHINFO_EXTENSION)) }}</span>
                </div>
                @endif
            </div>
            <div class="p-2">
                <p class="text-xs truncate">{{ $item->original_name }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $item->file_size_formatted }}</p>
            </div>
            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
                 @click.prevent.stop="toggleSelect({{ $item->id }})">
                <div class="w-5 h-5 rounded border-2 border-white bg-white/80 flex items-center justify-center"
                     :class="{ 'bg-blue-600 border-blue-600': selectedIds.includes({{ $item->id }}) }">
                    <svg x-show="selectedIds.includes({{ $item->id }})" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </a>
        @empty
        <div class="col-span-full text-center py-16 text-gray-400">
            <p class="text-lg">هیچ فایلی یافت نشد</p>
            <p class="text-sm mt-1">فایل‌های خود را آپلود کنید</p>
        </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $media->links() }}
    </div>
</div>

@push('scripts')
<script>
function mediaLibrary() {
    return {
        search: '{{ request('q') }}',
        typeFilter: '{{ request('type') }}',
        selectedIds: [],
        uploading: false,

        doSearch() {
            const params = new URLSearchParams();
            if (this.search) params.set('q', this.search);
            if (this.typeFilter) params.set('type', this.typeFilter);
            window.location.href = '{{ route('admin.media.index') }}' + '?' + params.toString();
        },

        uploadFiles(files) {
            if (!files.length) return;
            this.uploading = true;
            const pb = document.getElementById('upload-progress');
            const bar = document.getElementById('progress-bar');
            const txt = document.getElementById('progress-text');
            pb.classList.remove('hidden');

            const formData = new FormData();
            for (const f of files) formData.append('files[]', f);

            fetch('{{ route('admin.media.upload') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData,
            }).then(r => r.json()).then(data => {
                bar.style.width = '100%';
                txt.textContent = 'آپلود کامل شد.';
                setTimeout(() => window.location.reload(), 500);
            }).catch(() => {
                txt.textContent = 'خطا در آپلود.';
            });
        },

        toggleSelect(id) {
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) this.selectedIds.splice(idx, 1);
            else this.selectedIds.push(id);
        },

        bulkDelete() {
            if (!this.selectedIds.length) return;
            if (!confirm('آیا از حذف ' + this.selectedIds.length + ' فایل انتخاب شده اطمینان دارید؟')) return;

            fetch('{{ route('admin.media.bulk-delete') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: this.selectedIds, force: false }),
            }).then(r => r.json()).then(data => {
                if (!data.ok && data.references) {
                    let msg = 'این فایل‌ها در حال استفاده هستند:\n';
                    data.references.forEach(r => msg += '\n- ' + r.label + ' (' + r.type + ')');
                    msg += '\n\nآیا برای حذف مجدداً تأیید می‌کنید؟';
                    if (!confirm(msg)) return;
                    return fetch('{{ route('admin.media.bulk-delete') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ ids: this.selectedIds, force: true }),
                    }).then(r => r.json());
                }
                return data;
            }).then(data => {
                if (data.ok) window.location.reload();
                else alert('خطا: ' + (data.error || 'مشخص نیست'));
            });
        },
    };
}
</script>
@endpush
@endsection
