{{--
    Black Friday floating bar, from Campaigns::floatingBars() (config campaigns.floating_bars,
    campaign-gated and dated like every other piece). Shown on every page except checkout.
    Phone: full-width bar above the bottom tab bar. lg+: a centred pill at the bottom.
    The collapse button minimises it to a small pill; that choice is kept in sessionStorage,
    so each page opens the minimised pill. The bar stacks itself above whichever fixed bottom
    element is on screen (cookie banner, mobile add-to-cart bar, tab bar), and publishes its
    own height as --bf-lift so the free-shipping widget, scroll-to-top and the footer clear it.
--}}
@php($__bf = \App\Support\Campaigns::floatingBars()[0] ?? null)
@if ($__bf && ! request()->routeIs('checkout.*'))
    <aside id="bf-bar" dir="rtl" role="region" aria-label="{{ $__bf['label'] }}" x-cloak
           x-data="{
               bar: @js($__bf),
               min: false, how: false, copied: false, base: 0,
               key: 'bf_bar_min',
               init() {
                   try { this.min = sessionStorage.getItem(this.key) === '1' } catch (e) {}
                   const run = () => { this.measure(); this.publish() };
                   let queued = false;
                   const soon = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; run() }) };
                   window.addEventListener('resize', soon); window.addEventListener('scroll', soon, { passive: true });
                   window.addEventListener('click', () => setTimeout(soon, 50));
                   new MutationObserver(soon).observe(document.body, { attributes: true, subtree: true, attributeFilter: ['class', 'style'] });
                   if (window.ResizeObserver) new ResizeObserver(soon).observe(this.$el);
                   setInterval(soon, 800);
                   this.$nextTick(run);
               },
               // Height of the tallest fixed bottom element currently on screen.
               measure() {
                   let h = 0;
                   for (const sel of ['#cookie-banner', '#sticky-atc', 'nav[aria-label]']) {
                       const el = document.querySelector(sel);
                       if (!el || !el.getClientRects().length) continue;
                       if (getComputedStyle(el).position !== 'fixed') continue;
                       const r = el.getBoundingClientRect();
                       if (r.height > 0 && r.top < window.innerHeight - 1) h = Math.max(h, window.innerHeight - r.top);
                   }
                   this.base = Math.round(h);
               },
               publish() {
                   // --bf-lift: how far up the bar's top edge is, for the widgets that must clear it;
                   // --bf-space: the bar's own height, for the footer's bottom padding.
                   const r = this.$el.getBoundingClientRect(), st = document.documentElement.style;
                   st.setProperty('--bf-lift', Math.max(0, Math.round(window.innerHeight - r.top)) + 'px');
                   st.setProperty('--bf-space', Math.round(r.height + 8) + 'px');
               },
               setMin(v) { this.min = v; this.how = false; try { v ? sessionStorage.setItem(this.key, '1') : sessionStorage.removeItem(this.key) } catch (e) {} },
               async copy() {
                   try { await navigator.clipboard.writeText(this.bar.code) } catch (e) {
                       const t = document.createElement('textarea'); t.value = this.bar.code; t.style.cssText = 'position:fixed;opacity:0';
                       document.body.appendChild(t); t.select(); try { document.execCommand('copy') } catch (e2) {} t.remove();
                   }
                   this.copied = true; setTimeout(() => this.copied = false, 1800);
               },
           }"
           :style="{ bottom: base ? (base + 8) + 'px' : 'max(12px, env(safe-area-inset-bottom, 0px))' }"
           class="fixed z-[41] text-white inset-x-0 max-lg:px-2 lg:mx-auto lg:w-[min(54rem,calc(100vw-2rem))]">

        {{-- Expanded --}}
        <div x-show="!min" x-transition.opacity.duration.200ms
             class="rounded-2xl bg-brand-900 px-3.5 py-3 shadow-[0_8px_30px_rgba(24,35,79,.35)] ring-1 ring-white/10 lg:px-5">
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <span class="rounded-sm bg-accent-600 px-2 py-0.5 text-[11px] font-bold leading-snug" x-text="bar.label"></span>
                        <span class="text-xs font-semibold lg:text-sm" x-text="bar.text"></span>
                        <span x-show="bar.ends" class="text-[11px] text-white/70" x-text="bar.ends"></span>
                    </div>

                    <template x-if="bar.tiers.length">
                        <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] font-medium text-white/90 lg:text-xs">
                            <template x-for="t in bar.tiers" :key="t">
                                <li class="flex items-center gap-1.5 before:inline-block before:h-1 before:w-1 before:rounded-full before:bg-sand" x-text="t"></li>
                            </template>
                        </ul>
                    </template>

                    <p class="mt-1.5 text-[11px] leading-5 text-white/75" x-show="bar.note || bar.exclusion">
                        <span x-text="bar.note"></span>
                        <span x-show="bar.exclusion" :class="how ? 'inline' : 'hidden lg:inline'" x-text="bar.exclusion"></span>
                    </p>

                    <div x-show="bar.code" class="mt-2 flex items-center gap-2">
                        <span class="rounded-md border border-dashed border-sand/70 px-2.5 py-1 font-mono text-sm font-bold tracking-wider" dir="ltr" x-text="bar.code"></span>
                        <button type="button" @click="copy()"
                                class="rounded-full bg-white px-3 py-1 text-[11px] font-bold text-brand-900 transition hover:bg-brand-50 active:scale-95">
                            <span x-text="copied ? 'کپی شد' : 'کپی کد'"></span>
                        </button>
                    </div>

                    {{-- How to buy: inline on lg+, a toggled panel on phones --}}
                    <template x-if="bar.steps.length">
                        <div class="mt-2">
                            <button type="button" @click="how = !how" :aria-expanded="how"
                                    class="flex items-center gap-1 text-[11px] font-semibold text-sand lg:hidden">
                                چطور بخرم؟
                                <svg class="h-3 w-3 transition" :class="how ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <ol :class="how ? 'mt-1.5 grid' : 'hidden lg:flex'"
                                class="gap-1.5 text-[11px] leading-5 text-white/85 lg:flex-wrap lg:gap-x-5 lg:text-xs">
                                <template x-for="(s, i) in bar.steps" :key="i">
                                    <li class="flex items-center gap-1.5">
                                        <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-white/15 text-[10px] font-bold" x-text="['۱','۲','۳','۴'][i]"></span>
                                        <span x-text="s"></span>
                                    </li>
                                </template>
                            </ol>
                        </div>
                    </template>

                </div>

                <div class="flex shrink-0 flex-col items-end gap-2">
                    <button type="button" @click="setMin(true)" aria-label="کوچک کردن نوار"
                            class="grid h-7 w-7 place-items-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <a :href="bar.url" class="whitespace-nowrap rounded-full bg-white px-3.5 py-1.5 text-xs font-bold text-brand-900 transition hover:bg-brand-50" x-text="bar.cta"></a>
                </div>
            </div>
        </div>

        {{-- Minimised pill --}}
        <div x-show="min" x-transition.opacity.duration.200ms class="flex max-lg:justify-start lg:justify-center">
            <div class="flex items-center gap-2 rounded-full bg-brand-900 py-1.5 ps-1.5 pe-3 text-[11px] shadow-[0_6px_20px_rgba(24,35,79,.35)] ring-1 ring-white/10 lg:text-xs">
                <button type="button" @click="setMin(false)" aria-label="باز کردن نوار" class="flex items-center gap-2">
                    <span class="rounded-full bg-accent-600 px-2.5 py-1 font-bold leading-none" x-text="bar.label"></span>
                    <span class="font-medium text-white/90" x-text="bar.mini"></span>
                    <svg class="h-3 w-3 text-white/70" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m18 15-6-6-6 6"/></svg>
                </button>
                <template x-if="bar.code">
                    <span class="flex items-center gap-1.5">
                        <span class="rounded-md border border-dashed border-sand/70 px-2 py-0.5 font-mono font-bold tracking-wider" dir="ltr" x-text="bar.code"></span>
                        <button type="button" @click="copy()" class="rounded-full bg-white px-2.5 py-0.5 font-bold text-brand-900 active:scale-95" x-text="copied ? 'کپی شد' : 'کپی'"></button>
                    </span>
                </template>
            </div>
        </div>
    </aside>
@endif
