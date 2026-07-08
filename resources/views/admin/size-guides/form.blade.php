@extends('admin.layout')

@section('title', $guide->exists ? 'ویرایش راهنمای سایز' : 'راهنمای سایز جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $guide->exists ? 'ویرایش راهنمای سایز' : 'راهنمای سایز جدید' }}</h1>
        <a href="{{ route('admin.size-guides.index') }}" class="text-sm text-brand-500 hover:underline">بازگشت</a>
    </div>

    <form method="POST" action="{{ $guide->exists ? route('admin.size-guides.update', $guide) : route('admin.size-guides.store') }}"
          enctype="multipart/form-data"
          class="max-w-3xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        @if ($guide->exists) @method('PATCH') @endif

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">نام راهنما *</label>
            <input type="text" name="name" value="{{ old('name', $guide->name) }}" required placeholder="مثلاً راهنمای سایز تی‌شرت"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">تصویر راهنمای سایز (اختیاری)</label>
            <div class="flex items-start gap-4">
                <div class="grid h-28 w-28 shrink-0 place-items-center overflow-hidden rounded-lg bg-brand-50 text-center text-xs text-brand-300 ring-1 ring-brand-100">
                    @if ($guide->image_path)
                        <img src="{{ $guide->image_path }}" alt="" class="h-full w-full object-contain">
                    @else
                        بدون تصویر
                    @endif
                </div>
                <div class="space-y-2 pt-1">
                    <input type="file" name="image" accept="image/*"
                           class="block text-sm text-brand-600 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-200">
                    @if ($guide->image_path)
                        <label class="inline-flex items-center gap-2 text-xs text-red-500"><input type="checkbox" name="remove_image" value="1" class="rounded"> حذف تصویر فعلی</label>
                    @endif
                    <p class="text-xs text-brand-400">یک تصویر جدول سایز بگذارید. در پنجره روی صفحهٔ محصول نمایش داده می‌شود (می‌توانید به‌جای یا همراه با جدول HTML پایین استفاده کنید).</p>
                    @error('image')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">محتوا (جدول سایز — HTML مجاز است، اختیاری)</label>
            <textarea name="content" rows="12" dir="auto"
                      class="w-full rounded-lg border border-brand-200 px-3 py-2 font-mono text-xs leading-6">{{ old('content', $guide->content) }}</textarea>
            <p class="mt-1 text-xs text-brand-400">می‌توانید یک جدول HTML بگذارید (table/tr/td). تگ‌های ناامن حذف می‌شوند. این محتوا در پنجرهٔ «راهنمای سایز» روی صفحهٔ محصول نمایش داده می‌شود.</p>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-brand-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $guide->is_active)) class="rounded"> فعال
        </label>

        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
            <a href="{{ route('admin.size-guides.index') }}" class="rounded-lg px-5 py-2 text-sm text-brand-500 hover:bg-brand-50">انصراف</a>
        </div>
    </form>
@endsection
