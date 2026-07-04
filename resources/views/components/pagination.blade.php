@props([
    'total',
    'perPage' => 10,
    'current' => 1,
])

@php
    $totalPages = max(1, ceil($total / $perPage));
    $startItem = (($current - 1) * $perPage) + 1;
    $endItem = min($total, $current * $perPage);
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center justify-between border-t border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800']) }}>
    <!-- Mobile view (simple buttons) -->
    <div class="flex flex-1 justify-between sm:hidden">
        <button 
            type="button" 
            {{ $current <= 1 ? 'disabled' : '' }}
            @click="alert('✓ Navigating to page {{ max(1, $current - 1) }}')"
            class="relative inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 cursor-pointer"
        >
            Previous
        </button>
        <button 
            type="button" 
            {{ $current >= $totalPages ? 'disabled' : '' }}
            @click="alert('✓ Navigating to page {{ min($totalPages, $current + 1) }}')"
            class="relative ml-3 inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 cursor-pointer"
        >
            Next
        </button>
    </div>

    <!-- Desktop view -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Showing
                <span class="font-semibold text-slate-900 dark:text-white">{{ $startItem }}</span>
                to
                <span class="font-semibold text-slate-900 dark:text-white">{{ $endItem }}</span>
                of
                <span class="font-semibold text-slate-900 dark:text-white">{{ $total }}</span>
                results
            </p>
        </div>
        <div>
            <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm" aria-label="Pagination">
                <!-- Prev Button -->
                <button 
                    type="button" 
                    {{ $current <= 1 ? 'disabled' : '' }}
                    @click="alert('✓ Navigating to previous page')"
                    class="relative inline-flex items-center rounded-l-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 focus:z-20 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer"
                >
                    <span class="sr-only">Previous</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <!-- Page numbers -->
                @for($p = 1; $p <= $totalPages; $p++)
                    @if($p === $current)
                        <button type="button" aria-current="page" class="relative z-10 inline-flex items-center bg-indigo-600 px-4 py-2 text-xs font-semibold text-white focus:z-20 dark:bg-indigo-500 cursor-pointer">
                            {{ $p }}
                        </button>
                    @else
                        <button type="button" @click="alert('✓ Navigating to page {{ $p }}')" class="relative inline-flex items-center border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 focus:z-20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer">
                            {{ $p }}
                        </button>
                    @endif
                @endfor

                <!-- Next Button -->
                <button 
                    type="button" 
                    {{ $current >= $totalPages ? 'disabled' : '' }}
                    @click="alert('✓ Navigating to next page')"
                    class="relative inline-flex items-center rounded-r-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 focus:z-20 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer"
                >
                    <span class="sr-only">Next</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </nav>
        </div>
    </div>
</div>
