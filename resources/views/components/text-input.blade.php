@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-2 border-gray-200 focus:border-[#005a9c] focus:ring-[#005a9c] rounded-xl shadow-sm transition-all duration-300 outline-none']) }}>
