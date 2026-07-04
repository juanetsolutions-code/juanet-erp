@props([
    'title',
    'description' => null,
    'icon' => null,
    'href' => null,
])

<div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-2xl bg-white border border-slate-200 p-6 md:p-8 shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800']) }}>
    <!-- Soft hover gradient backdrop -->
    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

    <div class="relative z-10 flex flex-col h-full justify-between">
        <div>
            @if($icon)
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 mb-6 group-hover:scale-110 transition duration-300">
                    {!! $icon !!}
                </div>
            @endif

            <h3 class="text-lg font-bold font-display text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                {{ $title }}
            </h3>

            @if($description || $slot->isNotEmpty())
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ $description ?? $slot }}
                </p>
            @endif
        </div>

        @if($href)
            <div class="mt-6 pt-4 border-t border-slate-50 dark:border-slate-800/50">
                <a href="{{ $href }}" class="inline-flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Learn more
                    <svg class="ml-1.5 h-3 w-3 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        @endif
    </div>
</div>
