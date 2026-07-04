@props([
    'quote',
    'author',
    'role' => null,
    'company' => null,
    'avatarSrc' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl bg-white border border-slate-200 p-6 md:p-8 shadow-sm dark:bg-slate-900 dark:border-slate-800 flex flex-col justify-between']) }}>
    <div>
        <!-- Double Quote Icon -->
        <svg class="h-8 w-8 text-indigo-500/20 mb-4" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true">
            <path d="M9.352 4C4.474 9.438 3 14.529 3 19c0 4.637 2.241 8 6 8a6 6 0 006-6c0-4.637-2.583-7-6-7-.522 0-1.025.109-1.5.258C8.974 11.05 11.01 7.21 14.352 4H9.352zm14 0c-4.878 5.438-6.352 10.529-6.352 15 0 4.637 2.241 8 6 8a6 6 0 006-6c0-4.637-2.583-7-6-7-.522 0-1.025.109-1.5.258C22.974 11.05 25.01 7.21 28.352 4h-5z" />
        </svg>
        
        <p class="text-sm md:text-base text-slate-700 dark:text-slate-300 italic leading-relaxed">
            "{{ $quote }}"
        </p>
    </div>

    <div class="mt-6 flex items-center gap-x-4">
        <x-avatar :src="$avatarSrc" :name="$author" size="sm" />
        <div>
            <h4 class="text-xs font-bold text-slate-900 dark:text-white">
                {{ $author }}
            </h4>
            @if($role || $company)
                <p class="text-[10px] text-slate-500 dark:text-slate-400">
                    {{ $role }}{{ $role && $company ? ', ' : '' }}{{ $company }}
                </p>
            @endif
        </div>
    </div>
</div>
