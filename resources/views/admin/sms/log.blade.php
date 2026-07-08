@extends('admin.layout')

@section('title', 'گزارش پیامک')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">پنل پیامک</h1>
        @include('admin.sms._tabs', ['active' => 'log'])
    </div>

    <form method="GET" class="mb-4">
        <select name="type" onchange="this.form.requestSubmit()" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            <option value="">همه انواع</option>
            @foreach (['otp' => 'کد ورود', 'order' => 'سفارش', 'campaign' => 'پیام گروهی'] as $k => $label)
                <option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">شماره</th>
                    <th class="p-3 text-right font-medium">نوع</th>
                    <th class="p-3 text-right font-medium">متن</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3 text-right font-medium">زمان</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($messages as $m)
                    <tr>
                        <td class="p-3 fa-num text-brand-700" dir="ltr">{{ $m->phone }}</td>
                        <td class="p-3 text-brand-500">{{ ['otp'=>'کد ورود','order'=>'سفارش','campaign'=>'گروهی'][$m->type] ?? $m->type }}</td>
                        <td class="p-3 text-brand-600">{{ \Illuminate\Support\Str::limit($m->body, 60) }}</td>
                        <td class="p-3">
                            @if ($m->status === 'sent')
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600">ارسال شد</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-600" title="{{ $m->error }}">ناموفق</span>
                            @endif
                        </td>
                        <td class="p-3 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($m->created_at, true) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-brand-400">پیامکی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 fa-num">{{ $messages->links() }}</div>
@endsection
