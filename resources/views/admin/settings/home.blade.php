@extends('admin.layout')

@section('title', 'صفحه اصلی')

@php($val = fn ($k, $d = '') => old(str_replace('.', '_', $k), $settings[$k] ?? $d))

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">محتوای صفحه اصلی</h1>
        <p class="mt-1 text-sm text-brand-500">بخش قهرمان (Hero) صفحهٔ نخست. خالی بگذارید تا متن پیش‌فرض استفاده شود.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.home.update') }}" class="max-w-3xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf @method('PATCH')

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">برچسب بالای عنوان</label>
            <input name="home_hero_badge" value="{{ $val('home.hero_badge') }}" placeholder="کالکشن جدید فصل" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">عنوان (خط اول)</label>
                <input name="home_hero_title" value="{{ $val('home.hero_title') }}" placeholder="استایلِ امروزِ تو،" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">عنوان (خط دوم، رنگی)</label>
                <input name="home_hero_title_accent" value="{{ $val('home.hero_title_accent') }}" placeholder="با کیفیت ایرانی" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">زیرعنوان</label>
            <textarea name="home_hero_subtitle" rows="2" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ $val('home.hero_subtitle') }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">متن دکمهٔ اصلی</label>
                <input name="home_hero_cta_text" value="{{ $val('home.hero_cta_text') }}" placeholder="مشاهده فروشگاه" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">آدرس تصویر قهرمان (URL)</label>
                <input name="home_hero_image" value="{{ $val('home.hero_image') }}" dir="ltr" placeholder="/storage/..." class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
    </form>
@endsection
