@extends('admin.layout')

@section('title', 'تنظیمات سایت')

@php($val = fn ($k, $d = '') => old(str_replace('.', '_', $k), $settings[$k] ?? $d))

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">تنظیمات سایت</h1>
        <p class="mt-1 text-sm text-brand-500">اطلاعات تماس، شبکه‌های اجتماعی، نماد اعتماد و سئو.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.site.update') }}"
          class="max-w-3xl space-y-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf @method('PATCH')

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">نام فروشگاه</label>
                <input type="text" name="site_store_name" value="{{ $val('site.store_name', 'چیاکو') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">شعار</label>
                <input type="text" name="site_tagline" value="{{ $val('site.tagline') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">تماس</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">تلفن</label>
                <input type="text" name="site_contact_phone" value="{{ $val('site.contact_phone') }}" dir="ltr" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">ایمیل</label>
                <input type="text" name="site_contact_email" value="{{ $val('site.contact_email') }}" dir="ltr" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-brand-700">آدرس</label>
                <input type="text" name="site_address" value="{{ $val('site.address') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">شبکه‌های اجتماعی</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">اینستاگرام</label>
                <input type="text" name="site_instagram" value="{{ $val('site.instagram') }}" dir="ltr" placeholder="@chiiaco" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">تلگرام</label>
                <input type="text" name="site_telegram" value="{{ $val('site.telegram') }}" dir="ltr" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">واتساپ</label>
                <input type="text" name="site_whatsapp" value="{{ $val('site.whatsapp') }}" dir="ltr" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        {{-- Footer editorial closing statement (shows in the dark footer) --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">جملهٔ پایانی فوتر (متن کوتاه ادیتوریال)</label>
            <textarea name="site_footer_statement" rows="2"
                      placeholder="چیاکو، برندی ایرانی با ریشه در طراحی مدرن و سنتی‌های دست‌ساز. لباس‌هایی برای امروز."
                      class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm leading-7">{{ $val('site.footer_statement') }}</textarea>
            <p class="mt-1 text-xs text-brand-400">یک یا دو جمله که در فوتر تیره نمایش داده می‌شود.</p>
        </div>

        {{-- Promo bar slides — thin black strip above the header, auto-rotates --}}
        @php($promoRaw = $val('site.promo_slides'))
        @php($promoSlides = $promoRaw ? (json_decode($promoRaw, true) ?: []) : [])
        @if (! $promoSlides) @php($promoSlides = [['text' => '', 'url' => '']]) @endif
        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">نوار اعلان بالای سایت</h2>
        <p class="-mt-2 text-xs text-brand-500">پیام‌های ثابت بالای هدر که خودکار جابه‌جا می‌شوند. خالی بگذارید تا غیرفعال شود.</p>
        <div x-data="{
                slides: @js($promoSlides),
                add() { if (this.slides.length < 5) this.slides.push({text:'', url:''}) },
                remove(i) { this.slides.splice(i, 1); if (!this.slides.length) this.add() },
             }" class="space-y-2">
            <template x-for="(s, i) in slides" :key="i">
                <div class="flex gap-2">
                    <input type="text" :name="'promo_slides['+i+'][text]'" x-model="s.text"
                           placeholder="مثلاً «ارسال رایگان برای خرید بالای ۱ میلیون»"
                           class="flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <input type="text" :name="'promo_slides['+i+'][url]'" x-model="s.url"
                           placeholder="/shop (اختیاری)" dir="ltr"
                           class="w-44 rounded-lg border border-brand-200 px-3 py-2 text-xs">
                    <button type="button" @click="remove(i)"
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-red-400 ring-1 ring-brand-200 transition hover:bg-red-50 hover:text-red-600" aria-label="حذف">✕</button>
                </div>
            </template>
            <button type="button" @click="add()" x-show="slides.length < 5"
                    class="rounded-lg bg-brand-100 px-4 py-2 text-xs font-medium text-brand-700 transition hover:bg-brand-200">+ افزودن پیام</button>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">نمادهای اعتماد و سئو</h2>
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">کد HTML نماد اعتماد الکترونیکی (enamad.ir)</label>
            <textarea name="site_enamad_html" rows="3" dir="ltr" placeholder="<a ...>...</a>" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-xs font-mono">{{ $val('site.enamad_html') }}</textarea>
            <p class="mt-1 text-xs text-brand-400">کد دریافتی از پنل نماد اعتماد را اینجا قرار دهید. در فوتر سایت نمایش داده می‌شود.</p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">کد HTML نشان ساماندهی (logo.samandehi.ir)</label>
            <textarea name="site_samandehi_html" rows="3" dir="ltr" placeholder="<a ...>...</a>" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-xs font-mono">{{ $val('site.samandehi_html') }}</textarea>
            <p class="mt-1 text-xs text-brand-400">کد دریافتی از ساماندهی پیام‌رسان‌ها. در کنار نماد اعتماد در فوتر نمایش داده می‌شود.</p>
        </div>
        <div class="grid gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">عنوان سئو (پیش‌فرض)</label>
                <input type="text" name="site_seo_title" value="{{ $val('site.seo_title') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">توضیح متا (پیش‌فرض)</label>
                <textarea name="site_seo_description" rows="2" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ $val('site.seo_description') }}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">کلمات کلیدی</label>
                <input type="text" name="site_seo_keywords" value="{{ $val('site.seo_keywords') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">شناسه Google Analytics 4 (GA4)</label>
                <input type="text" name="site_ga4_id" value="{{ $val('site.ga4_id') }}" dir="ltr" placeholder="G-XXXXXXXXXX" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-brand-400">رویداد خرید (purchase) به‌صورت خودکار در صفحهٔ موفقیت پرداخت ارسال می‌شود.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">شناسهٔ Google Tag Manager (GTM)</label>
                <input type="text" name="site_gtm_id" value="{{ $val('site.gtm_id') }}" dir="ltr" placeholder="GTM-XXXXXXX" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-brand-400">می‌توانید فقط شناسه (مثلاً <code dir="ltr">GTM-W64N9PMC</code>) یا کل کد را paste کنید — شناسه به‌صورت خودکار استخراج و هر دو snippet در <code dir="ltr">&lt;head&gt;</code> و بعد از <code dir="ltr">&lt;body&gt;</code> به‌درستی قرار می‌گیرد.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">کد تأیید Google (Search Console)</label>
                <input type="text" name="site_google_verification" value="{{ $val('site.google_verification') }}" dir="ltr" placeholder="مقدار content از متاتگ تأیید" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-brand-400">روش «برچسب HTML» در Search Console — فقط مقدار <code dir="ltr">content</code> متاتگ <code dir="ltr">google-site-verification</code> را اینجا بگذارید.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">کد اسکریپت سفارشی (چت / Google Tag Manager)</label>
                <textarea name="site_live_chat_code" rows="4" dir="ltr" placeholder="&lt;script&gt;...&lt;/script&gt;" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-xs font-mono">{{ $val('site.live_chat_code') }}</textarea>
                <p class="mt-1 text-xs text-brand-400">کد HTML (تگ گوگل / Google Tag Manager / ابزار چت) را اینجا قرار دهید — در بخش <code dir="ltr">&lt;head&gt;</code> همهٔ صفحات قرار می‌گیرد. برای Google Analytics بهتر است فقط شناسهٔ <code dir="ltr">G-…</code> را در فیلد GA4 بالا بگذارید.</p>
            </div>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">فایل‌های سئو</h2>
        <div>
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('sitemap') }}" target="_blank" rel="noopener" class="rounded-lg bg-brand-50 px-3 py-2 font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-100" dir="ltr">sitemap.xml ↗</a>
                <a href="{{ route('robots') }}" target="_blank" rel="noopener" class="rounded-lg bg-brand-50 px-3 py-2 font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-100" dir="ltr">robots.txt ↗</a>
                <a href="{{ route('llms') }}" target="_blank" rel="noopener" class="rounded-lg bg-brand-50 px-3 py-2 font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-100" dir="ltr">llms.txt ↗</a>
            </div>
            <p class="mt-2 text-xs text-brand-400">این فایل‌ها به‌صورت خودکار از محصولات و دسته‌بندی‌های فعال ساخته می‌شوند. نقشهٔ سایت (sitemap.xml) را در Google Search Console ثبت کنید.</p>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">🎁 بسته‌بندی کادویی</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm text-brand-700 sm:col-span-2">
                <input type="checkbox" name="site_giftwrap_enabled" value="1" @checked($val('site.giftwrap_enabled'))>
                در صفحهٔ پرداخت آپشن «بسته‌بندی کادویی» را به مشتری نشان بده
            </label>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">قیمت به ازای هر کالا (تومان)</label>
                <input type="number" name="site_giftwrap_per_item_price" value="{{ $val('site.giftwrap_per_item_price') }}" min="0" placeholder="مثلاً ۲۰٬۰۰۰" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
                <p class="mt-1 text-xs text-brand-400">هزینه بسته‌بندی = این عدد × تعداد اقلام سفارش.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">رایگان برای خرید بالای (تومان)</label>
                <input type="number" name="site_giftwrap_free_over" value="{{ $val('site.giftwrap_free_over') }}" min="0" placeholder="۰ یعنی هرگز رایگان نباشد" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
                <p class="mt-1 text-xs text-brand-400">اگر جمع کالا‌های سبد از این مبلغ بیشتر باشد، بسته‌بندی رایگان می‌شود.</p>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-brand-700">متن توضیحی برای مشتری (اختیاری)</label>
                <input type="text" name="site_giftwrap_label" value="{{ $val('site.giftwrap_label') }}" placeholder="مثلاً: جعبهٔ شیک + کارت پیام چاپی" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-brand-400">کنار چک‌باکس بسته‌بندی در صفحهٔ پرداخت نمایش داده می‌شود.</p>
            </div>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">واترمارک فوتر سایت</h2>
        <div class="grid gap-4">
            <div class="flex items-start gap-4">
                <div id="footer-image-preview" class="shrink-0 rounded-lg border border-brand-200 bg-white p-2 {{ $val('site.footer_image') ? '' : 'hidden' }}">
                    @if ($val('site.footer_image'))
                        <img src="{{ $val('site.footer_image') }}" alt="واترمارک فوتر" class="block max-h-24 max-w-48 rounded object-contain">
                    @endif
                </div>
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium text-brand-700">تصویر واترمارک</label>
                    <p class="mb-2 text-xs text-brand-400">تصویری با عرض زیاد (مثلاً ۱۹۲۰px) برای زمینهٔ تمام‌عرض فوتر. JPEG, PNG, WebP — حداکثر ۵ مگابایت.</p>
                    <div class="flex flex-wrap gap-2">
                        <label class="cursor-pointer rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">
                            انتخاب و بارگذاری
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden"
                                   onchange="uploadFooterImage(this)">
                        </label>
                        <button type="button" id="remove-footer-image-btn"
                                class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600 {{ $val('site.footer_image') ? '' : 'hidden' }}"
                                onclick="removeFooterImage()">🗑 حذف تصویر</button>
                    </div>
                    <div id="footer-image-uploading" class="mt-2 hidden text-xs text-brand-400">در حال بارگذاری…</div>
                    <input type="hidden" name="site_footer_image" id="site_footer_image" value="{{ $val('site.footer_image') }}">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">Opacity (شفافیت واترمارک)</label>
                <div class="flex items-center gap-3">
                    <input type="range" name="site_footer_opacity" min="0.05" max="0.8" step="0.05"
                           value="{{ $val('site.footer_opacity', '0.15') }}"
                           oninput="document.getElementById('footer-op-val').textContent=this.value"
                           class="w-48">
                    <span id="footer-op-val" class="text-sm font-bold text-brand-700">{{ $val('site.footer_opacity', '0.15') }}</span>
                </div>
                <p class="mt-1 text-xs text-brand-400">مقادیر پایین (۰٫۱–۰٫۲) جلوۀ واترمارک ملایم می‌دهد. ۰٫۸ = پررنگ.</p>
            </div>
        </div>

        @push('scripts')
        <script>
        function uploadFooterImage(input) {
            const file = input.files?.[0];
            if (!file) return;
            const form = new FormData();
            form.append('image', file);
            const status = document.getElementById('footer-image-uploading');
            status.classList.remove('hidden');
            status.textContent = 'در حال بارگذاری…';
            fetch('{{ route('admin.settings.site.footer-image.upload') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: form,
            }).then(r => r.json()).then(d => {
                status.classList.add('hidden');
                if (d.ok) {
                    document.getElementById('site_footer_image').value = d.url;
                    const preview = document.getElementById('footer-image-preview');
                    preview.classList.remove('hidden');
                    preview.innerHTML = '<img src="' + d.url + '" alt="واترمارک فوتر" class="block max-h-24 max-w-48 rounded object-contain">';
                    document.getElementById('remove-footer-image-btn').classList.remove('hidden');
                } else {
                    alert('خطا در بارگذاری تصویر');
                }
            }).catch(() => {
                status.classList.add('hidden');
                alert('خطا در بارگذاری تصویر');
            });
        }
        function removeFooterImage() {
            if (!confirm('واترمارک فوتر حذف شود؟')) return;
            fetch('{{ route('admin.settings.site.footer-image.delete') }}', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            }).then(r => r.json()).then(d => {
                if (d.ok) {
                    document.getElementById('site_footer_image').value = '';
                    document.getElementById('footer-image-preview').classList.add('hidden');
                    document.getElementById('remove-footer-image-btn').classList.add('hidden');
                }
            });
        }
        </script>
        @endpush

        <div class="pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره تنظیمات</button>
        </div>
    </form>
@endsection
