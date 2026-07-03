@props([
    'variant' => 'slate',
])

@php
    $variants = [
        'slate' => 'bg-slate-50 text-slate-700 ring-slate-600/10 dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-500/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/10 dark:bg-indigo-900/50 dark:text-indigo-300 dark:ring-indigo-500/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-900/50 dark:text-emerald-300 dark:ring-emerald-500/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-900/50 dark:text-amber-300 dark:ring-amber-500/20',
        'red' => 'bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-900/50 dark:text-red-300 dark:ring-red-500/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/10 dark:bg-sky-900/50 dark:text-sky-300 dark:ring-sky-500/20',
    ];

    $classes = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' . ($variants[$variant] ?? $variants['slate']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
