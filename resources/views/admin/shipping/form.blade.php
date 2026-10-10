@extends('admin.layout')

@section('title', $method->exists ? 'ویرایش روش ارسال' : 'روش ارسال جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $method->exists ? 'ویرایش روش ارسال' : 'روش ارسال جدید' }}</h1>
        <a href="{{ route('admin.shipping.index') }}" class="text-sm text-brand-500 hover:underline">بازگشت</a>
    </div>

    <form method="POST" action="{{ $method->exists ? route('admin.shipping.update', $method) : route('admin.shipping.store') }}"
          class="max-w-2xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        @if ($method->exists) @method('PATCH') @endif

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">نام روش *</label>
            <input type="text" name="name" value="{{ old('name', $method->name) }}" required placeholder="مثلاً پست پیشتاز"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">توضیح</label>
            <input type="text" name="description" value="{{ old('description', $method->description) }}"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">هزینه (تومان) *</label>
                <input type="number" name="price" value="{{ old('price', $method->price ?? 0) }}" min="0" required
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">ارسال رایگان از مبلغ (تومان)</label>
                <input type="number" name="free_over" value="{{ old('free_over', $method->free_over) }}" min="0" placeholder="خالی = هرگز"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
        </div>

        <label class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
            <input type="checkbox" name="cost_on_delivery" value="1" @checked(old('cost_on_delivery', $method->cost_on_delivery ?? false)) class="mt-0.5">
            <span>
                <span class="font-medium">هزینهٔ پس‌کرایه</span>
                <span class="mt-1 block text-xs text-amber-700">با تیک‌زدن این گزینه، هزینهٔ ارسال در زمان پرداخت آنلاین محاسبه نمی‌شود و مشتری مبلغ کرایه را هنگام تحویل به مأمور پرداخت می‌کند.</span>
            </span>
        </label>

        {{-- Per-province zone costs --}}
        <div class="border-t border-brand-100 pt-4">
            <h2 class="mb-2 text-sm font-bold text-brand-800">هزینهٔ استان‌ها (اختیاری)</h2>
            <p class="mb-2 text-xs text-brand-400">برای استان‌های خاص هزینهٔ متفاوت تعیین کنید. نام استان باید با آدرس مشتری یکی باشد. سایر استان‌ها هزینهٔ پایه را می‌پردازند.</p>
            <div id="zone-rows" class="space-y-2">
                @foreach ((array) old('zone_province', array_keys($method->zones ?? [])) as $i => $prov)
                    <div class="flex gap-2">
                        <input name="zone_province[]" value="{{ $prov }}" placeholder="استان" class="flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <input name="zone_price[]" type="number" min="0" value="{{ old('zone_price.'.$i, ($method->zones[$prov] ?? 0)) }}" placeholder="هزینه (تومان)" class="w-40 rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
                        <button type="button" class="text-red-400" onclick="this.parentElement.remove()">×</button>
                    </div>
                @endforeach
            </div>
            <button type="button" id="zone-add" class="mt-2 rounded-lg bg-brand-100 px-3 py-1.5 text-xs text-brand-700">+ افزودن استان</button>
            <template id="zone-tpl">
                <div class="flex gap-2">
                    <input name="zone_province[]" placeholder="استان" class="flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <input name="zone_price[]" type="number" min="0" placeholder="هزینه (تومان)" class="w-40 rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
                    <button type="button" class="text-red-400" onclick="this.parentElement.remove()">×</button>
                </div>
            </template>
        </div>

        <div class="flex gap-4">
            <div class="w-32">
                <label class="mb-1 block text-sm font-medium text-brand-700">ترتیب</label>
                <input type="number" name="position" value="{{ old('position', $method->position ?? 0) }}" min="0"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <label class="mt-7 inline-flex items-center gap-2 text-sm text-brand-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $method->is_active)) class="rounded"> فعال
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
            <a href="{{ route('admin.shipping.index') }}" class="rounded-lg px-5 py-2 text-sm text-brand-500 hover:bg-brand-50">انصراف</a>
        </div>
    </form>

    <script>
        document.getElementById('zone-add')?.addEventListener('click', function () {
            const tpl = document.getElementById('zone-tpl');
            document.getElementById('zone-rows').appendChild(tpl.content.cloneNode(true));
        });
    </script>
@endsection
