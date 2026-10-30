@extends('admin.layout')

@section('title', 'اوتلت')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">اوتلت</h1>
        <p class="mt-1 text-sm text-brand-500">محصول را انتخاب کنید و درصد تخفیف بدهید. قیمت در سایت، سبد خرید و گزارش فروش به StoqS همین قیمت است.
            حداکثر {{ \App\Support\Money::toPersianDigits((string) $max) }}٪؛ اقلام زمستانه و بسته‌ها مجاز نیستند و تخفیف‌های سبد روی اقلام اوتلت اعمال نمی‌شود.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.outlet.store') }}" class="mb-8 flex flex-wrap items-end gap-3 rounded-card bg-white p-4 ring-1 ring-brand-100">
        @csrf
        <label class="min-w-[16rem] flex-1 text-sm">
            <span class="mb-1 block text-brand-500">محصول</span>
            <select id="outlet-product" name="product_id" required class="w-full rounded-lg border border-brand-100 px-3 py-2">
                <option value="">انتخاب کنید</option>
                @foreach ($candidates as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} — {{ \App\Support\Money::toman((int) ($p->hasDiscount() ? $p->compare_at_price : $p->price)) }}</option>
                @endforeach
            </select>
        </label>
        <label class="w-28 text-sm">
            <span class="mb-1 block text-brand-500">تخفیف ٪</span>
            <input id="outlet-percent" type="number" name="percent" min="1" max="{{ $max }}" value="20" required class="w-full rounded-lg border border-brand-100 px-3 py-2">
        </label>
        <button class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">افزودن به اوتلت</button>
    </form>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">محصول</th>
                    <th class="p-3 text-right font-medium">قیمت پایه</th>
                    <th class="p-3 text-right font-medium">قیمت اوتلت</th>
                    <th class="p-3 text-right font-medium">تخفیف و وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($items as $item)
                    <tr>
                        <td class="p-3 font-semibold text-brand-800">
                            @if ($item->product)<a href="{{ route('product.show', $item->product) }}" target="_blank" class="hover:underline">{{ $item->product->name }}</a>@else — @endif
                        </td>
                        <td class="p-3 fa-num text-brand-500">{{ $item->product ? \App\Support\Money::toman((int) ($item->product->compare_at_price ?: $item->product->price)) : '—' }}</td>
                        <td class="p-3 fa-num font-semibold text-accent-600">{{ $item->product ? \App\Support\Money::toman((int) $item->product->price) : '—' }}</td>
                        <td class="p-3">
                            <form method="POST" action="{{ route('admin.outlet.update', $item) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <input id="outlet-percent-{{ $item->id }}" type="number" name="percent" min="1" max="{{ $max }}" value="{{ $item->percent }}" class="w-20 rounded-lg border border-brand-100 px-2 py-1">
                                <label class="flex items-center gap-1 text-xs text-brand-500"><input id="outlet-active-{{ $item->id }}" type="checkbox" name="is_active" value="1" @checked($item->is_active)> فعال</label>
                                <button class="rounded-lg bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-800">ذخیره</button>
                            </form>
                        </td>
                        <td class="p-3 text-left">
                            <form method="POST" action="{{ route('admin.outlet.destroy', $item) }}">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-600">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-brand-400">هنوز محصولی در اوتلت نیست.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
