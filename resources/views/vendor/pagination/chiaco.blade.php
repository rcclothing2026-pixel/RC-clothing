@if ($paginator->hasPages())
    <nav role="navigation" aria-label="صفحه‌بندی" class="fa-num">
        <div class="flex items-center justify-between gap-4">
            {{-- Mobile --}}
            <div class="flex flex-1 justify-between sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="inline-flex cursor-not-allowed items-center rounded-xl border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-300">قبلی</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-xl border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-50">قبلی</a>
                @endif
                <span class="flex items-center text-sm text-brand-500">صفحه {{ $paginator->currentPage() }} از {{ $paginator->lastPage() }}</span>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-xl border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-50">بعدی</a>
                @else
                    <span class="inline-flex cursor-not-allowed items-center rounded-xl border border-brand-200 bg-white px-4 py-2 text-sm font-medium text-brand-300">بعدی</span>
                @endif
            </div>

            {{-- Desktop --}}
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-brand-500">
                        نمایش
                        @if ($paginator->firstItem())
                            <span class="font-semibold text-brand-800">{{ \App\Support\Money::toPersianDigits((string) $paginator->firstItem()) }}</span>
                            تا
                            <span class="font-semibold text-brand-800">{{ \App\Support\Money::toPersianDigits((string) $paginator->lastItem()) }}</span>
                        @else
                            {{ $paginator->count() }}
                        @endif
                        از
                        <span class="font-semibold text-brand-800">{{ \App\Support\Money::toPersianDigits((string) $paginator->total()) }}</span>
                        نتیجه
                    </p>
                </div>

                <div class="flex items-center gap-1">
                    {{-- Previous --}}
                    @if ($paginator->onFirstPage())
                        <span class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-brand-200 bg-white p-2 text-brand-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center rounded-lg border border-brand-200 bg-white p-2 text-brand-600 transition hover:bg-brand-50 hover:text-brand-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    @endif

                    {{-- Pages --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="inline-flex items-center justify-center px-3 py-1.5 text-sm text-brand-300">...</span>
                        @endif
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="inline-flex items-center justify-center rounded-lg bg-brand-900 px-3.5 py-1.5 text-sm font-semibold text-white">{{ \App\Support\Money::toPersianDigits((string) $page) }}</span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex items-center justify-center rounded-lg border border-brand-200 bg-white px-3.5 py-1.5 text-sm text-brand-600 transition hover:bg-brand-50 hover:text-brand-900" aria-label="صفحه {{ $page }}">{{ \App\Support\Money::toPersianDigits((string) $page) }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center rounded-lg border border-brand-200 bg-white p-2 text-brand-600 transition hover:bg-brand-50 hover:text-brand-900">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                        </a>
                    @else
                        <span class="inline-flex cursor-not-allowed items-center justify-center rounded-lg border border-brand-200 bg-white p-2 text-brand-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </nav>
@endif
