@extends('layouts.app')

@section('header', 'Global Search Engine')

@section('content')
<div class="space-y-8" x-data="{
    searchQuery: '{{ e($query) }}',
    activeModule: '{{ e($activeModule) }}',
    modules: [
        { key: 'all', label: 'All Modules' },
        { key: 'crm', label: 'CRM Leads' },
        { key: 'marketplace', label: 'Marketplace' },
        { key: 'cms', label: 'CMS Articles' },
        { key: 'projects', label: 'Project Tasks' },
        { key: 'finance', label: 'Finance' },
        { key: 'support', label: 'Support Helpdesk' },
        { key: 'ai', label: 'AI Operations' },
        { key: 'notifications', label: 'Notifications' }
    ],
    triggerSearch() {
        if (!this.searchQuery.trim()) return;
        window.location.href = '/search?q=' + encodeURIComponent(this.searchQuery) + '&module=' + this.activeModule;
    },
    switchModule(mod) {
        this.activeModule = mod;
        window.location.href = '/search?q=' + encodeURIComponent(this.searchQuery) + '&module=' + mod;
    }
}">

    <!-- Search Hero Header Panel -->
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 rounded-3xl p-8 text-white relative overflow-hidden shadow-xl border border-indigo-950/40">
        <div class="relative z-10 max-w-2xl space-y-4">
            <span class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-500/10 px-2 py-1 text-xs font-medium text-indigo-400 ring-1 ring-inset ring-indigo-500/30">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                PostgreSQL Full-Text Engine
            </span>
            <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Enterprise Search Hub</h1>
            <p class="text-sm text-slate-300">Unified sub-second search cross-referenced dynamically against CRM, Marketplace, CMS Pages, Projects, Financial Records, Tickets, and System Alert Logs with active tenant-isolation.</p>
            
            <!-- Global Large Search Input Bar -->
            <div class="flex max-w-lg items-center gap-x-2 mt-4">
                <div class="relative flex-grow">
                    <input type="text" x-model="searchQuery" @keyup.enter="triggerSearch()" placeholder="Search contacts, documents, tasks, invoices..." 
                        class="w-full bg-white/10 text-white placeholder-slate-400 pl-11 pr-4 py-3 rounded-2xl border border-white/10 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 text-sm">
                    <svg class="h-5 w-5 text-slate-400 absolute left-3.5 top-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.537z" />
                    </svg>
                </div>
                <button type="button" @click="triggerSearch()" class="px-5 py-3 rounded-2xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-500 shadow transition-all duration-150">
                    Search
                </button>
            </div>
        </div>

        <!-- Ambient Backdrop Gradients -->
        <div class="absolute -right-10 -bottom-10 h-64 w-64 bg-indigo-600/20 rounded-full blur-3xl"></div>
    </div>

    <!-- Modules Filtering Menu Row -->
    <div class="border-b border-slate-200 dark:border-slate-800 pb-2">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <template x-for="mod in modules" :key="mod.key">
                <button type="button"
                    @click="switchModule(mod.key)"
                    class="px-3.5 py-2 text-xs font-semibold rounded-xl uppercase tracking-wider transition-all duration-150"
                    :class="activeModule === mod.key 
                        ? 'bg-indigo-600 text-white shadow-sm' 
                        : 'bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800'"
                    x-text="mod.label">
                </button>
            </template>
        </nav>
    </div>

    <!-- Search Results Listing Container -->
    <div class="space-y-6">
        @if(empty(trim($query)))
            <!-- Empty initial view state -->
            <div class="text-center py-24 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl p-12 space-y-3">
                <div class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-600">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75l-2.489-2.489m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.774 4.774zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">Begin Searching</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">Type keywords or project titles into the query bar above to probe the enterprise full-text indexing system.</p>
            </div>
        @else
            <!-- Result count summary banner -->
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Displaying {{ $results->count() }} active indices matching "<span class="font-semibold text-slate-700 dark:text-slate-300">{{ $query }}</span>"</span>
                @if($activeTenant)
                    <span>Tenant: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $activeTenant->name }}</span></span>
                @endif
            </div>

            <!-- List Grid -->
            <div class="grid grid-cols-1 gap-4">
                @forelse($results as $result)
                    <div class="group relative rounded-2xl border border-slate-200 dark:border-slate-800/80 bg-white dark:bg-slate-950 p-5 shadow-sm hover:shadow-md transition duration-150 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        
                        <!-- Main Content and Module Indicators -->
                        <div class="space-y-2 flex-grow">
                            <div class="flex items-center gap-x-2 flex-wrap gap-y-1.5">
                                
                                <!-- Dynamic Module Badge -->
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                    @if($result->module === 'crm') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400
                                    @elseif($result->module === 'marketplace') bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400
                                    @elseif($result->module === 'cms') bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-400
                                    @elseif($result->module === 'projects') bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-400
                                    @elseif($result->module === 'finance') bg-purple-100 text-purple-800 dark:bg-purple-950/40 dark:text-purple-400
                                    @elseif($result->module === 'support') bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-400
                                    @elseif($result->module === 'ai') bg-violet-100 text-violet-800 dark:bg-violet-950/40 dark:text-violet-400
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-400
                                    @endif">
                                    {{ $result->module }}
                                </span>

                                <!-- Score indicator -->
                                <span class="inline-flex items-center gap-x-1 text-[10px] font-medium text-slate-400">
                                    <svg class="h-3 w-3 text-indigo-500 fill-current" viewBox="0 0 16 16">
                                        <path d="M8 .25a.75.75 0 0 1 .673.418l1.882 3.815 4.21.612a.75.75 0 0 1 .416 1.279l-3.046 2.97.719 4.192a.75.75 0 0 1-1.088.791L8 12.347l-3.766 1.98a.75.75 0 0 1-1.088-.79l.72-4.194L.818 6.374a.75.75 0 0 1 .416-1.28l4.21-.611L7.327.668A.75.75 0 0 1 8 .25z" />
                                    </svg>
                                    Relevance Score: {{ number_format($result->score * 100, 0) }}%
                                </span>
                            </div>

                            <!-- Document Title -->
                            <h3 class="text-sm font-bold text-slate-950 dark:text-white leading-5">
                                @if($result->url)
                                    <a href="{{ $result->url }}" class="hover:text-indigo-600 hover:underline transition duration-150">
                                        {{ $result->title }}
                                    </a>
                                @else
                                    {{ $result->title }}
                                @endif
                            </h3>

                            <!-- Render Highlight Text Snippet safely with raw markup matches -->
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading- relaxed">
                                {!! $result->highlight !!}
                            </p>
                        </div>

                        <!-- Side Action Button to Deep Link -->
                        <div class="flex-shrink-0 md:pl-4 border-t md:border-t-0 border-slate-100 dark:border-slate-800 pt-3 md:pt-0">
                            @if($result->url)
                                <a href="{{ $result->url }}" class="inline-flex items-center gap-x-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 text-xs font-bold shadow dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100 transition">
                                    View File
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M15 12l-6.75 6.75" />
                                    </svg>
                                </a>
                            @endif
                        </div>

                    </div>
                @empty
                    <!-- No items found matching queries -->
                    <div class="text-center py-20 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 space-y-3">
                        <div class="mx-auto h-12 w-12 text-slate-400">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">No Matching Records</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">No enterprise records or notifications matching "<span class="font-medium text-slate-700 dark:text-slate-300">{{ $query }}</span>" were found inside this tenant's directory.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

</div>
@endsection
