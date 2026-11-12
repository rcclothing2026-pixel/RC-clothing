@extends('admin.layout')

@section('title', 'تأیید تغییر مدیریت تخفیف‌ها')

@section('content')
    @php($__toLabel = $to === 'stoqs' ? 'مدیریت با StoqS' : 'مدیریت با فروشگاه')
    @php($__fa = fn ($n) => \App\Support\Money::toPersianDigits((string) $n))
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">تغییر به «{{ $__toLabel }}»</h1>
        <p class="mt-1 text-sm text-brand-500">پیش از تغییر ببینید چه چیزهایی متوقف و چه چیزهایی شروع می‌شود.</p>
    </div>

    @if ($error)
        <div class="mb-4 rounded-card bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">{{ $error }}</div>
    @endif

    @if (! empty($guard['stop_all']))
        <div class="mb-4 rounded-card bg-red-50 p-4 text-sm font-bold text-red-700 ring-1 ring-red-200">{{ \App\Services\StockKeeping\StoqsPromoSource::STOP_ALL }}</div>
    @elseif (! empty($guard['warning']))
        <div class="mb-4 rounded-card bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">{{ $guard['warning'] }}</div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        @if (isset($guard['shop_stops']))
            <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
                <h2 class="text-sm font-bold text-brand-800">تخفیف‌ها و کوپن‌های فروشگاه که متوقف می‌شوند ({{ $__fa((int) ($guard['shop_stops']['count'] ?? 0)) }})</h2>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-xs text-brand-600">
                    @forelse ((array) ($guard['shop_stops']['names'] ?? []) as $n)<li>{{ $n }}</li>@empty<li class="list-none text-brand-400">هیچ</li>@endforelse
                </ul>
            </div>
        @endif
        @if (isset($guard['stoqs_starts']))
            <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
                <h2 class="text-sm font-bold text-brand-800">کمپین‌های StoqS که شروع می‌شوند ({{ $__fa((int) ($guard['stoqs_starts']['count'] ?? 0)) }})</h2>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-xs text-brand-600">
                    @forelse ((array) ($guard['stoqs_starts']['plans'] ?? $guard['stoqs_starts']['names'] ?? []) as $p)<li>{{ is_array($p) ? ($p['name'] ?? '') : $p }}</li>@empty<li class="list-none text-brand-400">هیچ</li>@endforelse
                </ul>
            </div>
        @endif
        @if (isset($guard['stoqs_stops']))
            <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
                <h2 class="text-sm font-bold text-brand-800">کمپین‌های StoqS که در این سایت متوقف می‌شوند ({{ $__fa((int) ($guard['stoqs_stops']['count'] ?? 0)) }})</h2>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-xs text-brand-600">
                    @forelse ((array) ($guard['stoqs_stops']['plans'] ?? $guard['stoqs_stops']['names'] ?? []) as $p)<li>{{ is_array($p) ? ($p['name'] ?? '') : $p }}</li>@empty<li class="list-none text-brand-400">هیچ</li>@endforelse
                </ul>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.settings.promo-source.confirm') }}" class="max-w-xl space-y-3 rounded-card bg-white p-4 ring-1 ring-brand-100">
        @csrf
        <input type="hidden" name="to" value="{{ $to }}">
        <label class="block text-xs text-brand-600">یادداشت (اختیاری)
            <input type="text" name="note" maxlength="200" value="{{ $note }}" class="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </label>
        <label class="flex items-start gap-2 text-sm text-brand-800">
            <input type="checkbox" name="confirm" value="1" class="mt-1">
            <span>مطمئنم؛ مدیریت تخفیف‌های سایت به «{{ $__toLabel }}» تغییر کند.</span>
        </label>
        @if (! empty($guard['stop_all']))
            <label class="flex items-start gap-2 text-sm font-bold text-red-700">
                <input type="checkbox" name="confirm_stop_all" value="1" class="mt-1">
                <span>{{ \App\Services\StockKeeping\StoqsPromoSource::STOP_ALL }}؛ این را می‌دانم.</span>
            </label>
        @endif
        <div class="flex gap-2">
            <button class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-bold text-white">تغییر</button>
            <a href="{{ route('admin.settings.integration') }}" class="rounded-lg px-4 py-2 text-sm text-brand-600 ring-1 ring-brand-200">انصراف</a>
        </div>
    </form>
@endsection
