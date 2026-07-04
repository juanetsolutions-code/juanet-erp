@props([
    'label',
    'name',
    'checked' => false,
    'disabled' => false,
])

<div {{ $attributes->merge(['class' => 'relative flex items-start']) }}>
    <div class="flex h-5 items-center">
        <input 
            id="{{ $name }}" 
            name="{{ $name }}" 
            type="checkbox" 
            {{ $checked ? 'checked' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            class="h-4.5 w-4.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:focus:ring-indigo-400/30 cursor-pointer"
        >
    </div>
    <div class="ml-3 text-xs leading-5">
        <label for="{{ $name }}" class="font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
            {{ $label }}
        </label>
        @if(isset($description))
            <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $description }}
            </p>
        @endif
    </div>
</div>
