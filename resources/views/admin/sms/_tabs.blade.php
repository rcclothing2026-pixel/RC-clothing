<nav class="mt-3 flex gap-1 text-sm">
    @foreach (['settings' => ['admin.sms.settings', 'تنظیمات'], 'telegram' => ['admin.sms.telegram', 'ربات تلگرام'], 'compose' => ['admin.sms.compose', 'ارسال پیامک'], 'log' => ['admin.sms.log', 'گزارش']] as $key => [$route, $label])
        <a href="{{ route($route) }}" class="rounded-lg px-3 py-1.5 {{ ($active ?? '') === $key ? 'bg-brand-900 text-white' : 'text-brand-600 hover:bg-brand-100' }}">{{ $label }}</a>
    @endforeach
</nav>
