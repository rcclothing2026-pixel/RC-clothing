@extends('admin.layout')

@section('title', 'محصولات')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">محصولات</h1>
        <a href="{{ route('admin.products.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">+ محصول جدید</a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="جستجوی محصول..." class="w-full max-w-xs rounded-lg border border-brand-200 px-3 py-2 text-sm">
        <select name="category" class="rounded-lg border border-brand-200 px-3 py-2 text-sm">
            <option value="">همه دسته‌ها</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="collection" class="rounded-lg border border-brand-200 px-3 py-2 text-sm">
            <option value="">همه مجموعه‌ها</option>
            @foreach ($collections as $collection)
                <option value="{{ $collection->id }}" @selected(request('collection') == $collection->id)>{{ $collection->name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-brand-200 px-3 py-2 text-sm">
            <option value="">همه وضعیت‌ها</option>
            <option value="active" @selected(request('status') === 'active')>فعال</option>
            <option value="inactive" @selected(request('status') === 'inactive')>غیرفعال</option>
        </select>
        <button class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700">فیلتر</button>
        @if (request()->hasAny(['q', 'category', 'collection', 'status']))
            <a href="{{ route('admin.products.index') }}" class="px-3 py-2 text-sm text-brand-400 hover:underline">حذف فیلترها</a>
        @endif
    </form>

    <form method="POST" action="{{ route('admin.products.bulk') }}" id="bulk-form">
        @csrf
        <input type="hidden" name="bulk_action" id="bulk_action">

        {{-- Bulk toolbar --}}
        <div id="bulk-bar" class="mb-3 hidden flex-wrap items-center gap-2 rounded-card bg-brand-50 p-3 ring-1 ring-brand-100">
            <span class="text-sm text-brand-600"><b id="bulk-count" class="fa-num">۰</b> انتخاب شده</span>
            <button type="button" data-bulk="activate" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white">فعال‌سازی</button>
            <button type="button" data-bulk="deactivate" class="rounded-lg bg-brand-200 px-3 py-1.5 text-xs font-medium text-brand-700">غیرفعال</button>
            <span class="mx-1 h-5 w-px bg-brand-200"></span>
            <select name="category_id" class="rounded-lg border border-brand-200 px-2 py-1.5 text-xs">
                <option value="">— دسته —</option>
                @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
            </select>
            <button type="button" data-bulk="category" class="rounded-lg bg-brand-100 px-3 py-1.5 text-xs text-brand-700">اعمال دسته</button>
            <select name="collection_id" class="rounded-lg border border-brand-200 px-2 py-1.5 text-xs">
                <option value="">— مجموعه —</option>
                @foreach ($collections as $collection)<option value="{{ $collection->id }}">{{ $collection->name }}</option>@endforeach
            </select>
            <button type="button" data-bulk="collection" class="rounded-lg bg-brand-100 px-3 py-1.5 text-xs text-brand-700">اعمال مجموعه</button>
            <span class="mx-1 h-5 w-px bg-brand-200"></span>
            <button type="button" data-bulk="delete" data-confirm="حذف محصول‌های انتخاب‌شده؟" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600">حذف</button>
        </div>

        <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr>
                        <th class="p-3"><input type="checkbox" id="check-all" class="rounded"></th>
                        <th class="p-3 text-right font-medium">محصول</th>
                        <th class="p-3 text-right font-medium">دسته</th>
                        <th class="p-3 text-right font-medium">مجموعه</th>
                        <th class="p-3 text-right font-medium">قیمت</th>
                        <th class="p-3 text-right font-medium">موجودی</th>
                        <th class="p-3 text-right font-medium">وضعیت</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-50">
                    @forelse ($products as $product)
                        <tr>
                            <td class="p-3"><input type="checkbox" name="ids[]" value="{{ $product->id }}" class="row-check rounded"></td>
                            <td class="p-3">
                                <a href="{{ route('admin.products.edit', $product) }}" class="flex items-center gap-3 hover:text-accent-600">
                                    <img src="{{ $product->primary_image_url ?? '/placeholder?w=80&h=80&label='.urlencode($product->name) }}" class="h-10 w-10 rounded-lg object-cover" alt="">
                                    <span class="font-medium text-brand-800">{{ $product->name }}</span>
                                </a>
                            </td>
                            <td class="p-3 text-brand-500">{{ ($product->categories->isNotEmpty() ? $product->categories : collect([$product->category])->filter())->pluck('name')->implode('، ') ?: '—' }}</td>
                            <td class="p-3 text-brand-500">{{ $product->collection?->name ?? '—' }}</td>
                            <td class="p-3 text-brand-700">{{ $product->formattedPrice() }}</td>
                            <td class="p-3 fa-num">{{ \App\Support\Money::toPersianDigits((string) $product->totalStock()) }}</td>
                            <td class="p-3">
                                @if ($product->is_active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600">فعال</span>
                                @else
                                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-500">غیرفعال</span>
                                @endif
                            </td>
                            <td class="p-3 text-left">
                                <a href="{{ route('admin.products.edit', $product) }}" class="text-accent-600 hover:underline">ویرایش</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-8 text-center text-brand-400">محصولی یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-4 fa-num">{{ $products->links() }}</div>

    <script>
        (function () {
            const form = document.getElementById('bulk-form');
            const bar = document.getElementById('bulk-bar');
            const countEl = document.getElementById('bulk-count');
            const checkAll = document.getElementById('check-all');
            const rows = () => Array.from(form.querySelectorAll('.row-check'));
            const faN = (n) => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]);

            function refresh() {
                const checked = rows().filter(c => c.checked).length;
                countEl.textContent = faN(checked);
                bar.classList.toggle('hidden', checked === 0);
                bar.classList.toggle('flex', checked > 0);
            }

            checkAll?.addEventListener('change', () => { rows().forEach(c => c.checked = checkAll.checked); refresh(); });
            form.addEventListener('change', (e) => { if (e.target.classList.contains('row-check')) refresh(); });

            document.querySelectorAll('[data-bulk]').forEach(btn => btn.addEventListener('click', () => {
                if (!rows().some(c => c.checked)) return;
                if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;
                document.getElementById('bulk_action').value = btn.dataset.bulk;
                form.submit();
            }));

            refresh();
        })();
    </script>
@endsection
