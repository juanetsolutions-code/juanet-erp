@props([
    'headers' => [],
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 shadow-sm']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
            <thead class="bg-slate-50/50 dark:bg-slate-900/50">
                <tr>
                    @foreach($headers as $header)
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
