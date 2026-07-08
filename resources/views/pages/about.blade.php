@extends('layouts.app')

@section('title', 'About | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">THE ART OF LEISURE</h1>
        <p class="mt-3 font-script text-3xl text-accent-600">Legends &amp; Legacy</p>
        <div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
            <p>Racket Club was founded on a quiet conviction: that the hours you keep for yourself deserve the same care as the ones you spend proving yourself. We make leisurewear for the modern classicist — pieces that move between the court, the club and the long weekend without ever raising their voice.</p>
            <h2 class="pt-2 font-bold text-brand-900">Made to Last</h2>
            <p>Every garment is cut from considered fabric and finished with a clean, honest hand. We favour the enduring over the seasonal, the understated over the obvious. Nothing here shouts. Everything here stays.</p>
            <h2 class="pt-2 font-bold text-brand-900">A Standing Invitation</h2>
            <p>The Club is less a place than a way of moving through the day — unhurried, self-assured, at ease. We'd be glad to have you. For anything at all, our team is one message away on the <a href="{{ route('contact') }}" class="text-accent-600 hover:underline">contact page</a>.</p>
        </div>
    </div>
@endsection
