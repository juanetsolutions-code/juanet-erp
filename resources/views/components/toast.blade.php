@props([
    'message' => null,
    'type' => 'success',
])

<div 
    x-data="{ 
        show: false, 
        message: '{{ $message }}', 
        type: '{{ $type }}',
        trigger(msg, t = 'success') {
            this.message = msg;
            this.type = t;
            this.show = true;
            setTimeout(() => this.show = false, 4000);
        }
    }"
    @trigger-toast.window="trigger($event.detail.message, $event.detail.type)"
    x-init="if(message) { show = true; setTimeout(() => show = false, 4000); }"
    x-show="show"
    x-transition:enter="transform ease-out duration-300 transition"
    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed bottom-6 right-6 z-50 max-w-sm w-full bg-white shadow-xl rounded-xl border p-4 dark:bg-slate-900 pointer-events-auto flex items-start gap-x-3"
    :class="type === 'error' ? 'border-rose-200 dark:border-rose-900/50' : (type === 'info' ? 'border-blue-200 dark:border-blue-900/50' : 'border-emerald-200 dark:border-emerald-900/50')"
    x-cloak
>
    <!-- Status Icon -->
    <div class="flex-shrink-0">
        <!-- Success Icon -->
        <svg x-show="type === 'success'" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <!-- Error Icon -->
        <svg x-show="type === 'error'" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" x-cloak>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
        <!-- Info Icon -->
        <svg x-show="type === 'info'" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" x-cloak>
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.083.984l-.04.022m-.083-.984l-.04.022m.083.984a.75.75 0 11-1.083-.984l.04-.022m0 0H12m0 0v-1.5m0 9a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </div>

    <!-- Message Content -->
    <div class="flex-1">
        <p class="text-xs font-semibold text-slate-900 dark:text-white" x-text="type === 'success' ? 'Success' : (type === 'error' ? 'Error' : 'Notification')"></p>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400" x-text="message"></p>
    </div>

    <!-- Close button -->
    <button type="button" @click="show = false" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 p-1 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
