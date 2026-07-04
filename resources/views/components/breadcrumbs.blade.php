@props([
    'items' => [],
])

<nav {{ $attributes->merge(['class' => 'flex']) }} aria-label="Breadcrumb">
    <ol role="list" class="flex items-center space-x-2">
        <li>
            <div>
                <a href="/" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 transition">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span class="sr-only">Home</span>
                </a>
            </div>
        </li>
        @foreach($items as $item)
            <li>
                <div class="flex items-center">
                    <svg class="h-5 w-5 text-slate-300 flex-shrink-0 dark:text-slate-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                    @if(isset($item['url']) && !$loop->last)
                        <a href="{{ $item['url'] }}" class="ml-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300 transition">{{ $item['label'] }}</a>
                    @else
                        <span class="ml-2 text-xs font-semibold text-slate-800 dark:text-white" aria-current="page">{{ $item['label'] }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</nav>
