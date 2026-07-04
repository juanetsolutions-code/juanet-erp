@props([
    'name',
    'title' => null,
    'align' => 'right', // 'right' | 'left'
    'maxWidth' => 'md', // 'sm' | 'md' | 'lg' | 'xl'
])

@php
    $maxWidths = [
        'sm' => 'max-w-xs',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
    ];

    $maxWidthClass = $maxWidths[$maxWidth] ?? $maxWidths['md'];
    $slideDirection = $align === 'right' ? 'translate-x-full' : '-translate-x-full';
@endphp

<div 
    x-data="{ open: false }"
    @open-drawer-{{ $name }}.window="open = true"
    @close-drawer-{{ $name }}.window="open = false"
    @keydown.escape.window="open = false"
    x-show="open"
    class="fixed inset-0 z-50 overflow-hidden" 
    x-cloak
>
    <!-- Backdrop overlay -->
    <div 
        x-show="open" 
        x-transition:enter="ease-in-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in-out duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
        @click="open = false"
    ></div>

    <div class="fixed inset-y-0 {{ $align === 'right' ? 'right-0' : 'left-0' }} pl-10 max-w-full flex">
        <div 
            x-show="open" 
            x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400"
            x-transition:enter-start="{{ $slideDirection }}"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="{{ $slideDirection }}"
            class="w-screen {{ $maxWidthClass }}"
        >
            <div class="h-full flex flex-col bg-white shadow-2xl dark:bg-slate-900 border-l dark:border-slate-800">
                
                <!-- Drawer Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white font-display">
                        {{ $title ?? 'Drawer' }}
                    </h2>
                    <button type="button" @click="open = false" class="rounded-xl p-2 text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition focus:outline-none">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Drawer Content -->
                <div class="flex-grow overflow-y-auto px-6 py-6 text-xs text-slate-600 dark:text-slate-300">
                    {{ $slot }}
                </div>

                <!-- Drawer Footer (Optional slot) -->
                @if(isset($footer))
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 dark:bg-slate-900/50 dark:border-slate-800 flex items-center justify-end gap-x-3">
                        {{ $footer }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
