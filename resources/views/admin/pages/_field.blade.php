{{--
    One field input. Props: $field, $name, $val, and ONE of $bk (data-bk, for
    block-level fields) or $rk (data-rk, for repeater sub-fields). The data-*
    attribute lets the editor reconstruct ordered names on submit.
--}}
@php($attr = isset($bk) ? 'data-bk="'.$bk.'"' : 'data-rk="'.$rk.'"')
<label class="mb-1 block text-xs font-medium text-brand-600">{{ $field['label'] }}</label>
@switch($field['type'])
    @case('textarea')
    @case('richtext')
        <textarea {!! $attr !!} name="{{ $name }}" rows="3" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ $val }}</textarea>
        @break
    @case('code')
        @include('admin.partials.html-editor', [
            'name' => $name,
            'value' => $val,
            'rows' => 16,
            'attr' => $attr,
            'placeholder' => 'محتوای صفحه را اینجا بنویسید — یا روی «کد HTML» بزنید تا مستقیم HTML بنویسید…',
        ])
        @break
    @case('lines')
        <textarea {!! $attr !!} name="{{ $name }}" rows="3" dir="auto" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ is_array($val) ? implode("\n", $val) : $val }}</textarea>
        @break
    @case('number')
        <input {!! $attr !!} type="number" name="{{ $name }}" value="{{ $val }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
        @break
    @case('datetime')
        {{-- Jalali date+time picker. Saves as «۱۴۰۴/۰۱/۰۱ ۱۲:۳۰» (Persian
             digits). Server-side, the block view passes through
             App\Support\Jalali::parseDateTime() to get a Gregorian Y-m-d H:i
             string that Carbon understands. --}}
        <input {!! $attr !!} type="text" name="{{ $name }}" value="{{ $val }}"
               data-jdp data-jdp-time="true" dir="ltr" autocomplete="off"
               placeholder="۱۴۰۴/۰۱/۰۱ ۱۲:۳۰"
               class="w-full rounded-lg border border-brand-200 px-3 py-2 text-center text-sm fa-num">
        @break
    @case('color')
        {{-- Native color picker + paired hex text input. Saves the hex
             (#rrggbb) to the underlying form field; the picker keeps in sync.
             Color blocks/strings get applied as inline styles in the block view. --}}
        <div class="flex items-center gap-2">
            <input type="color" value="{{ $val ?: '#ffffff' }}"
                   oninput="this.nextElementSibling.value = this.value"
                   class="h-9 w-12 cursor-pointer rounded border border-brand-200 bg-white p-1">
            <input {!! $attr !!} type="text" name="{{ $name }}" value="{{ $val }}"
                   dir="ltr" placeholder="#ffffff"
                   oninput="this.previousElementSibling.value = (/^#[0-9a-fA-F]{6}$/.test(this.value) ? this.value : this.previousElementSibling.value)"
                   class="flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>
        @break
    @case('select')
        {{-- A select whose options are exactly the 9 position keys renders as a
             visual 3×3 grid instead of a dropdown — applies to ANY block
             (editorial_hero position_desktop/mobile, hero_banner, etc.).
             Single-line @php() only: multi-line @php blocks inside @case break
             Blade's switch compiler. --}}
        @php($posKeys = ['tl','tc','tr','ml','mc','mr','bl','bc','br'])
        @php($isPosGrid = count(array_intersect(array_keys($field['options']), $posKeys)) === 9)
        @if ($isPosGrid)
            @php($posGlyph = ['tl'=>'↖','tc'=>'↑','tr'=>'↗','ml'=>'←','mc'=>'●','mr'=>'→','bl'=>'↙','bc'=>'↓','br'=>'↘'])
            @php($cur = (string) ($val ?: 'mc'))
            <div class="pos-control" dir="ltr">
                <input {!! $attr !!} type="hidden" name="{{ $name }}" value="{{ $cur }}" class="pos-value">
                <div class="pos-grid">
                    @foreach ($posKeys as $pk)
                        <button type="button" class="pos-cell {{ $cur === $pk ? 'is-active' : '' }}" data-pos="{{ $pk }}" title="{{ $field['options'][$pk] ?? $pk }}">{{ $posGlyph[$pk] }}</button>
                    @endforeach
                </div>
            </div>
        @else
            <select {!! $attr !!} name="{{ $name }}" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                @foreach ($field['options'] as $okey => $olabel)
                    <option value="{{ $okey }}" @selected((string) $val === (string) $okey)>{{ $olabel }}</option>
                @endforeach
            </select>
        @endif
        @break
    @case('range')
        @php($rMin = $field['min'] ?? 0)
        @php($rMax = $field['max'] ?? 100)
        @php($rStep = $field['step'] ?? 1)
        @php($rUnit = $field['unit'] ?? '')
        @php($rVal = ($val === '' || $val === null) ? ($field['default'] ?? $rMin) : $val)
        <div class="range-row" dir="ltr">
            <input {!! $attr !!} type="range" name="{{ $name }}" value="{{ $rVal }}"
                   min="{{ $rMin }}" max="{{ $rMax }}" step="{{ $rStep }}"
                   oninput="this.nextElementSibling.value = this.value + '{{ $rUnit }}'">
            <output>{{ $rVal }}{{ $rUnit }}</output>
        </div>
        @break
    @case('picker')
        @php($options = \App\Support\Blocks\BlockPicker::options($field['source'] ?? ''))
        @php($currentLabel = $options[$val] ?? '')
        <div class="picker relative" data-picker>
            <input type="text" class="picker-search w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="جستجو و انتخاب..." autocomplete="off" value="{{ $currentLabel }}">
            <input {!! $attr !!} type="hidden" name="{{ $name }}" value="{{ $val }}" class="picker-value">
            <div class="picker-list absolute z-30 mt-1 hidden max-h-56 w-full overflow-auto rounded-lg border border-brand-200 bg-white shadow-lg">
                <button type="button" class="picker-opt block w-full px-3 py-2 text-right text-sm text-brand-400 hover:bg-brand-50" data-value="" data-label="">— هیچ‌کدام —</button>
                @foreach ($options as $okey => $olabel)
                    <button type="button" class="picker-opt block w-full px-3 py-2 text-right text-sm text-brand-700 hover:bg-brand-50" data-value="{{ $okey }}" data-label="{{ $olabel }}">{{ $olabel }}</button>
                @endforeach
            </div>
        </div>
        @break
    @case('multipicker')
        @php($options = \App\Support\Blocks\BlockPicker::options($field['source'] ?? ''))
        @php($selected = array_values(array_filter(array_map('trim', explode(',', (string) $val)), fn ($s) => $s !== '')))
        <div class="multipicker relative" data-multipicker>
            <input {!! $attr !!} type="hidden" name="{{ $name }}" value="{{ implode(',', $selected) }}" class="mp-value">
            <div class="mp-chips mb-1.5 flex flex-wrap gap-1.5 {{ $selected ? '' : 'hidden' }}">
                @foreach ($selected as $slug)
                    <span class="mp-chip inline-flex items-center gap-1 rounded-full bg-accent-50 px-2.5 py-1 text-xs font-medium text-accent-700 ring-1 ring-accent-200" data-value="{{ $slug }}">
                        <span class="mp-chip-label">{{ $options[$slug] ?? $slug }}</span>
                        <button type="button" class="mp-chip-del leading-none text-accent-400 hover:text-accent-700" aria-label="حذف">×</button>
                    </span>
                @endforeach
            </div>
            <input type="text" class="mp-search w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="جستجو و انتخاب (چند مورد مجاز است)…" autocomplete="off">
            <div class="mp-list absolute z-30 mt-1 hidden max-h-56 w-full overflow-auto rounded-lg border border-brand-200 bg-white shadow-lg">
                @forelse ($options as $okey => $olabel)
                    <button type="button" class="mp-opt block w-full px-3 py-2 text-right text-sm text-brand-700 hover:bg-brand-50 {{ in_array((string) $okey, $selected, true) ? 'hidden' : '' }}" data-value="{{ $okey }}" data-label="{{ $olabel }}">{{ $olabel }}</button>
                @empty
                    <span class="block px-3 py-2 text-right text-sm text-brand-400">موردی موجود نیست</span>
                @endforelse
            </div>
        </div>
        @break
    @case('image')
        <div class="img-field flex items-center gap-3">
            <input {!! $attr !!} type="hidden" name="{{ $name }}" value="{{ $val }}" class="img-url">
            <div class="img-preview grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-brand-50 text-xs text-brand-300 ring-1 ring-brand-100">
                @if ($val)<img src="{{ $val }}" alt="" class="h-full w-full object-cover">@else بدون تصویر @endif
            </div>
            <label class="shrink-0 cursor-pointer rounded-lg bg-brand-100 px-3 py-2 text-xs font-medium text-brand-700 hover:bg-brand-200">
                انتخاب تصویر
                <input type="file" accept="image/*" class="img-file hidden">
            </label>
            <button type="button" class="img-clear shrink-0 text-xs text-red-400 hover:underline {{ $val ? '' : 'hidden' }}">حذف</button>
        </div>
        @break
    @default
        <input {!! $attr !!} type="text" name="{{ $name }}" value="{{ $val }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
@endswitch
