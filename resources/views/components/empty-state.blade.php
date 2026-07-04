@props([
    'title',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-4 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10']) }}>
    @if($icon)
        <div class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500 mb-4 flex items-center justify-center">
            {!! $icon !!}
        </div>
    @else
        <div class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500 mb-4 flex items-center justify-center">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.008 1.24l.885 1.77a2.25 2.25 0 002.007 1.24h1.98a2.25 2.25 0 002.007-1.24l.885-1.77a2.25 2.25 0 012.007-1.24h3.86m-18 0h18" />
            </svg>
        </div>
    @endif
    
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    
    @if($description)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
            {{ $description }}
        </p>
    @endif

    @if($slot->isNotEmpty())
        <div class="mt-6 flex justify-center gap-x-3">
            {{ $slot }}
        </div>
    @endif
</div>
