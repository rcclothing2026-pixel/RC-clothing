@extends('admin.layout')

@section('title', 'منوها')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold text-brand-900">مدیریت منوها</h1>
        <p class="mt-1 text-sm text-brand-500">هر ستون یک موقعیت نمایش است. لینک‌ها را مستقیماً ویرایش کنید — با hover ظاهر می‌شوند.</p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    @foreach ($locations as $key => $label)
        @php($locationItems = $items[$key] ?? collect())
        <section class="flex flex-col rounded-2xl border border-brand-100 bg-brand-50/40 p-4 shadow-sm">

            {{-- Section header --}}
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-brand-900">{{ $label }}</h2>
                    <p class="text-xs text-brand-400">{{ $locationItems->count() }} لینک</p>
                </div>
                <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-brand-500">{{ $key }}</span>
            </div>

            {{-- Items list --}}
            <div class="flex-1 space-y-2 mb-4">
                @forelse ($locationItems as $item)
                    @include('admin.menus._item', ['item' => $item, 'depth' => 0])
                    @foreach (($childrenByParent[$item->id] ?? collect()) as $child)
                        @include('admin.menus._item', ['item' => $child, 'depth' => 1])
                    @endforeach
                @empty
                    <div class="rounded-xl border border-dashed border-brand-200 bg-white p-5 text-center">
                        <svg class="mx-auto mb-2 h-6 w-6 text-brand-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        <p class="text-xs text-brand-400">لینکی ندارد — پیش‌فرض سایت نمایش داده می‌شود.</p>
                    </div>
                @endforelse
            </div>

            {{-- Add new link --}}
            <details class="group rounded-xl border border-brand-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer select-none items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        افزودن لینک
                    </span>
                    <svg class="h-4 w-4 text-brand-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </summary>

                <form action="{{ route('admin.menus.store') }}" method="POST" class="space-y-3 border-t border-brand-100 px-4 py-4">
                    @csrf
                    <input type="hidden" name="location" value="{{ $key }}">

                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-600">عنوان لینک</label>
                        <input type="text" name="label" required
                            class="w-full rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900 placeholder:text-brand-300 focus:border-accent-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-accent-100"
                            placeholder="مثال: فروشگاه">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-600">آدرس (URL)</label>
                        <input type="text" name="url" required dir="ltr"
                            class="w-full rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-900 placeholder:text-brand-300 focus:border-accent-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-accent-100"
                            placeholder="/shop یا https://...">
                    </div>

                    @if ($locationItems->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-600">زیرمنوی</label>
                        <select name="parent_id"
                            class="w-full rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700 focus:border-accent-400 focus:outline-none">
                            <option value="">— سطح اول (بدون والد) —</option>
                            @foreach (($parentsByLocation[$key] ?? collect()) as $parent)
                                <option value="{{ $parent->id }}">زیر: {{ $parent->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @else
                        <input type="hidden" name="parent_id" value="">
                    @endif

                    <button type="submit"
                        class="w-full rounded-lg bg-accent-600 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-700 active:scale-[.98]">
                        + افزودن لینک
                    </button>
                </form>
            </details>

            {{-- Bulk-add from catalog: pick categories / collections and create
                 menu items (optionally each backed by an auto-built brick page). --}}
            <details class="group mt-2 rounded-xl border border-brand-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer select-none items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6Z"/></svg>
                        افزودن از دسته‌بندی و مجموعه
                    </span>
                    <svg class="h-4 w-4 text-brand-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </summary>

                <form action="{{ route('admin.menus.fromCatalog') }}" method="POST" class="space-y-3 border-t border-brand-100 px-4 py-4">
                    @csrf
                    <input type="hidden" name="location" value="{{ $key }}">

                    @if ($locationItems->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-600">زیرمنوی</label>
                        <select name="parent_id"
                            class="w-full rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700 focus:border-accent-400 focus:outline-none">
                            <option value="">— سطح اول (بدون والد) —</option>
                            @foreach (($parentsByLocation[$key] ?? collect()) as $parent)
                                <option value="{{ $parent->id }}">زیر: {{ $parent->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @else
                        <input type="hidden" name="parent_id" value="">
                    @endif

                    @if ($catalogCategories->isNotEmpty())
                    <div>
                        <p class="mb-1 text-xs font-medium text-brand-600">دسته‌بندی‌ها</p>
                        <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-brand-100 bg-brand-50/50 p-2">
                            @foreach ($catalogCategories as $cat)
                                <label class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm text-brand-700 hover:bg-white">
                                    <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" class="rounded border-brand-300 text-accent-600">
                                    <span class="truncate">{{ $cat->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if ($catalogCollections->isNotEmpty())
                    <div>
                        <p class="mb-1 text-xs font-medium text-brand-600">مجموعه‌ها</p>
                        <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-brand-100 bg-brand-50/50 p-2">
                            @foreach ($catalogCollections as $col)
                                <label class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm text-brand-700 hover:bg-white">
                                    <input type="checkbox" name="collection_ids[]" value="{{ $col->id }}" class="rounded border-brand-300 text-accent-600">
                                    <span class="truncate">{{ $col->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <label class="flex cursor-pointer items-start gap-2 rounded-lg bg-brand-50 p-2.5 text-xs text-brand-700">
                        <input type="checkbox" name="build_page" value="1" checked class="mt-0.5 rounded border-brand-300 text-accent-600">
                        <span>ساخت خودکار صفحه آجری (brick) برای هر مورد و لینک منو به آن. <span class="text-brand-400">(بدون تیک، لینک مستقیم به فروشگاه فیلترشده می‌رود.)</span></span>
                    </label>

                    <button type="submit"
                        class="w-full rounded-lg bg-brand-900 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[.98]">
                        + ساخت آیتم‌های منو
                    </button>
                </form>
            </details>

        </section>
    @endforeach
</div>
@endsection
