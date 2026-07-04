@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
    'align' => 'center', // 'center' | 'left'
])

<div {{ $attributes->merge(['class' => 'relative py-20 md:py-28 overflow-hidden bg-slate-50 dark:bg-slate-950 transition-colors duration-300']) }}>
    <!-- Ambient glowing lights -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[300px] bg-gradient-to-b from-indigo-500/10 to-transparent dark:from-indigo-500/5 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-4xl {{ $align === 'center' ? 'mx-auto text-center' : 'text-left' }}">
            @if($badge)
                <span class="inline-flex items-center gap-x-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400 border border-indigo-200/30 mb-6 animate-fade-in">
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-600 dark:bg-indigo-400 animate-pulse"></span>
                    {{ $badge }}
                </span>
            @endif

            @if($title)
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black font-display tracking-tight text-slate-900 dark:text-white leading-tight">
                    {{ $title }}
                </h1>
            @else
                <div class="text-4xl sm:text-5xl md:text-6xl font-black font-display tracking-tight text-slate-900 dark:text-white leading-tight">
                    {{ $slot }}
                </div>
            @endif

            @if($subtitle)
                <p class="mt-6 text-lg sm:text-xl text-slate-500 dark:text-slate-400 font-light leading-relaxed max-w-2xl {{ $align === 'center' ? 'mx-auto' : '' }}">
                    {{ $subtitle }}
                </p>
            @endif

            @if(isset($actions))
                <div class="mt-10 flex flex-wrap gap-4 items-center {{ $align === 'center' ? 'justify-center' : 'justify-start' }}">
                    {{ $actions }}
                </div>
            @endif
        </div>
    </div>
</div>
