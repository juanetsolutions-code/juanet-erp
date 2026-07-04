<div 
    x-data="{ 
        open: false,
        search: '',
        commands: [
            { id: 'dash', label: 'Go to Console Dashboard', url: '/dashboard', icon: '💻' },
            { id: 'profile', label: 'Manage Profile Settings', url: '/profile', icon: '👤' },
            { id: 'crm', label: 'Manage CRM Leads', url: '/crm', icon: '📈' },
            { id: 'contacts', label: 'Manage Enterprise Contacts', url: '/crm/contacts', icon: '🪪' },
            { id: 'companies', label: 'Manage Companies & Accounts', url: '/crm/companies', icon: '🏢' },
            { id: 'settings', label: 'Platform Master Settings', url: '/settings', icon: '⚙️' }
        ],
        get filteredCommands() {
            if (!this.search) return this.commands;
            return this.commands.filter(cmd => cmd.label.toLowerCase().includes(this.search.toLowerCase()));
        }
    }"
    @keydown.window.cmd.k.prevent="open = true"
    @keydown.window.ctrl.k.prevent="open = true"
    @keydown.escape.window="open = false"
    x-show="open"
    class="relative z-50"
    role="dialog"
    aria-modal="true"
    x-cloak
>
    <!-- Background backdrop -->
    <div 
        x-show="open" 
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
    ></div>

    <div class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20">
        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="mx-auto max-w-xl transform divide-y divide-slate-100 rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 transition-all dark:divide-slate-800 dark:bg-slate-900 dark:ring-white/10"
            @click.away="open = false"
        >
            <div class="relative">
                <svg class="pointer-events-none absolute top-3.5 left-4 h-5 w-5 text-slate-400 dark:text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                </svg>
                <input 
                    type="text" 
                    x-model="search"
                    class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0 dark:text-white dark:placeholder-slate-500" 
                    placeholder="Type a command or page name..." 
                    role="combobox" 
                    aria-expanded="false" 
                    aria-controls="options"
                    x-ref="input"
                    @keydown.escape="open = false"
                >
            </div>

            <!-- Options list -->
            <ul x-show="filteredCommands.length > 0" class="max-h-72 scroll-py-2 overflow-y-auto py-2 text-xs text-slate-700 dark:text-slate-300" id="options" role="listbox">
                <template x-for="cmd in filteredCommands" :key="cmd.id">
                    <li class="group flex cursor-pointer select-none items-center rounded-xl px-3.5 py-2 hover:bg-indigo-600 hover:text-white dark:hover:bg-indigo-500" role="option" tabindex="-1" @click="window.location = cmd.url">
                        <span class="mr-3 text-sm" x-text="cmd.icon"></span>
                        <span class="flex-auto font-medium" x-text="cmd.label"></span>
                        <span class="hidden group-hover:inline ml-3 text-[10px] text-slate-300 dark:text-slate-200">Jump to &rarr;</span>
                    </li>
                </template>
            </ul>

            <!-- Empty state -->
            <div x-show="filteredCommands.length === 0" class="py-14 px-6 text-center sm:px-14">
                <svg class="mx-auto h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">No commands found matching that search.</p>
            </div>

            <!-- Help footer -->
            <div class="flex items-center justify-between bg-slate-50/50 px-4 py-2.5 text-[10px] text-slate-400 dark:bg-slate-900/50 dark:border-slate-800">
                <span>Use <kbd class="font-sans font-semibold">⌘K</kbd> to open anytime.</span>
                <span>Press <kbd class="font-sans font-semibold">ESC</kbd> to close.</span>
            </div>
        </div>
    </div>
</div>
