@extends('layouts.app')

@section('title', 'Size Guide | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">SIZE GUIDE</h1>
        <p class="mt-2 text-sm leading-7 text-brand-600">To find your perfect fit, compare your body measurements with the table below (in centimetres).</p>
        <div class="mt-6 overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full text-center text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr><th class="p-3">Size</th><th class="p-3">Chest</th><th class="p-3">Waist</th><th class="p-3">Hips</th></tr>
                </thead>
                <tbody class="divide-y divide-brand-50 fa-num">
                    @foreach ([['S','88–92','72–76','94–98'],['M','94–98','78–82','100–104'],['L','100–104','84–88','106–110'],['XL','106–110','90–94','112–116']] as $r)
                        <tr><td class="p-3 font-bold">{{ $r[0] }}</td><td class="p-3">{{ $r[1] }}</td><td class="p-3">{{ $r[2] }}</td><td class="p-3">{{ $r[3] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs text-brand-400">If you're between two sizes, choose the larger one for a more relaxed fit.</p>
    </div>
@endsection
