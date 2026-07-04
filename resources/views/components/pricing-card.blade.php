@props([
    'name',
    'price',
    'period' => '/mo',
    'description' => null,
    'features' => [],
    'ctaText' => 'Get started',
    'ctaHref' => '#',
    'popular' => false,
])

<div {{ $attributes->merge(['class' => 'relative rounded-2xl bg-white border p-8 shadow-sm flex flex-col justify-between transition hover:shadow-md dark:bg-slate-900 ' . ($popular ? 'border-indigo-600 ring-2 ring-indigo-600/10 dark:border-indigo-500' : 'border-slate-200 dark:border-slate-800')]) }}>
    
    @if($popular)
        <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3.5 py-1 text-xs font-semibold text-white uppercase tracking-wider dark:bg-indigo-500">
            Most Popular
        </span>
    @endif

    <div>
        <h3 class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">
            {{ $name }}
        </h3>
        
        @if($description)
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                {{ $description }}
            </p>
        @endif

        <div class="mt-6 flex items-baseline">
            <span class="text-4xl font-extrabold font-display text-slate-900 dark:text-white">
                {{ $price }}
            </span>
            @if($period)
                <span class="ml-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $period }}
                </span>
            @endif
        </div>

        <!-- Divider -->
        <div class="my-6 border-t border-slate-100 dark:border-slate-800/80"></div>

        <ul class="space-y-3.5 text-xs text-slate-600 dark:text-slate-300">
            @foreach($features as $feature)
                <li class="flex items-start">
                    <svg class="h-4.5 w-4.5 text-emerald-500 flex-shrink-0 mr-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>{{ $feature }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-8">
        <a href="{{ $ctaHref }}" class="block w-full text-center rounded-xl px-4 py-3 text-xs font-bold transition duration-200 {{ $popular ? 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-sm' : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700' }}">
            {{ $ctaText }}
        </a>
    </div>
</div>
