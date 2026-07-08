{{-- One menu row. Props: $item, $depth (0 = top-level, 1 = child) --}}
<div class="rounded-xl border bg-white p-3 shadow-sm {{ $depth ? 'ms-6 border-dashed border-brand-200 bg-brand-50/60' : 'border-brand-100' }}">
    @if ($depth)
        <div class="mb-2 flex items-center gap-1 text-[11px] font-medium text-brand-400">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            زیرمنو
        </div>
    @endif

    <form action="{{ route('admin.menus.update', $item) }}" method="POST" class="space-y-2" enctype="multipart/form-data">
        @csrf @method('PUT')

        {{-- Label --}}
        <div>
            <label class="mb-1 block text-[11px] font-medium text-brand-500">عنوان</label>
            <input type="text" name="label" value="{{ $item->label }}" required
                class="w-full rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm font-medium text-brand-900 focus:border-accent-400 focus:outline-none focus:ring-2 focus:ring-accent-100">
        </div>

        {{-- URL --}}
        <div>
            <label class="mb-1 block text-[11px] font-medium text-brand-500">آدرس (URL)</label>
            <input type="text" name="url" value="{{ $item->url }}" required dir="ltr"
                class="w-full rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs text-brand-600 focus:border-accent-400 focus:outline-none focus:ring-2 focus:ring-accent-100">
        </div>

        {{-- Optional mega-menu image (shown on hover in the desktop dropdown).
             Only meaningful for sub-items, but harmless on top-level rows. --}}
        <div>
            <label class="mb-1 block text-[11px] font-medium text-brand-500">تصویر مگامنو <span class="text-brand-300">(اختیاری — با hover نمایش داده می‌شود)</span></label>
            <div class="flex items-center gap-2">
                @if ($item->image)
                    <img src="{{ $item->image }}" alt="" class="h-12 w-10 shrink-0 rounded-md object-cover ring-1 ring-brand-200">
                @endif
                <input type="file" name="image" accept="image/*"
                    class="w-full text-xs text-brand-600 file:mr-2 file:rounded-md file:border-0 file:bg-brand-100 file:px-2.5 file:py-1 file:text-xs file:font-medium file:text-brand-700 hover:file:bg-brand-200">
            </div>
            @if ($item->image)
                <label class="mt-1 flex cursor-pointer items-center gap-1.5 text-[11px] text-red-500">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-brand-300">
                    حذف تصویر
                </label>
            @endif
        </div>

        {{-- Bottom bar: active toggle + save --}}
        <div class="flex items-center justify-between pt-1">
            <label class="flex cursor-pointer select-none items-center gap-2 text-xs text-brand-600">
                <span class="relative inline-flex">
                    <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="peer sr-only">
                    <span class="block h-5 w-9 rounded-full bg-brand-300 transition-colors peer-checked:bg-green-500"></span>
                    <span class="absolute start-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                </span>
                <span class="font-medium">{{ $item->is_active ? 'فعال' : 'غیرفعال' }}</span>
            </label>

            <button type="submit"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-accent-600 active:scale-95">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                ذخیره
            </button>
        </div>
    </form>

    {{-- Move + delete row (always visible) --}}
    <div class="mt-2 flex items-center justify-between border-t border-brand-100 pt-2">
        <div class="flex items-center gap-1">
            <form action="{{ route('admin.menus.move', $item) }}" method="POST">
                @csrf <input type="hidden" name="dir" value="up">
                <button type="submit" title="انتقال به بالا"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-brand-200 text-brand-500 transition hover:bg-brand-100 hover:text-brand-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m18 15-6-6-6 6"/></svg>
                </button>
            </form>
            <form action="{{ route('admin.menus.move', $item) }}" method="POST">
                @csrf <input type="hidden" name="dir" value="down">
                <button type="submit" title="انتقال به پایین"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-brand-200 text-brand-500 transition hover:bg-brand-100 hover:text-brand-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            </form>
        </div>

        <form action="{{ route('admin.menus.destroy', $item) }}" method="POST"
              onsubmit="return confirm('این لینک حذف شود؟ زیرمنوهای آن هم حذف می‌شوند.')">
            @csrf @method('DELETE')
            <button type="submit" title="حذف"
                class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-500 transition hover:bg-red-50">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                حذف
            </button>
        </form>
    </div>
</div>
