@props([
    'title' => null,
    'subtitle' => null,
    'footer' => null,
    'noPadding' => false,
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl bg-white border border-slate-200 shadow-sm transition hover:shadow-md dark:bg-slate-900 dark:border-slate-800']) }}>
    @if($title || $subtitle || isset($header))
        <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            @if(isset($header))
                {{ $header }}
            @else
                @if($title)
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                @endif
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? '' : 'px-6 py-5' }}">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/50">
            {{ $footer }}
        </div>
    @endif
</div>
