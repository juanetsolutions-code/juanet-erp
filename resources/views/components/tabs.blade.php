@props([
    'tabs' => [],
    'active' => null,
])

@php
    $firstTab = count($tabs) > 0 ? (is_array($tabs[0]) ? $tabs[0]['id'] : $tabs[0]) : null;
    $defaultActive = $active ?? $firstTab;
@endphp

<div x-data="{ activeTab: '{{ $defaultActive }}' }" {{ $attributes->merge(['class' => 'w-full']) }}>
    <!-- Tab list -->
    <div class="border-b border-slate-200 dark:border-slate-800">
        <nav class="-mb-px flex space-x-6" aria-label="Tabs">
            @foreach($tabs as $tab)
                @php
                    $id = is_array($tab) ? $tab['id'] : $tab;
                    $label = is_array($tab) ? $tab['label'] : $tab;
                @endphp
                <button 
                    type="button"
                    @click="activeTab = '{{ $id }}'"
                    :class="activeTab === '{{ $id }}' 
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' 
                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                    class="whitespace-nowrap border-b-2 px-1 py-4 text-xs font-semibold focus:outline-none transition-all duration-150 cursor-pointer"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <!-- Tab content wrapper -->
    <div class="mt-6">
        {{ $slot }}
    </div>
</div>
