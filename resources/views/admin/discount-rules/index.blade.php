@extends('admin.layout')

@section('title', 'قوانین تخفیف هوشمند')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-brand-900">قوانین تخفیف هوشمند</h1>
    <a href="{{ route('admin.discount-rules.create') }}"
       class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">+ قانون جدید</a>
</div>

<div class="rounded-card bg-white ring-1 ring-brand-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-brand-50 text-brand-500 text-xs">
            <tr>
                <th class="text-right p-3">نام</th>
                <th class="text-right p-3">اولویت</th>
                <th class="text-right p-3">شرایط</th>
                <th class="text-right p-3">عملیات</th>
                <th class="text-right p-3">مصرف</th>
                <th class="text-center p-3">فعال</th>
                <th class="p-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-50">
            @forelse ($rules as $rule)
            <tr class="hover:bg-brand-50 transition">
                <td class="p-3">
                    <p class="font-medium text-brand-800">{{ $rule->name }}</p>
                    @if ($rule->description)<p class="text-xs text-brand-400 mt-0.5">{{ $rule->description }}</p>@endif
                </td>
                <td class="p-3 text-brand-500 fa-num">{{ $rule->priority }}</td>
                <td class="p-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach ($rule->conditions as $cond)
                            <span class="inline-block rounded-full bg-brand-100 px-2 py-0.5 text-[10px] text-brand-600">
                                {{ $rule->conditionLabel($cond['type']) }}
                            </span>
                        @endforeach
                    </div>
                </td>
                <td class="p-3">
                    <div class="flex flex-wrap gap-1">
                        @foreach ($rule->actions as $act)
                            <span class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-600">
                                {{ $rule->actionLabel($act['type']) }}
                            </span>
                        @endforeach
                    </div>
                </td>
                <td class="p-3 text-xs text-brand-400 fa-num">
                    @if ($rule->max_uses)
                        {{ $rule->used_count }}/{{ $rule->max_uses }}
                    @else
                        {{ $rule->used_count }}
                    @endif
                </td>
                <td class="p-3 text-center">
                    @if ($rule->is_active)
                        <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                    @else
                        <span class="inline-block w-2 h-2 rounded-full bg-red-300"></span>
                    @endif
                </td>
                <td class="p-3">
                    <div class="flex gap-2">
                        <a href="{{ route('admin.discount-rules.edit', $rule) }}"
                           class="text-xs text-brand-500 hover:text-accent-600">ویرایش</a>
                        <form action="{{ route('admin.discount-rules.destroy', $rule) }}" method="POST"
                              onsubmit="return confirm('حذف قانون «{{ $rule->name }}»؟')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-brand-400 hover:text-red-500">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="p-8 text-center text-brand-400">هیچ قانون تخفیفی تعریف نشده است.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $rules->links() }}</div>
@endsection
