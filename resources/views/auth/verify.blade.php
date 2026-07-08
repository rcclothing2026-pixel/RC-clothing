@extends('layouts.app')

@section('title', 'Verify Code | Racket Club')

@section('content')
    <div class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="rounded-card bg-white p-8 ring-1 ring-brand-100">
            <h1 class="text-xl font-bold uppercase tracking-wide text-brand-900">VERIFICATION CODE</h1>
            <p class="mt-2 text-sm text-brand-500">
                Enter the code we sent to <span class="font-semibold text-brand-800 fa-num" dir="ltr">{{ $phone }}</span>.
            </p>

            @if (session('dev_code'))
                <div class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    Dev code (log mode): <span class="font-bold fa-num">{{ session('dev_code') }}</span>
                </div>
            @endif

            @php($otpLen = (int) config('sms.otp_length', 5))
            @php($resendSeconds = (int) config('sms.otp_resend_seconds', 60))
            <form action="{{ route('login.verify') }}" method="POST" class="mt-6"
                  x-data="{
                      len: {{ $otpLen }},
                      code: Array.from({ length: {{ $otpLen }} }, () => ''),
                      cooldown: {{ $resendSeconds }},
                      resending: false,
                      init() {
                          if (this.cooldown > 0) this.startTimer();
                      },
                      startTimer() {
                          this.cooldown = {{ $resendSeconds }};
                          const tick = () => {
                              this.cooldown--;
                              if (this.cooldown > 0) setTimeout(tick, 1000);
                          };
                          setTimeout(tick, 1000);
                      },
                      onInput(el) {
                          el.value = el.value.replace(/\D/g, '').slice(0, 1);
                          if (el.value && el.nextElementSibling) el.nextElementSibling.focus();
                      },
                      onBackspace(el) {
                          if (!el.value && el.previousElementSibling) el.previousElementSibling.focus();
                      },
                      handlePaste(e) {
                          const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, this.len);
                          if (!paste) return;
                          e.preventDefault();
                          this.code = Array.from({ length: this.len }, (_, i) => paste[i] || '');
                          if (paste.length >= this.len) this.$nextTick(() => this.$root.requestSubmit());
                      },
                      submit() {
                          if (this.code.every(d => d)) this.$root.requestSubmit();
                      },
                      resend() {
                          this.resending = true;
                          fetch('{{ route('login.otp') }}', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                              body: 'phone={{ $phone }}'
                          }).then(r => r.json()).then(() => {
                              this.resending = false;
                              this.startTimer();
                              this.code = Array.from({ length: this.len }, () => '');
                              this.$root.querySelector('input[inputmode=numeric]').focus();
                          }).catch(() => { this.resending = false; });
                      }
                  }">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <input type="hidden" name="code" :value="code.join('')">

                <div class="flex items-center justify-center gap-2" dir="ltr">
                    <template x-for="(_, i) in len" :key="i">
                        <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code"
                               x-model="code[i]"
                               @input="onInput($event.target)"
                               @keydown.backspace="onBackspace($event.target)"
                               @paste="handlePaste"
                               @keydown.enter.prevent="submit()"
                               class="h-14 w-12 rounded-xl border-2 text-center text-xl font-bold outline-none transition-all duration-150"
                               :class="code[i] ? 'border-brand-900 bg-brand-50' : 'border-brand-200 bg-white hover:border-brand-400 focus:border-brand-900 focus:ring-2 focus:ring-brand-100'">
                    </template>
                </div>
                @error('code')<p class="mt-3 text-center text-xs text-red-500">{{ $message }}</p>@enderror

                <div class="mt-6 space-y-3">
                    <button type="submit"
                            class="w-full rounded-full bg-brand-900 py-3 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-50"
                            :disabled="!code.every(d => d)">
                        Verify
                    </button>

                    <p class="text-center text-xs text-brand-500" aria-live="polite">
                        <template x-if="cooldown > 0">
                            <span>Resend code in <span class="font-bold fa-num" x-text="cooldown"></span> seconds</span>
                        </template>
                        <template x-if="cooldown <= 0">
                            <button type="button" @click="resend()" :disabled="resending"
                                    class="text-accent-600 hover:underline disabled:opacity-50"
                                    x-text="resending ? 'Sending...' : 'Resend code'"></button>
                        </template>
                    </p>
                </div>
            </form>

            <a href="{{ route('login') }}" class="mt-5 block text-center text-xs text-accent-600 hover:underline">Edit mobile number</a>
        </div>
    </div>
@endsection
