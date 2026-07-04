@props([
    'title',
    'subtitle' => null,
    'badge' => null,
    'align' => 'center', // 'center' | 'left'
])

<div {{ $attributes->merge(['class' => 'mb-12 md:mb-16 ' . ($align === 'center' ? 'text-center' : 'text-left')]) }}>
    @if($badge)
        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400 border border-indigo-200/20 mb-3 uppercase tracking-wider">
            {{ $badge }}
        </span>
    @endif
    
    <h2 class="text-3xl font-extrabold font-display tracking-tight text-slate-900 dark:text-white sm:text-4xl">
        {{ $title }}
    </h2>
    
    @if($subtitle)
        <p class="mt-4 text-base text-slate-500 dark:text-slate-400 max-w-2xl {{ $align === 'center' ? 'mx-auto' : '' }}">
            {{ $subtitle }}
        </p>
    @endif
</div>
