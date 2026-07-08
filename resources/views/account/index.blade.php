@extends('layouts.app')

@section('title', 'Account | Racket Club')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <div class="mb-8 flex items-center justify-between">
            <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">ACCOUNT</h1>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="text-sm text-brand-500 transition hover:text-red-500">Sign Out</button>
            </form>
        </div>

        <div class="mb-6 flex flex-wrap gap-3">
            <a href="{{ route('wishlist.index') }}" class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">Wishlist</a>
            @if ($user->is_admin)
                <a href="{{ route('admin.dashboard') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">Admin Panel</a>
            @endif
        </div>

        @if ($crm && ($crm['ok'] ?? false))
            @php($club = $crm['customer'] ?? [])
            @php($life = $crm['lifetime'] ?? [])
            <section class="mb-6 rounded-card bg-brand-900 p-6 text-white">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-white/70">Club Points</p>
                        <p class="mt-1 text-3xl font-bold fa-num">{{ $club['loyalty_points'] ?? 0 }}</p>
                        @if (!empty($crm['loyalty']['points_value_toman']))
                            <p class="mt-1 text-xs text-white/70 fa-num">Worth {{ \App\Support\Money::toman($crm['loyalty']['points_value_toman']) }}</p>
                        @endif
                    </div>
                    <div class="flex gap-6 text-center">
                        <div>
                            <p class="text-2xl font-bold fa-num">{{ $life['orders'] ?? 0 }}</p>
                            <p class="text-xs text-white/70">Orders</p>
                        </div>
                        <div>
                            <p class="text-lg font-bold fa-num">{{ \App\Support\Money::toman((int) ($life['total_spent'] ?? 0)) }}</p>
                            <p class="text-xs text-white/70">Total Spent</p>
                        </div>
                    </div>
                </div>
                @if (!empty($crm['recent_purchases']))
                    <div class="mt-5 border-t border-white/15 pt-4">
                        <p class="mb-2 text-xs text-white/70">Recent purchases (in-store &amp; online)</p>
                        <ul class="space-y-1 text-sm">
                            @foreach (array_slice($crm['recent_purchases'], 0, 5) as $p)
                                <li class="flex justify-between text-white/90">
                                    <span>{{ $p['product'] }} @if(!empty($p['size']))<span class="text-white/60">({{ $p['size'] }})</span>@endif</span>
                                    <span class="fa-num text-white/60" dir="ltr">{{ $p['date'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        {{-- Tabbed navigation --}}
        <div x-data="{ tab: 'profile' }">
            <div class="mb-6 flex gap-1 border-b border-brand-100" role="tablist">
                <button @click="tab = 'profile'" role="tab" :aria-selected="tab === 'profile'"
                        class="rounded-t-lg px-5 py-3 text-sm font-medium transition"
                        :class="tab === 'profile' ? 'bg-white text-brand-900 border-b-2 border-brand-900' : 'text-brand-500 hover:text-brand-700 hover:bg-brand-50'">
                    <span class="flex items-center gap-2">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        Profile
                    </span>
                </button>
                <button @click="tab = 'addresses'" role="tab" :aria-selected="tab === 'addresses'"
                        class="rounded-t-lg px-5 py-3 text-sm font-medium transition"
                        :class="tab === 'addresses' ? 'bg-white text-brand-900 border-b-2 border-brand-900' : 'text-brand-500 hover:text-brand-700 hover:bg-brand-50'">
                    <span class="flex items-center gap-2">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/></svg>
                        Addresses
                    </span>
                </button>
                <button @click="tab = 'orders'" role="tab" :aria-selected="tab === 'orders'"
                        class="rounded-t-lg px-5 py-3 text-sm font-medium transition"
                        :class="tab === 'orders' ? 'bg-white text-brand-900 border-b-2 border-brand-900' : 'text-brand-500 hover:text-brand-700 hover:bg-brand-50'">
                    <span class="flex items-center gap-2">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Orders
                    </span>
                </button>
            </div>

            {{-- Profile tab --}}
            <section x-show="tab === 'profile'" x-cloak
                     class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">Your Details</h2>
                <form action="{{ route('account.profile') }}" method="POST" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <label class="mb-1 block text-xs text-brand-500">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm outline-none focus:border-brand-400">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-brand-500">Mobile</label>
                        <input type="text" value="{{ $user->phone }}" dir="ltr" disabled class="w-full rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-center text-sm text-brand-500 fa-num">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-brand-500">Email (optional)</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" dir="ltr" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm outline-none focus:border-brand-400">
                    </div>
                    <button class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-800">Save</button>
                </form>

                {{-- Telegram connection: get order updates in Telegram --}}
                <div class="mt-6 border-t border-brand-100 pt-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="flex items-center gap-2 text-sm font-bold text-brand-900">
                                <svg viewBox="0 0 24 24" class="h-5 w-5 text-[#229ED9]" fill="currentColor"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71l-4.14-3.05-1.99 1.93c-.23.23-.42.42-.86.42z"/></svg>
                                Telegram Connection
                            </h3>
                            @if ($telegramChat)
                                <p class="mt-1 text-xs text-green-600">Your account is connected to Telegram — order updates arrive there.</p>
                            @else
                                <p class="mt-1 text-xs leading-6 text-brand-500">Connect Telegram to receive order confirmations and shipping updates straight to your chat.</p>
                            @endif
                        </div>
                        @if ($telegramChat)
                            <form action="{{ route('account.telegram.disconnect') }}" method="POST">
                                @csrf
                                <button class="shrink-0 rounded-lg border border-brand-200 px-4 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50">Disconnect</button>
                            </form>
                        @elseif ($telegramReady)
                            <a href="{{ route('account.telegram.connect') }}"
                               class="shrink-0 rounded-lg bg-[#229ED9] px-4 py-2 text-xs font-semibold text-white hover:bg-[#1c8ec2]">Connect Telegram</a>
                        @else
                            <span class="shrink-0 self-center text-xs text-brand-300">Coming soon</span>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Addresses tab --}}
            <section x-show="tab === 'addresses'" x-cloak
                     class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">Addresses</h2>
                <div class="space-y-2">
                    @forelse ($addresses as $address)
                        <div class="flex items-start justify-between rounded-lg bg-brand-50 p-3 text-sm">
                            <div>
                                <p class="font-medium text-brand-800">{{ $address->recipient_name }} — <span class="fa-num" dir="ltr">{{ $address->phone }}</span></p>
                                <p class="mt-1 text-xs text-brand-500">{{ $address->province }}، {{ $address->city }} — {{ $address->line }}</p>
                            </div>
                            <form action="{{ route('account.addresses.destroy', $address) }}" method="POST">
                                @csrf @method('DELETE')
                                <button class="text-xs text-brand-500 hover:text-red-500">Remove</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-brand-500">You haven't added an address yet.</p>
                    @endforelse
                </div>

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-accent-600">+ Add a new address</summary>
                    <form action="{{ route('account.addresses.store') }}" method="POST" class="mt-3 grid grid-cols-2 gap-2">
                        @csrf
                        <input name="recipient_name" value="{{ old('recipient_name', $user->name) }}" placeholder="Recipient name" class="rounded-lg border border-brand-200 px-3 py-2 text-sm" required>
                        <input name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Recipient mobile" dir="ltr" class="rounded-lg border border-brand-200 px-3 py-2 text-sm" required>
                        <x-province-select name="province" required />
                        <input name="city" placeholder="City" class="rounded-lg border border-brand-200 px-3 py-2 text-sm" required>
                        <input name="postal_code" placeholder="Postal code" dir="ltr" class="col-span-2 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <textarea name="line" placeholder="Full address" class="col-span-2 rounded-lg border border-brand-200 px-3 py-2 text-sm" required></textarea>
                        <label class="col-span-2 flex items-center gap-2 text-xs text-brand-500"><input type="checkbox" name="is_default" value="1"> Default address</label>
                        <button class="col-span-2 rounded-lg bg-brand-900 py-2 text-sm font-semibold text-white">Save address</button>
                    </form>
                </details>
            </section>

            {{-- Orders tab --}}
            <section x-show="tab === 'orders'" x-cloak
                     class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-brand-900">Recent Orders</h2>
                    <a href="{{ route('account.orders') }}" class="text-sm text-accent-600 hover:underline">All orders</a>
                </div>
                @forelse ($orders as $order)
                    <a href="{{ route('account.orders.show', $order) }}" class="flex items-center justify-between border-b border-brand-50 py-3 text-sm last:border-0">
                        <span class="font-medium text-brand-800 fa-num" dir="ltr">{{ $order->number }}</span>
                        <span class="text-brand-500">{{ $order->statusLabel() }}</span>
                        <span class="font-bold text-brand-900">{{ $order->formattedTotal() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-brand-500">You haven't placed an order yet.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
