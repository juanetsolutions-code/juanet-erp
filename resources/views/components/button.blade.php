@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
])

@php
    $baseClass = 'inline-flex items-center justify-center font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none rounded-lg cursor-pointer';
    
    $variants = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-500 focus:ring-indigo-500 border border-transparent shadow-sm dark:bg-indigo-500 dark:hover:bg-indigo-400',
        'secondary' => 'bg-slate-100 text-slate-800 hover:bg-slate-200 focus:ring-slate-500 border border-transparent dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700',
        'outline' => 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-300 focus:ring-indigo-500 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-800',
        'danger' => 'bg-red-600 text-white hover:bg-red-500 focus:ring-red-500 border border-transparent shadow-sm dark:bg-red-500 dark:hover:bg-red-400',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-500 focus:ring-emerald-500 border border-transparent shadow-sm dark:bg-emerald-500 dark:hover:bg-emerald-400',
    ];

    $sizes = [
        'xs' => 'px-2 py-1 text-xs',
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
        'xl' => 'px-5 py-3 text-base',
    ];

    $classes = $baseClass . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="mr-1.5 -ml-0.5">{!! $icon !!}</span>
        @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)
            <span class="mr-1.5 -ml-0.5">{!! $icon !!}</span>
        @endif
        {{ $slot }}
    </button>
@endif
