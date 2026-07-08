@extends('admin.layout')

@section('title', 'درگاه‌های پرداخت')

@section('content')
    <h1 class="mb-2 text-xl font-bold text-brand-900">درگاه‌های پرداخت</h1>
    <p class="mb-6 text-sm text-brand-500">درگاه‌ها را فعال کرده، اطلاعات اتصال را وارد و درگاه پیش‌فرض را انتخاب کنید. تا زمانی که اطلاعات اتصال وارد نشده باشد، پرداخت در حالت آزمایشی (شبیه‌سازی) انجام می‌شود.</p>

    <div class="space-y-5">
        @foreach ($methods as $method)
            <form action="{{ route('admin.settings.payments.update', $method) }}" method="POST" class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                @csrf @method('PATCH')
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-base font-bold text-brand-900">{{ $method->label }}</h2>
                        <span class="text-xs text-brand-400" dir="ltr">{{ $method->key }}</span>
                        @if ($method->is_default)
                            <span class="rounded-full bg-brand-900 px-2 py-0.5 text-xs text-white">پیش‌فرض</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-1.5 text-brand-600"><input type="checkbox" name="is_active" value="1" @checked($method->is_active)> فعال</label>
                        <label class="flex items-center gap-1.5 text-brand-600"><input type="checkbox" name="sandbox" value="1" @checked($method->sandbox)> حالت تست</label>
                        <label class="flex items-center gap-1.5 text-brand-600"><input type="checkbox" name="is_default" value="1" @checked($method->is_default)> پیش‌فرض</label>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-brand-500">عنوان نمایشی</label>
                        <input name="label" value="{{ $method->label }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-brand-500">توضیح</label>
                        <input name="description" value="{{ $method->description }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>

                    @foreach (\App\Models\PaymentMethod::fieldsFor($method->key) as $field => $label)
                        <div>
                            <label class="mb-1 block text-xs text-brand-500">{{ $label }}</label>
                            <input name="config[{{ $field }}]" value="{{ $method->config($field) }}" dir="ltr"
                                   autocomplete="off"
                                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-brand-400">
                        آدرس بازگشت (Callback):
                        <span dir="ltr">{{ url('/checkout/callback/'.$method->key) }}</span>
                    </p>
                    <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
                </div>
            </form>
        @endforeach
    </div>
@endsection
