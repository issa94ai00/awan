@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-[#1a2634]']) }}>
    {{ $value ?? $slot }}
</label>
