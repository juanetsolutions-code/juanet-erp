@props([
    'type' => 'info',
    'title' => null,
])

@php
    $types = [
        'info' => [
            'bg' => 'bg-blue-50 border-blue-200 dark:bg-blue-950/20 dark:border-blue-900',
            'text' => 'text-blue-800 dark:text-blue-200',
            'icon' => '<svg class="h-5 w-5 text-blue-400 dark:text-blue-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        ],
        'success' => [
            'bg' => 'bg-emerald-50 border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-900',
            'text' => 'text-emerald-800 dark:text-emerald-200',
            'icon' => '<svg class="h-5 w-5 text-emerald-400 dark:text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
        ],
        'warning' => [
            'bg' => 'bg-amber-50 border-amber-200 dark:bg-amber-950/20 dark:border-amber-900',
            'text' => 'text-amber-800 dark:text-amber-200',
            'icon' => '<svg class="h-5 w-5 text-amber-400 dark:text-amber-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        ],
        'danger' => [
            'bg' => 'bg-red-50 border-red-200 dark:bg-red-950/20 dark:border-red-900',
            'text' => 'text-red-800 dark:text-red-200',
            'icon' => '<svg class="h-5 w-5 text-red-400 dark:text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>',
        ],
    ];

    $cfg = $types[$type] ?? $types['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border p-4 ' . $cfg['bg'] . ' ' . $cfg['text']]) }}>
    <div class="flex">
        <div class="flex-shrink-0">
            {!! $cfg['icon'] !!}
        </div>
        <div class="ml-3">
            @if($title)
                <h3 class="text-sm font-semibold leading-5">{{ $title }}</h3>
            @endif
            <div class="text-xs leading-5 @if($title) mt-1 @endif">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
