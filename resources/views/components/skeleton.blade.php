@props([
    'type' => 'line', // 'line' | 'circle' | 'card' | 'table'
    'rows' => 1,
])

<div {{ $attributes->merge(['class' => 'animate-pulse']) }}>
    @if($type === 'circle')
        <div class="h-12 w-12 rounded-full bg-slate-200 dark:bg-slate-800"></div>
    @elseif($type === 'card')
        <div class="rounded-xl border border-slate-200 p-6 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-4">
            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/3"></div>
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-full"></div>
            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-5/6"></div>
            <div class="h-8 bg-slate-200 dark:bg-slate-800 rounded-lg w-1/4 mt-4"></div>
        </div>
    @elseif($type === 'table')
        <div class="space-y-4">
            <div class="grid grid-cols-4 gap-4 pb-2 border-b border-slate-200 dark:border-slate-800">
                <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/2"></div>
                <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/2"></div>
                <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/2"></div>
                <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/2"></div>
            </div>
            @for($i = 0; $i < 3; $i++)
                <div class="grid grid-cols-4 gap-4 py-1">
                    <div class="h-3 bg-slate-100 dark:bg-slate-900 rounded w-3/4"></div>
                    <div class="h-3 bg-slate-100 dark:bg-slate-900 rounded w-2/3"></div>
                    <div class="h-3 bg-slate-100 dark:bg-slate-900 rounded w-1/2"></div>
                    <div class="h-3 bg-slate-100 dark:bg-slate-900 rounded w-5/6"></div>
                </div>
            @endfor
        </div>
    @else
        <div class="space-y-3">
            @for($i = 0; $i < $rows; $i++)
                <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded-full {{ $i === $rows - 1 && $rows > 1 ? 'w-4/5' : 'w-full' }}"></div>
            @endfor
        </div>
    @endif
</div>
