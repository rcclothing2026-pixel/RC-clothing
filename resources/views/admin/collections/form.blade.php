@extends('admin.layout')

@section('title', $collection->exists ? 'ویرایش مجموعه' : 'مجموعه جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $collection->exists ? 'ویرایش مجموعه' : 'مجموعه جدید' }}</h1>
        <a href="{{ route('admin.collections.index') }}" class="text-sm text-brand-500 hover:underline">بازگشت</a>
    </div>

    <form method="POST" action="{{ $collection->exists ? route('admin.collections.update', $collection) : route('admin.collections.store') }}"
          enctype="multipart/form-data"
          class="max-w-2xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        @if ($collection->exists) @method('PATCH') @endif

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">تصویر مجموعه</label>
            <div class="flex items-start gap-4">
                <div class="grid h-28 w-24 shrink-0 place-items-center overflow-hidden rounded-lg bg-brand-50 text-center text-xs text-brand-300 ring-1 ring-brand-100">
                    @if ($collection->image_path)
                        <img src="{{ $collection->image_path }}" alt="{{ $collection->name }}" class="h-full w-full object-cover">
                    @else
                        بدون تصویر
                    @endif
                </div>
                <div class="space-y-2 pt-1">
                    <input type="file" name="image" accept="image/*"
                           class="block text-sm text-brand-600 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-200">
                    @if ($collection->image_path)
                        <label class="inline-flex items-center gap-2 text-xs text-red-500">
                            <input type="checkbox" name="remove_image" value="1" class="rounded"> حذف تصویر فعلی
                        </label>
                    @endif
                    <p class="text-xs text-brand-400">حداکثر ۴ مگابایت. نسبت عمودی (۳:۴) بهترین نتیجه را می‌دهد.</p>
                    @error('image')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">نام مجموعه *</label>
            <input type="text" name="name" value="{{ old('name', $collection->name) }}" required placeholder="مثلاً کالکشن بهار"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">نامک (slug)</label>
            <input type="text" name="slug" value="{{ old('slug', $collection->slug) }}" dir="ltr"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">توضیح</label>
            <textarea name="description" rows="2" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ old('description', $collection->description) }}</textarea>
        </div>
        <div class="flex gap-4">
            <div class="w-32">
                <label class="mb-1 block text-sm font-medium text-brand-700">ترتیب</label>
                <input type="number" name="position" value="{{ old('position', $collection->position ?? 0) }}" min="0"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <label class="mt-7 inline-flex items-center gap-2 text-sm text-brand-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $collection->is_active)) class="rounded"> فعال
            </label>
        </div>
        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
            <a href="{{ route('admin.collections.index') }}" class="rounded-lg px-5 py-2 text-sm text-brand-500 hover:bg-brand-50">انصراف</a>
        </div>
    </form>
@endsection
