@extends('layouts.app')

@section('title', 'راهنمای سایز | چیاکو')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold text-brand-900">راهنمای سایز</h1>
        <p class="mt-2 text-sm leading-7 text-brand-600">برای انتخاب سایز درست، اندازه‌های بدن خود را با جدول زیر مقایسه کنید (سانتی‌متر).</p>
        <div class="mt-6 overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full text-center text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr><th class="p-3">سایز</th><th class="p-3">دور سینه</th><th class="p-3">دور کمر</th><th class="p-3">دور باسن</th></tr>
                </thead>
                <tbody class="divide-y divide-brand-50 fa-num">
                    @foreach ([['S','88–92','72–76','94–98'],['M','94–98','78–82','100–104'],['L','100–104','84–88','106–110'],['XL','106–110','90–94','112–116']] as $r)
                        <tr><td class="p-3 font-bold">{{ $r[0] }}</td><td class="p-3">{{ $r[1] }}</td><td class="p-3">{{ $r[2] }}</td><td class="p-3">{{ $r[3] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs text-brand-400">اگر بین دو سایز بودید، سایز بزرگ‌تر را برای فیت راحت‌تر انتخاب کنید.</p>
    </div>
@endsection
