@props(['name' => 'province', 'value' => '', 'placeholder' => null, 'required' => false])
@php($placeholder ??= __('Province'))

@php $provinces = config('provinces'); @endphp

<select name="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100']) }}>
    <option value="" disabled {{ !$value ? 'selected' : '' }}>{{ $placeholder }}</option>
    @foreach ($provinces as $province)
        <option value="{{ $province }}" {{ $value === $province ? 'selected' : '' }}>{{ $province }}</option>
    @endforeach
</select>
