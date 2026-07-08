{{-- One repeater row. Props: $sub (sub-fields), $i, $rep, $j, $row (values). --}}
<div class="rep-item rounded-lg bg-white p-3 ring-1 ring-brand-100">
    <div class="mb-2 flex items-center justify-between">
        <span class="text-xs text-brand-400">مورد</span>
        <button type="button" class="rep-del rounded px-2 text-xs text-red-400 hover:bg-red-50">حذف</button>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($sub as $field)
            <div class="{{ in_array($field['type'], ['textarea','richtext','lines','image'], true) ? 'sm:col-span-2' : '' }}">
                @include('admin.pages._field', [
                    'field' => $field,
                    'name' => "blocks[$i][data][$rep][$j][{$field['key']}]",
                    'rk' => $field['key'],
                    'val' => $row[$field['key']] ?? '',
                ])
            </div>
        @endforeach
    </div>
</div>
