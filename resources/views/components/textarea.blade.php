@props([
    'label' => null,
    'name',
    'placeholder' => null,
    'value' => null,
    'rows' => 4,
    'required' => false,
    'disabled' => false,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if($label)
        <label for="{{ $name }}" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wide">
            {{ $label }}
            @if($required)
                <span class="text-rose-500">*</span>
            @endif
        </label>
    @endif
    
    <div class="relative rounded-xl shadow-sm">
        <textarea 
            id="{{ $name }}" 
            name="{{ $name }}" 
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}" 
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            class="block w-full rounded-xl border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-950 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/10 disabled:bg-slate-50 disabled:text-slate-400 border transition dark:border-slate-800 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/20 {{ $error ? 'border-rose-300 text-rose-900 placeholder-rose-300 focus:border-rose-500 focus:ring-rose-500/10' : '' }}"
        >{{ $value }}</textarea>
    </div>

    @if($error)
        <p class="mt-1.5 text-[10px] font-medium text-rose-600 dark:text-rose-400">
            {{ $error }}
        </p>
    @endif
</div>
