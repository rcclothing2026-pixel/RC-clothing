@extends('admin.layout')

@section('title', $customer->exists ? 'ویرایش کاربر' : 'کاربر جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $customer->exists ? 'ویرایش کاربر' : 'افزودن کاربر / مدیر' }}</h1>
        <a href="{{ $customer->exists ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="text-sm text-brand-500 hover:underline">بازگشت</a>
    </div>

    <form method="POST" action="{{ $customer->exists ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" class="max-w-2xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        @if ($customer->exists) @method('PATCH') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm text-brand-600">نام</label>
                <input name="name" value="{{ old('name', $customer->name) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">موبایل <span class="text-red-500">*</span></label>
                <input name="phone" value="{{ old('phone', $customer->phone) }}" dir="ltr" placeholder="09120000000" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-brand-400">ورود کاربر با همین شماره و کد پیامکی انجام می‌شود.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">ایمیل (اختیاری)</label>
                <input name="email" value="{{ old('email', $customer->email) }}" dir="ltr" type="email" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">گروه (اختیاری)</label>
                <input name="group" value="{{ old('group', $customer->group) }}" placeholder="مثلاً عمده‌فروش" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        <label class="flex items-center gap-2 rounded-lg bg-accent-50 p-3 text-sm text-accent-800 ring-1 ring-accent-100">
            <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $customer->is_admin)) class="rounded">
            دسترسی مدیریت (این کاربر می‌تواند وارد پنل مدیریت شود)
        </label>

        <div class="flex items-center gap-3">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">{{ $customer->exists ? 'ذخیره تغییرات' : 'ایجاد کاربر' }}</button>
            @if ($customer->exists)
                <button form="delete-user" class="text-sm text-red-600 hover:underline">حذف کاربر</button>
            @endif
        </div>
    </form>

    @if ($customer->exists)
        <form id="delete-user" method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('این کاربر حذف شود؟ این عمل قابل بازگشت نیست.')">
            @csrf @method('DELETE')
        </form>
    @endif
@endsection
