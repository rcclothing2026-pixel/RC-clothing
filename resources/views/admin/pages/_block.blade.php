{{--
    One block card in the editor. Props: $type, $data (values), $i (index or
    the __I__ placeholder for the JS template). Field names follow
    blocks[$i][data][key]; repeater rows add [rep][j][subkey]. The editor
    reconstructs ordered names on submit from the data-bk / data-rk attributes.

    Note: every PHP statement in this file uses the @php ... @endphp BLOCK form.
    The expression form @php(expr) silently breaks Laravel's raw-block regex
    when combined with a @php...@endphp block elsewhere in the same file —
    the regex greedily consumes content between the two, producing an invalid
    `<?php(` open tag and dumping the file as raw text to the browser.
--}}
@php $def = \App\Support\Blocks\BlockRegistry::all()[$type]; $vis = $vis ?? 'all'; @endphp
@php
    // Classify each field by viewport scope → drives the «🎨 محتوا · 🖥 دسکتاپ ·
    // 📱 موبایل» tab strip. A field is mobile-only if its key ends in _mobile
    // (or ^mobile_ / ^image_mobile), desktop-only if it ends in _desktop, else
    // shared. The «(دسکتاپ)»/«(موبایل)» label hint is also honoured.
    $groups = ['shared' => [], 'desktop' => [], 'mobile' => []];
    foreach ($def['fields'] as $field) {
        $k = (string) ($field['key'] ?? '');
        $label = (string) ($field['label'] ?? '');
        if (preg_match('/(_mobile$|^mobile_|^image_mobile$)/i', $k) || str_contains($label, 'موبایل')) {
            $groups['mobile'][] = $field;
        } elseif (preg_match('/(_desktop$|^desktop_)/i', $k) || str_contains($label, 'دسکتاپ')) {
            $groups['desktop'][] = $field;
        } else {
            $groups['shared'][] = $field;
        }
    }
    $tabMeta = ['shared' => '🎨 محتوا', 'desktop' => '🖥 دسکتاپ', 'mobile' => '📱 موبایل'];
    $activeTabs = array_keys(array_filter($groups, fn ($g) => ! empty($g)));
    $firstTab = $activeTabs[0] ?? 'shared';
    $showTabBar = count($activeTabs) > 1;
@endphp
{{-- Accordion card. The `.b-head` row toggles `is-collapsed` (editor JS closes
     siblings so one is open — a layers-spine feel). Controls live in a ⋮ menu
     to keep the header clean. Fields are split across tabs. --}}
<div class="block-card is-collapsed rounded-card bg-white ring-1 ring-brand-100" data-type="{{ $type }}" data-bid="{{ $bid ?? '' }}" draggable="true">
    <div class="b-head flex cursor-pointer items-center justify-between gap-2 px-3 py-2.5">
        <span class="flex min-w-0 items-center gap-2 text-sm font-semibold text-brand-800">
            <span class="b-grip cursor-grab text-brand-300 hover:text-brand-700" title="جابه‌جا کنید" aria-hidden="true">⋮⋮</span>
            <span class="b-chevron text-brand-300 transition-transform" aria-hidden="true">▸</span>
            <span class="truncate">{{ $def['icon'] }} {{ $def['label'] }}</span>
        </span>
        <div class="relative shrink-0">
            <button type="button" class="b-dots grid h-7 w-7 place-items-center rounded text-brand-400 hover:bg-brand-50" title="تنظیمات بلاک" aria-label="تنظیمات">⋮</button>
            {{-- Options popover — opened by the ⋮ button via the editor JS. --}}
            <div class="b-menu absolute end-0 top-full z-30 mt-1 hidden w-48 rounded-lg border border-brand-200 bg-white py-1 text-sm shadow-xl">
                <div class="px-3 py-1.5">
                    <span class="mb-1 block text-[11px] text-brand-400">نمایش بلاک</span>
                    <select data-bk="_v" name="blocks[{{ $i }}][_v]"
                            class="w-full rounded-md border border-brand-200 bg-white px-2 py-1 text-xs text-brand-700">
                        <option value="all"     @selected($vis === 'all')>👁 همه</option>
                        <option value="desktop" @selected($vis === 'desktop')>🖥 فقط دسکتاپ</option>
                        <option value="mobile"  @selected($vis === 'mobile')>📱 فقط موبایل</option>
                        <option value="auth"    @selected($vis === 'auth')>🔒 فقط کاربران وارد شده</option>
                        <option value="guest"   @selected($vis === 'guest')>👋 فقط مهمان‌ها</option>
                    </select>
                </div>
                <div class="my-1 border-t border-brand-100"></div>
                <button type="button" class="b-up flex w-full items-center gap-2 px-3 py-1.5 text-brand-700 hover:bg-brand-50"><span>▲</span> انتقال به بالا</button>
                <button type="button" class="b-down flex w-full items-center gap-2 px-3 py-1.5 text-brand-700 hover:bg-brand-50"><span>▼</span> انتقال به پایین</button>
                <button type="button" class="b-save-pattern flex w-full items-center gap-2 px-3 py-1.5 text-brand-700 hover:bg-brand-50"><span>💾</span> ذخیره به‌عنوان الگو</button>
                <div class="my-1 border-t border-brand-100"></div>
                <button type="button" class="b-del flex w-full items-center gap-2 px-3 py-1.5 text-red-500 hover:bg-red-50"><span>✕</span> حذف بلاک</button>
            </div>
        </div>
    </div>
    <div class="b-body border-t border-brand-50">
    <input type="hidden" data-bk="type" name="blocks[{{ $i }}][type]" value="{{ $type }}">
    <input type="hidden" data-bk="_bid" name="blocks[{{ $i }}][_bid]" value="{{ $bid ?? '' }}">
    @if ($type === 'hero_banner')
        <div class="p-4 text-sm text-brand-500">
            محتوای این بنر در صفحه‌ی اختصاصی <a href="{{ route('admin.hero.edit') }}" class="font-medium text-accent-600 hover:underline">«بنر هیرو»</a> ویرایش می‌شود (اسلایدها، محصولات، رنگ‌ها و تصاویر).
        </div>
    @endif

    {{-- Tab bar: content viewport groups + a universal «پیشرفته» (style) tab.
         Always shown now, since every block has the Advanced style controls. --}}
    <div class="b-tabs flex flex-wrap gap-1 border-b border-brand-100 px-3 pt-2">
        @foreach ($activeTabs as $tk)
            <button type="button" class="b-tab rounded-t-md px-3 py-1.5 text-xs font-medium {{ $tk === $firstTab ? 'is-active' : 'text-brand-500' }}" data-tab="{{ $tk }}">{{ $tabMeta[$tk] }}</button>
        @endforeach
        <button type="button" class="b-tab rounded-t-md px-3 py-1.5 text-xs font-medium text-brand-500" data-tab="advanced">⚙️ پیشرفته</button>
    </div>

    {{-- Tab panels --}}
    @foreach ($activeTabs as $tk)
        <div class="b-tab-panel {{ $tk === $firstTab ? '' : 'hidden' }}" data-tab-panel="{{ $tk }}">
            <div class="grid gap-3 p-4 sm:grid-cols-2">
                @foreach ($groups[$tk] as $field)
                    @if (($field['type'] ?? null) === 'repeater')
                        <div class="rep-container sm:col-span-2 rounded-lg bg-brand-50 p-3" data-rep="{{ $field['key'] }}">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold text-brand-700">{{ $field['label'] }}</span>
                                <button type="button" class="rep-add rounded-lg bg-accent-600 px-3 py-1 text-xs font-medium text-white hover:bg-accent-700">+ افزودن</button>
                            </div>
                            <div class="rep-items space-y-2">
                                @php
                                    $rows = $data[$field['key']] ?? [];
                                    if (! is_array($rows) || ! count($rows)) $rows = [[]];
                                @endphp
                                @foreach ($rows as $j => $row)
                                    @include('admin.pages._repitem', ['sub' => $field['sub'], 'i' => $i, 'rep' => $field['key'], 'j' => $j, 'row' => (array) $row])
                                @endforeach
                            </div>
                            <template class="rep-tpl">@include('admin.pages._repitem', ['sub' => $field['sub'], 'i' => $i, 'rep' => $field['key'], 'j' => '__J__', 'row' => []])</template>
                        </div>
                    @else
                        <div class="{{ in_array($field['type'], ['textarea','richtext','lines','image','code'], true) ? 'sm:col-span-2' : '' }}">
                            @include('admin.pages._field', [
                                'field' => $field,
                                'name' => "blocks[$i][data][{$field['key']}]",
                                'bk' => "data.{$field['key']}",
                                'val' => $data[$field['key']] ?? '',
                            ])
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Advanced (style) tab panel — universal per-block controls. --}}
    <div class="b-tab-panel hidden" data-tab-panel="advanced">
    {{-- Universal size & spacing (Elementor-style «پیشرفته») — applies inline
         CSS to the block wrapper via data._style. Available on every block. --}}
    @php($st = (array) ($data['_style'] ?? []))
    <details class="mt-2 border-t border-brand-100 px-3 py-2" open>
        <summary class="cursor-pointer text-xs font-semibold text-brand-600">📐 اندازه و فاصله (پیشرفته)</summary>
        <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-brand-500">
            <label>فاصله بالا (px)<input type="number" min="0" data-bk="data._style.mt" name="blocks[{{ $i }}][data][_style][mt]" value="{{ $st['mt'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>فاصله پایین (px)<input type="number" min="0" data-bk="data._style.mb" name="blocks[{{ $i }}][data][_style][mb]" value="{{ $st['mb'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>پدینگ بالا (px)<input type="number" min="0" data-bk="data._style.pt" name="blocks[{{ $i }}][data][_style][pt]" value="{{ $st['pt'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>پدینگ پایین (px)<input type="number" min="0" data-bk="data._style.pb" name="blocks[{{ $i }}][data][_style][pb]" value="{{ $st['pb'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>پدینگ چپ/راست (px)<input type="number" min="0" data-bk="data._style.px" name="blocks[{{ $i }}][data][_style][px]" value="{{ $st['px'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>حداکثر عرض (px)<input type="number" min="0" data-bk="data._style.maxw" name="blocks[{{ $i }}][data][_style][maxw]" value="{{ $st['maxw'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>چیدمان
                <select data-bk="data._style.align" name="blocks[{{ $i }}][data][_style][align]" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800">
                    <option value="" @selected(($st['align'] ?? '') === '')>پیش‌فرض</option>
                    <option value="center" @selected(($st['align'] ?? '') === 'center')>وسط‌چین</option>
                </select></label>
            <label>رنگ پس‌زمینه<input type="text" data-bk="data._style.bg" name="blocks[{{ $i }}][data][_style][bg]" value="{{ $st['bg'] ?? '' }}" placeholder="#ffffff" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>حداقل ارتفاع (px)<input type="number" min="0" data-bk="data._style.minh" name="blocks[{{ $i }}][data][_style][minh]" value="{{ $st['minh'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>گردی گوشه (px)<input type="number" min="0" data-bk="data._style.radius" name="blocks[{{ $i }}][data][_style][radius]" value="{{ $st['radius'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>ضخامت کادر (px)<input type="number" min="0" data-bk="data._style.bw" name="blocks[{{ $i }}][data][_style][bw]" value="{{ $st['bw'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>رنگ کادر<input type="text" data-bk="data._style.bc" name="blocks[{{ $i }}][data][_style][bc]" value="{{ $st['bc'] ?? '' }}" placeholder="#e5e7eb" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>سایه
                <select data-bk="data._style.shadow" name="blocks[{{ $i }}][data][_style][shadow]" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800">
                    <option value="" @selected(($st['shadow'] ?? '') === '')>بدون سایه</option>
                    <option value="sm" @selected(($st['shadow'] ?? '') === 'sm')>کم</option>
                    <option value="md" @selected(($st['shadow'] ?? '') === 'md')>متوسط</option>
                    <option value="lg" @selected(($st['shadow'] ?? '') === 'lg')>زیاد</option>
                </select></label>
            <label>تراز متن
                <select data-bk="data._style.ta" name="blocks[{{ $i }}][data][_style][ta]" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800">
                    <option value="" @selected(($st['ta'] ?? '') === '')>پیش‌فرض</option>
                    <option value="right" @selected(($st['ta'] ?? '') === 'right')>راست</option>
                    <option value="center" @selected(($st['ta'] ?? '') === 'center')>وسط</option>
                    <option value="left" @selected(($st['ta'] ?? '') === 'left')>چپ</option>
                </select></label>
            <label>رنگ متن<input type="text" data-bk="data._style.tc" name="blocks[{{ $i }}][data][_style][tc]" value="{{ $st['tc'] ?? '' }}" placeholder="#111111" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>ضخامت فونت
                <select data-bk="data._style.fw" name="blocks[{{ $i }}][data][_style][fw]" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800">
                    <option value="" @selected(($st['fw'] ?? '') === '')>پیش‌فرض</option>
                    <option value="300" @selected((string)($st['fw'] ?? '') === '300')>نازک</option>
                    <option value="400" @selected((string)($st['fw'] ?? '') === '400')>عادی</option>
                    <option value="500" @selected((string)($st['fw'] ?? '') === '500')>نیمه‌ضخیم</option>
                    <option value="600" @selected((string)($st['fw'] ?? '') === '600')>ضخیم</option>
                    <option value="700" @selected((string)($st['fw'] ?? '') === '700')>خیلی ضخیم</option>
                    <option value="800" @selected((string)($st['fw'] ?? '') === '800')>سیاه</option>
                </select></label>
            <label>ارتفاع خط<input type="text" data-bk="data._style.lh" name="blocks[{{ $i }}][data][_style][lh]" value="{{ $st['lh'] ?? '' }}" placeholder="۱٫۶" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>فاصله حروف (px)<input type="number" data-bk="data._style.ls" name="blocks[{{ $i }}][data][_style][ls]" value="{{ $st['ls'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
        </div>
    </details>

    {{-- Per-breakpoint: mobile (<768px) overrides for spacing & size. --}}
    @php($sm = (array) ($st['m'] ?? []))
    <details class="mt-1 border-t border-brand-100 px-3 py-2">
        <summary class="cursor-pointer text-xs font-semibold text-brand-600">📱 اندازه و فاصله — موبایل</summary>
        <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-brand-500">
            @foreach (['mt' => 'فاصله بالا', 'mb' => 'فاصله پایین', 'pt' => 'پدینگ بالا', 'pb' => 'پدینگ پایین', 'px' => 'پدینگ چپ/راست', 'maxw' => 'حداکثر عرض', 'minh' => 'حداقل ارتفاع'] as $mk => $ml)
                <label>{{ $ml }} (px)<input type="number" min="0" data-bk="data._style.m.{{ $mk }}" name="blocks[{{ $i }}][data][_style][m][{{ $mk }}]" value="{{ $sm[$mk] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            @endforeach
            <label>تراز متن
                <select data-bk="data._style.m.ta" name="blocks[{{ $i }}][data][_style][m][ta]" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800">
                    <option value="" @selected(($sm['ta'] ?? '') === '')>پیش‌فرض</option>
                    <option value="right" @selected(($sm['ta'] ?? '') === 'right')>راست</option>
                    <option value="center" @selected(($sm['ta'] ?? '') === 'center')>وسط</option>
                    <option value="left" @selected(($sm['ta'] ?? '') === 'left')>چپ</option>
                </select></label>
            <label>رنگ پس‌زمینه<input type="text" data-bk="data._style.m.bg" name="blocks[{{ $i }}][data][_style][m][bg]" value="{{ $sm['bg'] ?? '' }}" placeholder="#ffffff" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>رنگ متن<input type="text" data-bk="data._style.m.tc" name="blocks[{{ $i }}][data][_style][m][tc]" value="{{ $sm['tc'] ?? '' }}" placeholder="#111111" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>ضخامت فونت
                <select data-bk="data._style.m.fw" name="blocks[{{ $i }}][data][_style][m][fw]" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800">
                    <option value="" @selected(($sm['fw'] ?? '') === '')>پیش‌فرض</option>
                    <option value="300" @selected((string)($sm['fw'] ?? '') === '300')>نازک</option>
                    <option value="400" @selected((string)($sm['fw'] ?? '') === '400')>عادی</option>
                    <option value="500" @selected((string)($sm['fw'] ?? '') === '500')>نیمه‌ضخیم</option>
                    <option value="600" @selected((string)($sm['fw'] ?? '') === '600')>ضخیم</option>
                    <option value="700" @selected((string)($sm['fw'] ?? '') === '700')>خیلی ضخیم</option>
                    <option value="800" @selected((string)($sm['fw'] ?? '') === '800')>سیاه</option>
                </select></label>
            <label>ارتفاع خط<input type="text" data-bk="data._style.m.lh" name="blocks[{{ $i }}][data][_style][m][lh]" value="{{ $sm['lh'] ?? '' }}" placeholder="۱٫۶" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
            <label>فاصله حروف (px)<input type="number" data-bk="data._style.m.ls" name="blocks[{{ $i }}][data][_style][m][ls]" value="{{ $sm['ls'] ?? '' }}" class="mt-1 w-full rounded border border-brand-200 px-2 py-1 text-brand-800"></label>
        </div>
    </details>
    </div>{{-- /.b-tab-panel advanced --}}
    </div>{{-- /.b-body --}}
</div>
