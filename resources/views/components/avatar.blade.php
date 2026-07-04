@props([
    'src' => null,
    'name',
    'size' => 'md', // 'xs' | 'sm' | 'md' | 'lg' | 'xl'
    'badge' => null, // 'online' | 'offline' | 'busy' | 'away'
])

@php
    $sizes = [
        'xs' => 'h-6 w-6 text-[10px]',
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-12 w-12 text-base',
        'xl' => 'h-16 w-16 text-lg',
    ];

    $badgeSizes = [
        'xs' => 'h-1.5 w-1.5',
        'sm' => 'h-2 w-2',
        'md' => 'h-2.5 w-2.5',
        'lg' => 'h-3 w-3',
        'xl' => 'h-4 w-4',
    ];

    $badgeColors = [
        'online' => 'bg-emerald-500',
        'offline' => 'bg-slate-400',
        'busy' => 'bg-red-500',
        'away' => 'bg-amber-500',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $badgeSizeClass = $badgeSizes[$size] ?? $badgeSizes['md'];
    $badgeColorClass = $badgeColors[$badge] ?? 'bg-slate-400';

    // Extract initials
    $initials = '';
    $words = explode(' ', $name);
    foreach ($words as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    $initials = substr($initials, 0, 2);
@endphp

<div {{ $attributes->merge(['class' => 'relative inline-block flex-shrink-0']) }}>
    @if($src)
        <img class="{{ $sizeClass }} rounded-full object-cover ring-2 ring-white dark:ring-slate-900 shadow-sm" src="{{ $src }}" alt="{{ $name }}" referrerPolicy="no-referrer">
    @else
        <div class="{{ $sizeClass }} rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-extrabold flex items-center justify-center ring-2 ring-white dark:ring-slate-900 shadow-sm">
            {{ $initials }}
        </div>
    @endif

    @if($badge)
        <span class="absolute bottom-0 right-0 rounded-full ring-2 ring-white dark:ring-slate-900 {{ $badgeSizeClass }} {{ $badgeColorClass }}" aria-hidden="true"></span>
    @endif
</div>
