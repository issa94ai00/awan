<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-l from-[#0077be] to-[#005a9c] border border-transparent rounded-xl font-semibold text-sm text-white hover:from-[#005a9c] hover:to-[#0077be] focus:outline-none focus:ring-2 focus:ring-[#005a9c] focus:ring-offset-2 transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5 font-[Cairo]']) }}>
    {{ $slot }}
</button>
