@props([
    'title',
    'value',
    'change' => null,
    'changeType' => 'increase', // increase, decrease, neutral
    'icon' => null,
    'iconBg' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400',
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl bg-white p-6 border border-slate-200 shadow-sm transition hover:shadow-md dark:bg-slate-900 dark:border-slate-800']) }}>
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider dark:text-slate-400">{{ $title }}</span>
        @if($icon)
            <div class="rounded-lg p-2 {{ $iconBg }}">
                {!! $icon !!}
            </div>
        @endif
    </div>
    
    <div class="mt-4 flex items-baseline justify-between">
        <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $value }}</span>
        
        @if($change !== null)
            <span class="inline-flex items-center text-xs font-semibold rounded-full px-2 py-0.5
                @if($changeType === 'increase')
                    bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300
                @elseif($changeType === 'decrease')
                    bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-300
                @else
                    bg-slate-50 text-slate-600 dark:bg-slate-950/20 dark:text-slate-400
                @endif
            ">
                @if($changeType === 'increase')
                    <svg class="mr-1 h-3 w-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                    </svg>
                @elseif($changeType === 'decrease')
                    <svg class="mr-1 h-3 w-3 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd"/>
                    </svg>
                @endif
                {{ $change }}
            </span>
        @endif
    </div>
</div>
