@extends('admin.layout')

@section('title', $category->exists ? 'ویرایش دسته' : 'دسته جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $category->exists ? 'ویرایش دسته' : 'دسته جدید' }}</h1>
        <a href="{{ route('admin.categories.index') }}" class="text-sm text-brand-500 hover:underline">بازگشت</a>
    </div>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="max-w-2xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        @if ($category->exists) @method('PATCH') @endif

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">نام دسته *</label>
            <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">نامک (slug) <span class="text-brand-400">— خالی بگذارید تا خودکار ساخته شود</span></label>
            <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" dir="ltr"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">دسته والد</label>
            <select name="parent_id" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="">— بدون والد —</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">تصویر دسته</label>
            <div class="flex items-center gap-4">
                <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-lg bg-brand-50 text-xs text-brand-300 ring-1 ring-brand-100">
                    @if ($category->image_path)
                        <img src="{{ $category->image_path }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                    @else
                        بدون تصویر
                    @endif
                </div>
                <div>
                    <input type="file" name="image" accept="image/*" class="text-sm">
                    <p class="mt-1 text-xs text-brand-400">تصویر مربعی پیشنهاد می‌شود. حداکثر ۴ مگابایت.</p>
                    @if ($category->image_path)
                        <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-red-500"><input type="checkbox" name="remove_image" value="1"> حذف تصویر فعلی</label>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">توضیح</label>
            <textarea name="description" rows="2" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ old('description', $category->description) }}</textarea>
        </div>

        <div class="flex gap-4">
            <div class="w-32">
                <label class="mb-1 block text-sm font-medium text-brand-700">ترتیب</label>
                <input type="number" name="position" value="{{ old('position', $category->position ?? 0) }}" min="0"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <label class="mt-7 inline-flex items-center gap-2 text-sm text-brand-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="rounded"> فعال
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
            <a href="{{ route('admin.categories.index') }}" class="rounded-lg px-5 py-2 text-sm text-brand-500 hover:bg-brand-50">انصراف</a>
        </div>
    </form>
@endsection
