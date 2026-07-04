@props([
    'placeholder' => 'Search projects, leads or customers...',
    'name' => 'query',
    'value' => null,
])

<form action="/search" method="GET" {{ $attributes->merge(['class' => 'relative w-full max-w-lg']) }}>
    <div class="relative rounded-xl shadow-sm">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
        </div>
        <input 
            type="search" 
            name="{{ $name }}" 
            placeholder="{{ $placeholder }}" 
            value="{{ $value }}"
            class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-xs text-slate-950 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/10 dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 transition"
        >
        <!-- Quick command label -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
            <kbd class="hidden sm:inline-flex items-center gap-0.5 rounded-lg border border-slate-200 bg-slate-50 px-2 font-mono text-[9px] font-semibold text-slate-400 dark:border-slate-800 dark:bg-slate-900/50">
                <span>⌘</span>K
            </kbd>
        </div>
    </div>
</form>
