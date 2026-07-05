@extends('layouts.public')

@section('title', $category->name . ' — JUANET Marketplace')
@section('meta_description', 'Browse and download premium digital products in ' . $category->name . '. Accelerate your product development with JUANET.')

@section('content')
<div class="relative min-h-screen py-16 bg-slate-50 dark:bg-slate-950 transition-colors duration-300"
     x-data="{
        // Base collections
        allProducts: {{ json_encode($initial_products) }},
        categories: {{ json_encode($categories) }},
        currentCategory: {{ json_encode($category) }},
        
        // Paginated subset
        visibleProducts: [],
        
        // Filter states
        searchQuery: '',
        selectedFramework: '',
        selectedLicense: '',
        selectedTag: '',
        selectedTechnology: '',
        minPrice: '',
        maxPrice: '',
        ratingFilter: '',
        filterFree: false,
        filterPremium: false,
        filterOnSale: false,
        filterNewest: false,
        filterPopular: false,
        filterFeatured: false,
        sorting: 'newest',
        
        // Layout & Pagination states
        viewMode: 'grid',
        perPage: 6,
        currentPage: 1,
        infiniteScroll: false,
        hasMore: true,
        loading: false,
        skeletonCount: 3,
        
        // Autocomplete search states
        searchFocused: false,
        recentSearches: [],
        popularSearches: ['SaaS', 'CRM', 'Starter Kit', 'Dashboard', 'M-PESA', 'API', 'Figma'],
        
        // Sidebar states
        expandedCategories: {},
        
        // Modal & Trackers
        activePreviewItem: null,
        activeDetailsItem: null,
        recommendations: [],
        recentlyViewed: [],

        init() {
            // Setup expanded categories sidebar states
            this.categories.forEach(cat => {
                if (!cat.parent_slug) {
                    this.expandedCategories[cat.slug] = (cat.slug === this.currentCategory.slug || cat.slug === this.currentCategory.parent_slug);
                }
            });

            // Load recently viewed & recent searches from localStorage
            this.loadRecentlyViewed();
            this.loadRecentSearches();

            // Watch filters to trigger recalculation & track combos
            this.$watch('searchQuery', () => { this.currentPage = 1; this.debounceRecalculate(); });
            this.$watch('selectedFramework', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('selectedLicense', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('selectedTag', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('selectedTechnology', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('minPrice', () => { this.currentPage = 1; this.debounceRecalculate(); });
            this.$watch('maxPrice', () => { this.currentPage = 1; this.debounceRecalculate(); });
            this.$watch('ratingFilter', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('filterFree', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('filterPremium', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('filterOnSale', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('filterNewest', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('filterPopular', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('filterFeatured', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('sorting', () => { this.recalculateProducts(); this.trackSortingSelection(); });
            this.$watch('perPage', () => { this.currentPage = 1; this.recalculateProducts(); });
            this.$watch('infiniteScroll', () => { this.currentPage = 1; this.recalculateProducts(); });

            // Run initial load
            this.recalculateProducts();
        },

        debounceTimer: null,
        debounceRecalculate() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.recalculateProducts();
            }, 300);
        },

        // Core business filtering & paging logic (Cursor pagination & Infinite Scroll ready)
        recalculateProducts() {
            this.loading = true;
            setTimeout(() => {
                let filtered = [...this.allProducts];

                // 1. Text Search
                if (this.searchQuery) {
                    let q = this.searchQuery.toLowerCase();
                    filtered = filtered.filter(p => {
                        let matchesTech = (p.technology || []).some(t => t.toLowerCase().includes(q));
                        let matchesTags = (p.tags || []).some(t => t.toLowerCase().includes(q));
                        return (p.title || '').toLowerCase().includes(q) || 
                               (p.short_description || '').toLowerCase().includes(q) || 
                               matchesTech || 
                               matchesTags;
                    });
                }

                // 2. Framework
                if (this.selectedFramework) {
                    let fw = this.selectedFramework.toLowerCase();
                    filtered = filtered.filter(p => (p.framework || '').toLowerCase() === fw);
                }

                // 3. License
                if (this.selectedLicense) {
                    let lic = this.selectedLicense.toLowerCase();
                    filtered = filtered.filter(p => (p.license || '').toLowerCase().includes(lic));
                }

                // 4. Tag
                if (this.selectedTag) {
                    let tag = this.selectedTag.toLowerCase();
                    filtered = filtered.filter(p => (p.tags || []).some(t => t.toLowerCase() === tag));
                }

                // 5. Technology
                if (this.selectedTechnology) {
                    let tech = this.selectedTechnology.toLowerCase();
                    filtered = filtered.filter(p => (p.technology || []).some(t => t.toLowerCase() === tech));
                }

                // 6. Prices
                if (this.minPrice !== '') {
                    filtered = filtered.filter(p => (p.price || 0) >= parseInt(this.minPrice));
                }
                if (this.maxPrice !== '') {
                    filtered = filtered.filter(p => (p.price || 0) <= parseInt(this.maxPrice));
                }

                // 7. Rating
                if (this.ratingFilter !== '') {
                    filtered = filtered.filter(p => (p.rating || 0) >= parseFloat(this.ratingFilter));
                }

                // 8. Attributes checkboxes
                if (this.filterFree) filtered = filtered.filter(p => (p.price || 0) === 0);
                if (this.filterPremium) filtered = filtered.filter(p => (p.price || 0) > 0);
                if (this.filterOnSale) filtered = filtered.filter(p => p.previous_price && p.previous_price > p->price);
                if (this.filterNewest) filtered = filtered.filter(p => p.is_new);
                if (this.filterPopular) filtered = filtered.filter(p => p.is_best_seller);
                if (this.filterFeatured) filtered = filtered.filter(p => p.is_featured);

                // 9. Sorting
                switch (this.sorting) {
                    case 'newest':
                        filtered.sort((a,b) => b.is_new - a.is_new);
                        break;
                    case 'oldest':
                        filtered.sort((a,b) => (a.id > b.id ? 1 : -1));
                        break;
                    case 'highest_rated':
                        filtered.sort((a,b) => (b.rating || 0) - (a.rating || 0));
                        break;
                    case 'lowest_price':
                        filtered.sort((a,b) => (a.price || 0) - (b.price || 0));
                        break;
                    case 'highest_price':
                        filtered.sort((a,b) => (b.price || 0) - (a.price || 0));
                        break;
                    case 'best_selling':
                        filtered.sort((a,b) => (b.downloads || 0) - (a.downloads || 0));
                        break;
                    case 'most_popular':
                        filtered.sort((a,b) => (b.views || 0) - (a.views || 0));
                        break;
                    case 'alphabetical':
                        filtered.sort((a,b) => (a.title || '').localeCompare(b.title || ''));
                        break;
                }

                // 10. Paginate
                let totalFiltered = filtered.length;
                if (this.infiniteScroll) {
                    let limit = this.currentPage * this.perPage;
                    this.visibleProducts = filtered.slice(0, limit);
                    this.hasMore = limit < totalFiltered;
                } else {
                    let start = (this.currentPage - 1) * this.perPage;
                    this.visibleProducts = filtered.slice(start, start + this.perPage);
                    this.hasMore = (start + this.perPage) < totalFiltered;
                }

                this.loading = false;
            }, 300);
        },

        loadMore() {
            if (!this.hasMore || this.loading) return;
            this.loading = true;
            // Track infinite scroll loaded trigger
            this.trackInfiniteScrollLoad();
            setTimeout(() => {
                this.currentPage++;
                this.recalculateProducts();
            }, 300);
        },

        clearFilters() {
            this.searchQuery = '';
            this.selectedFramework = '';
            this.selectedLicense = '';
            this.selectedTag = '';
            this.selectedTechnology = '';
            this.minPrice = '';
            this.maxPrice = '';
            this.ratingFilter = '';
            this.filterFree = false;
            this.filterPremium = false;
            this.filterOnSale = false;
            this.filterNewest = false;
            this.filterPopular = false;
            this.filterFeatured = false;
            this.sorting = 'newest';
            this.currentPage = 1;
            this.recalculateProducts();
            this.$dispatch('trigger-toast', { message: 'All filters reset', type: 'info' });
        },

        // Collection Filter presets
        applyCollectionPreset(collectionName) {
            this.clearFilters();
            switch (collectionName) {
                case 'agencies':
                    this.selectedLicense = 'Extended License';
                    this.filterFeatured = true;
                    break;
                case 'ai':
                    this.searchQuery = 'Gemini';
                    break;
                case 'laravel':
                    this.selectedFramework = 'Laravel';
                    this.filterFeatured = true;
                    break;
                case 'new':
                    this.filterNewest = true;
                    break;
                case 'essentials':
                    this.filterPopular = true;
                    break;
                case 'free':
                    this.filterFree = true;
                    break;
            }
            this.recalculateProducts();
            this.$dispatch('trigger-toast', { message: 'Applied collection: ' + collectionName.toUpperCase().replace('_', ' '), type: 'success' });
        },

        // Autocomplete mechanics
        triggerSearchSuggestion(term) {
            this.searchQuery = term;
            this.addRecentSearch(term);
            this.searchFocused = false;
            this.recalculateProducts();
        },

        addRecentSearch(term) {
            if (!term) return;
            let current = [...this.recentSearches].filter(x => x !== term);
            current.unshift(term);
            this.recentSearches = current.slice(0, 5);
            localStorage.setItem('juanet_recent_searches', JSON.stringify(this.recentSearches));
        },

        loadRecentSearches() {
            let val = localStorage.getItem('juanet_recent_searches');
            if (val) {
                this.recentSearches = JSON.parse(val);
            }
        },

        clearRecentSearches() {
            this.recentSearches = [];
            localStorage.removeItem('juanet_recent_searches');
        },

        // Recently Viewed mechanics (Persist & display automatically)
        loadRecentlyViewed() {
            let val = localStorage.getItem('juanet_recently_viewed');
            if (val) {
                this.recentlyViewed = JSON.parse(val);
            }
        },

        trackProductView(product) {
            let current = [...this.recentlyViewed].filter(p => p.id !== product.id);
            current.unshift(product);
            this.recentlyViewed = current.slice(0, 4); // Limit to last 4 items
            localStorage.setItem('juanet_recently_viewed', JSON.stringify(this.recentlyViewed));
        },

        openQuickPreview(product) {
            this.activePreviewItem = product;
            this.trackProductView(product);

            // Fetch recommendations
            let categorySlug = product.category_slug || 'laravel';
            this.recommendations = this.allProducts.filter(p => p.category_slug === categorySlug && p.id !== product.id).slice(0, 3);
            
            // Track Impression domain event via background endpoint
            fetch('/api/marketplace/search', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
        },

        openDetails(product) {
            this.trackProductView(product);
            window.location.href = `/marketplace/product/${product.slug}`;
        },

        // Visitor Intelligence dispatchers
        trackSortingSelection() {
            // Simulated tracking dispatch for outbox event
            console.log('Tracking outbox: marketplace.sort.changed', this.sorting);
        },

        trackInfiniteScrollLoad() {
            console.log('Tracking outbox: marketplace.infinitescroll.loaded', this.currentPage + 1);
        },

        toggleSidebarCategory(slug) {
            this.expandedCategories[slug] = !this.expandedCategories[slug];
        }
     }">
    
    <!-- Ambient ambient mesh backdrops -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute top-[-5%] right-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[-5%] left-[-10%] w-[50%] h-[50%] bg-emerald-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Breadcrumbs Navigation Component -->
        <div class="mb-6">
            <x-breadcrumbs :items="[
                ['label' => 'Marketplace', 'url' => route('marketplace')],
                ['label' => $category->name]
            ]" />
        </div>

        <!-- Custom Category Landing Hero Banner -->
        <div class="bg-gradient-to-r {{ $category->cover_image ?? 'from-indigo-600/10 via-slate-500/5 to-transparent' }} border border-slate-200/50 dark:border-slate-800 p-8 sm:p-12 rounded-3xl shadow-sm mb-12 flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden">
            <div class="max-w-2xl space-y-4 relative z-10">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-950 px-3 py-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                        {{ $category->name }}
                    </span>
                    <span class="text-xs font-mono text-slate-400 font-bold" x-text="allProducts.length + ' Available Items'"></span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight font-display">{{ $category->name }} Hub</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ $category->description ?? 'Discover production-ready digital tools, elite boilerplates, and developer-centric configurations selected and approved by JUANET.' }}
                </p>
                <div class="flex flex-wrap gap-2 pt-2 text-xs font-mono">
                    <span class="bg-white/60 dark:bg-slate-900/60 border border-slate-200/40 dark:border-slate-800 px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300">✓ Developer-Verified</span>
                    <span class="bg-white/60 dark:bg-slate-900/60 border border-slate-200/40 dark:border-slate-800 px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300">✓ Instant Downloads</span>
                    <span class="bg-white/60 dark:bg-slate-900/60 border border-slate-200/40 dark:border-slate-800 px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300">✓ Safaricom Daraja M-PESA</span>
                </div>
            </div>
            
            <div class="relative w-40 h-40 shrink-0 hidden md:flex items-center justify-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/40 dark:border-slate-800/80 shadow-md">
                <span class="text-7xl select-none">
                    @if($category->slug === 'laravel') 🔴 
                    @elseif($category->slug === 'react') ⚛️ 
                    @elseif($category->slug === 'nextjs') 🌐 
                    @elseif($category->slug === 'templates') 💻 
                    @elseif($category->slug === 'ai-prompts') ✨ 
                    @else 📦 @endif
                </span>
            </div>
        </div>

        <!-- Featured Curated Collections (Bento Style Buttons) -->
        <div class="mb-12">
            <h3 class="text-xs font-bold font-mono text-slate-400 uppercase tracking-wider mb-4">Curated Collections</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <button @click="applyCollectionPreset('agencies')" class="p-3 bg-white dark:bg-slate-900 hover:border-indigo-500/50 border border-slate-200/40 dark:border-slate-800/80 rounded-xl text-left transition hover:-translate-y-0.5 shadow-sm group cursor-pointer">
                    <span class="text-lg block">🏢</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-2 group-hover:text-indigo-600 transition">Best for Agencies</h4>
                </button>
                <button @click="applyCollectionPreset('ai')" class="p-3 bg-white dark:bg-slate-900 hover:border-indigo-500/50 border border-slate-200/40 dark:border-slate-800/80 rounded-xl text-left transition hover:-translate-y-0.5 shadow-sm group cursor-pointer">
                    <span class="text-lg block">🤖</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-2 group-hover:text-indigo-600 transition">AI Ready</h4>
                </button>
                <button @click="applyCollectionPreset('laravel')" class="p-3 bg-white dark:bg-slate-900 hover:border-indigo-500/50 border border-slate-200/40 dark:border-slate-800/80 rounded-xl text-left transition hover:-translate-y-0.5 shadow-sm group cursor-pointer">
                    <span class="text-lg block">⚡</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-2 group-hover:text-indigo-600 transition">Laravel Enterprise</h4>
                </button>
                <button @click="applyCollectionPreset('new')" class="p-3 bg-white dark:bg-slate-900 hover:border-indigo-500/50 border border-slate-200/40 dark:border-slate-800/80 rounded-xl text-left transition hover:-translate-y-0.5 shadow-sm group cursor-pointer">
                    <span class="text-lg block">✨</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-2 group-hover:text-indigo-600 transition">New Releases</h4>
                </button>
                <button @click="applyCollectionPreset('essentials')" class="p-3 bg-white dark:bg-slate-900 hover:border-indigo-500/50 border border-slate-200/40 dark:border-slate-800/80 rounded-xl text-left transition hover:-translate-y-0.5 shadow-sm group cursor-pointer">
                    <span class="text-lg block">🛠️</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-2 group-hover:text-indigo-600 transition">Developer Essentials</h4>
                </button>
                <button @click="applyCollectionPreset('free')" class="p-3 bg-white dark:bg-slate-900 hover:border-indigo-500/50 border border-slate-200/40 dark:border-slate-800/80 rounded-xl text-left transition hover:-translate-y-0.5 shadow-sm group cursor-pointer">
                    <span class="text-lg block">🎁</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-2 group-hover:text-indigo-600 transition">Free Resources</h4>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Upgraded Interactive Categories Sidebar Panel -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Categories Tree Sidebar -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 rounded-2xl shadow-sm">
                    <h3 class="text-sm font-bold text-slate-950 dark:text-white uppercase tracking-wider font-mono border-b border-slate-100 dark:border-slate-800 pb-4 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                        Discover Categories
                    </h3>
                    
                    <!-- Expandable Category Tree Node -->
                    <div class="space-y-2">
                        <template x-for="cat in categories.filter(c => !c.parent_slug)">
                            <div class="space-y-1">
                                <div class="flex items-center justify-between group">
                                    <a :href="'/marketplace/category/' + cat.slug" 
                                       class="flex-grow flex items-center gap-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 py-1.5 px-2 rounded-lg transition"
                                       :class="currentCategory.slug === cat.slug ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-extrabold' : ''">
                                        <span class="text-base select-none" x-text="cat.slug === 'laravel' ? '🔴' : (cat.slug === 'templates' ? '💻' : (cat.slug === 'react' ? '⚛️' : (cat.slug === 'nextjs' ? '🌐' : '📦')))"></span>
                                        <span x-text="cat.name"></span>
                                        <span class="text-[9px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded" x-text="cat.product_count"></span>
                                    </a>
                                    
                                    <!-- Child expand indicator if children exist -->
                                    <template x-if="categories.some(child => child.parent_slug === cat.slug)">
                                        <button @click="toggleSidebarCategory(cat.slug)" class="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition cursor-pointer text-slate-400 hover:text-slate-600">
                                            <svg class="w-4 h-4 transform transition-transform" :class="expandedCategories[cat.slug] ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                        </button>
                                    </template>
                                </div>

                                <!-- Nested Tree Leaf Children -->
                                <div x-show="expandedCategories[cat.slug]" class="pl-6 space-y-1" x-cloak>
                                    <template x-for="child in categories.filter(c => c.parent_slug === cat.slug)">
                                        <a :href="'/marketplace/category/' + child.slug" 
                                           class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 py-1 px-2 rounded-md transition"
                                           :class="currentCategory.slug === child.slug ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-extrabold' : ''">
                                            <span>↳</span>
                                            <span x-text="child.name"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Complete Dynamic Filters Sidebar -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 rounded-2xl shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                        <h3 class="text-sm font-bold text-slate-950 dark:text-white uppercase tracking-wider font-mono">Filters</h3>
                        <button @click="clearFilters()" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-mono font-bold cursor-pointer">
                            Reset All
                        </button>
                    </div>

                    <!-- Autocomplete Keyword Input Section -->
                    <div class="space-y-2 relative">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Keyword Search</label>
                        <div class="relative">
                            <input type="text" x-model="searchQuery" 
                                   @focus="searchFocused = true"
                                   @click.away="setTimeout(() => searchFocused = false, 200)"
                                   placeholder="Boilerplate, STK, SaaS..." 
                                   class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                            <span class="absolute right-3.5 top-3.5 text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </span>
                        </div>

                        <!-- Autocomplete Popover Dropdown -->
                        <div x-show="searchFocused" 
                             class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-lg z-30 p-4 space-y-4 text-left" 
                             x-transition x-cloak>
                            
                            <!-- Recent Searches -->
                            <template x-if="recentSearches.length > 0">
                                <div class="space-y-1.5">
                                    <div class="flex justify-between items-center">
                                        <span class="text-[9px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Recent Searches</span>
                                        <button @click="clearRecentSearches()" class="text-[9px] font-mono text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 underline">Clear</button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="term in recentSearches">
                                            <button @click="triggerSearchSuggestion(term)" class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-1 rounded text-[10px] hover:bg-indigo-500 hover:text-white transition cursor-pointer" x-text="term"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Popular Searches -->
                            <div class="space-y-1.5">
                                <span class="text-[9px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Popular Searches</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="term in popularSearches">
                                        <button @click="triggerSearchSuggestion(term)" class="bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-1 rounded text-[10px] hover:bg-indigo-500 hover:text-white border border-slate-100 dark:border-slate-800/80 transition cursor-pointer" x-text="term"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Framework Dropdown -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Framework Core</label>
                        <select x-model="selectedFramework" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Any Framework</option>
                            <option value="Laravel">Laravel</option>
                            <option value="React">React</option>
                            <option value="Next.js">Next.js</option>
                            <option value="Vue">Vue</option>
                        </select>
                    </div>

                    <!-- License Selection Filter -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Licensing Type</label>
                        <select x-model="selectedLicense" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Any License</option>
                            <option value="Regular License">Regular License</option>
                            <option value="Extended License">Extended License</option>
                        </select>
                    </div>

                    <!-- Tech selector -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Technology Stack</label>
                        <select x-model="selectedTechnology" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">All Tech Stacks</option>
                            <option value="Alpine.js">Alpine.js</option>
                            <option value="Tailwind CSS">Tailwind CSS</option>
                            <option value="PostgreSQL">PostgreSQL</option>
                            <option value="Redis">Redis</option>
                            <option value="D3.js">D3.js</option>
                            <option value="Vue">Vue</option>
                            <option value="Figma">Figma</option>
                        </select>
                    </div>

                    <!-- Pricing limits -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Price Limits (KES)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" x-model="minPrice" placeholder="Min" 
                                   class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                            <input type="number" x-model="maxPrice" placeholder="Max" 
                                   class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        </div>
                    </div>

                    <!-- Minimum rating filter -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Minimum Rating</label>
                        <select x-model="ratingFilter" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">Any Rating</option>
                            <option value="4.5">★ 4.5 &amp; Above</option>
                            <option value="4.8">★ 4.8 &amp; Above</option>
                            <option value="5.0">★ 5.0 (Flawless)</option>
                        </select>
                    </div>

                    <!-- Additional flags -->
                    <div class="space-y-3 pt-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Attributes</label>
                        <div class="space-y-2">
                            <label class="flex items-center text-xs text-slate-700 dark:text-slate-300 gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="filterFree" class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500" />
                                <span>Free Resources</span>
                            </label>
                            <label class="flex items-center text-xs text-slate-700 dark:text-slate-300 gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="filterPremium" class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500" />
                                <span>Premium Products</span>
                            </label>
                            <label class="flex items-center text-xs text-slate-700 dark:text-slate-300 gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="filterOnSale" class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500" />
                                <span>On Discount/Sale</span>
                            </label>
                            <label class="flex items-center text-xs text-slate-700 dark:text-slate-300 gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="filterNewest" class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500" />
                                <span>New Arrivals</span>
                            </label>
                            <label class="flex items-center text-xs text-slate-700 dark:text-slate-300 gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="filterPopular" class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500" />
                                <span>Popular/Best Seller</span>
                            </label>
                            <label class="flex items-center text-xs text-slate-700 dark:text-slate-300 gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="filterFeatured" class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500" />
                                <span>Featured Only</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upgraded Catalog Products Hub (Main Column) -->
            <div class="lg:col-span-3 space-y-6">
                
                <!-- Dynamic Header Controls (Sorting, Grid/List layout toggle, items per page) -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b border-slate-200/60 dark:border-slate-800/80 pb-6">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xs font-mono font-bold uppercase tracking-wider text-slate-400">Layout</h2>
                        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-800 p-0.5 bg-slate-100/50 dark:bg-slate-950">
                            <!-- Grid View mode -->
                            <button @click="viewMode = 'grid'" 
                                    :class="viewMode === 'grid' ? 'bg-white dark:bg-slate-900 shadow-sm text-indigo-600' : 'text-slate-400'"
                                    class="p-1.5 rounded-md cursor-pointer transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                            </button>
                            <!-- List View mode -->
                            <button @click="viewMode = 'list'" 
                                    :class="viewMode === 'list' ? 'bg-white dark:bg-slate-900 shadow-sm text-indigo-600' : 'text-slate-400'"
                                    class="p-1.5 rounded-md cursor-pointer transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-3.75 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <!-- Products Per Page selector -->
                        <div class="flex items-center gap-2">
                            <label class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Page Size</label>
                            <select x-model="perPage" 
                                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                                <option value="3">3 items</option>
                                <option value="6">6 items</option>
                                <option value="12">12 items</option>
                                <option value="24">24 items</option>
                            </select>
                        </div>

                        <!-- Sorting selector -->
                        <div class="flex items-center gap-2">
                            <label class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Sort By</label>
                            <select x-model="sorting" 
                                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                                <option value="newest">New Arrivals</option>
                                <option value="oldest">Oldest Listings</option>
                                <option value="highest_rated">Highest Rated</option>
                                <option value="lowest_price">Lowest Price</option>
                                <option value="highest_price">Highest Price</option>
                                <option value="best_selling">Best Selling</option>
                                <option value="most_popular">Most Popular</option>
                                <option value="alphabetical">Alphabetical (A-Z)</option>
                            </select>
                        </div>

                        <!-- Infinite scroll switch -->
                        <div class="flex items-center gap-2">
                            <label class="text-[10px] font-mono font-bold uppercase tracking-wider text-slate-400">Scroll Mode</label>
                            <button @click="infiniteScroll = !infiniteScroll" 
                                    :class="infiniteScroll ? 'bg-indigo-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-400'"
                                    class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                    x-text="infiniteScroll ? 'Infinite' : 'Paged'"></button>
                        </div>
                    </div>
                </div>

                <!-- Skeleton Loader State -->
                <div x-show="loading && visibleProducts.length === 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" x-cloak>
                    <template x-for="n in [1, 2, 3]">
                        <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 p-5 rounded-2xl space-y-4 animate-pulse">
                            <div class="h-44 bg-slate-200 dark:bg-slate-800 rounded-xl"></div>
                            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/3"></div>
                            <div class="h-5 bg-slate-200 dark:bg-slate-800 rounded w-3/4"></div>
                            <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-full"></div>
                            <div class="h-10 bg-slate-200 dark:bg-slate-800 rounded-xl pt-4"></div>
                        </div>
                    </template>
                </div>

                <!-- Beautiful Empty State (If filters yield no matches) -->
                <div x-show="!loading && visibleProducts.length === 0" class="py-20 text-center bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800/80 rounded-2xl" x-cloak>
                    <span class="text-5xl block animate-bounce">📦</span>
                    <h3 class="text-base font-bold text-slate-950 dark:text-white mt-4">No Products Match Filters</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 max-w-sm mx-auto leading-relaxed">
                        Adjust your pricing limits, technology tags, framework choices, or license types to broaden your discovery.
                    </p>
                    <div class="flex justify-center gap-3 mt-6">
                        <button @click="clearFilters()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">Reset Filters</button>
                        <a href="/marketplace" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition">All Categories</a>
                    </div>
                </div>

                <!-- Products Grid / List layouts -->
                <div x-show="visibleProducts.length > 0">
                    
                    <!-- GRID view template -->
                    <div x-show="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <template x-for="product in visibleProducts" :key="product.id">
                            <x-product-card isAlpine="true" />
                        </template>
                    </div>

                    <!-- LIST view template -->
                    <div x-show="viewMode === 'list'" class="space-y-4" x-cloak>
                        <template x-for="product in visibleProducts" :key="product.id">
                            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition duration-300 text-left flex flex-col sm:flex-row gap-6 relative group">
                                <div class="w-full sm:w-44 h-36 shrink-0 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200/40 dark:border-slate-800/80 flex items-center justify-center relative overflow-hidden select-none">
                                    <span class="text-5xl" x-text="product.category_slug === 'laravel' ? '🔴' : (product.category_slug === 'templates' ? '💻' : (product.category_slug === 'react' ? '⚛️' : '📦'))"></span>
                                </div>
                                <div class="flex-grow flex flex-col justify-between space-y-3">
                                    <div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-[9px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2 py-0.5 rounded uppercase tracking-wider" x-text="product.category_name"></span>
                                            <div class="flex gap-1">
                                                <template x-if="product.is_featured">
                                                    <span class="text-[9px] font-mono font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.5 rounded uppercase tracking-wider">Featured</span>
                                                </template>
                                                <template x-if="product.is_best_seller">
                                                    <span class="text-[9px] font-mono font-bold text-emerald-600 bg-emerald-500/10 px-1.5 py-0.5 rounded uppercase tracking-wider">Best Seller</span>
                                                </template>
                                            </div>
                                        </div>
                                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1 group-hover:text-indigo-600 transition" x-text="product.title"></h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed" x-text="product.short_description"></p>
                                    </div>
                                    <div class="flex items-center gap-3 pt-2 text-[10px] font-mono text-slate-400">
                                        <span x-text="'Framework: ' + (product.framework || 'N/A')"></span>
                                        <span>•</span>
                                        <span x-text="product.downloads + ' downloads'"></span>
                                        <span>•</span>
                                        <span x-text="'★ ' + product.rating"></span>
                                    </div>
                                </div>
                                <div class="w-full sm:w-44 shrink-0 border-t sm:border-t-0 sm:border-l border-slate-100 dark:border-slate-800/80 pt-4 sm:pt-0 sm:pl-6 flex flex-col justify-between items-start sm:items-end">
                                    <div class="text-left sm:text-right">
                                        <span class="text-[10px] text-slate-400 block font-mono">Price</span>
                                        <span class="text-base font-extrabold text-slate-900 dark:text-white" x-text="'KES ' + Number(product.price).toLocaleString()"></span>
                                        <template x-if="product.previous_price">
                                            <span class="text-[10px] line-through text-slate-400 block" x-text="'KES ' + Number(product.previous_price).toLocaleString()"></span>
                                        </template>
                                    </div>
                                    <div class="flex gap-2 mt-4 sm:mt-0 w-full">
                                        <button @click="openQuickPreview(product)" class="flex-grow sm:flex-grow-0 px-3 py-1.5 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-lg transition cursor-pointer">Preview</button>
                                        <button @click="openDetails(product)" class="flex-grow sm:flex-grow-0 px-3.5 py-1.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg transition cursor-pointer">Details</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Cursor Pagination Controls (Paged Mode) -->
                    <div x-show="!infiniteScroll" class="flex items-center justify-between pt-8 border-t border-slate-200/60 dark:border-slate-800/60 mt-8">
                        <button @click="currentPage--; recalculateProducts(); window.scrollTo({top: 0, behavior: 'smooth'});" 
                                :disabled="currentPage === 1"
                                class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold disabled:opacity-50 transition cursor-pointer">
                            ← Previous
                        </button>
                        <span class="text-xs text-slate-400 font-mono" x-text="'Page ' + currentPage"></span>
                        <button @click="currentPage++; recalculateProducts(); window.scrollTo({top: 0, behavior: 'smooth'});" 
                                :disabled="!hasMore"
                                class="px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold disabled:opacity-50 transition cursor-pointer">
                            Next →
                        </button>
                    </div>

                    <!-- Infinite Scroll Load More Button (Scroll Mode) -->
                    <div x-show="infiniteScroll && hasMore" class="text-center pt-8 mt-8 border-t border-slate-200/60 dark:border-slate-800/60" x-cloak>
                        <button @click="loadMore()" 
                                :disabled="loading"
                                class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md transition hover:-translate-y-0.5 cursor-pointer">
                            <span x-show="!loading">Load More Digital Products 🗃️</span>
                            <span x-show="loading">Scanning Files...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Persisted Recently Viewed Products Section -->
        <div x-show="recentlyViewed.length > 0" class="mt-20 border-t border-slate-200/60 dark:border-slate-800/60 pt-12" x-cloak>
            <div class="flex items-center justify-between mb-6">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider font-mono">Recently Viewed</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pristine catalog history synced specifically to your visitor signature.</p>
                </div>
                <button @click="recentlyViewed = []; localStorage.removeItem('juanet_recently_viewed');" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 underline font-mono">Clear History</button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                <template x-for="p in recentlyViewed" :key="p.id">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/40 dark:border-slate-800/80 p-4 rounded-xl flex items-center gap-3 shadow-sm hover:border-indigo-500/30 transition cursor-pointer group"
                         @click="openQuickPreview(p)">
                        <div class="w-12 h-12 rounded-lg bg-slate-50 dark:bg-slate-950 flex items-center justify-center shrink-0 border border-slate-200/40 dark:border-slate-800/80 select-none">
                            <span class="text-2xl" x-text="p.category_slug === 'laravel' ? '🔴' : (p.category_slug === 'templates' ? '💻' : (p.category_slug === 'react' ? '⚛️' : '📦'))"></span>
                        </div>
                        <div class="overflow-hidden">
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-indigo-600 transition" x-text="p.title"></h4>
                            <p class="text-[10px] text-slate-400 font-mono" x-text="'KES ' + Number(p.price).toLocaleString()"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Popover Quick Preview Panel Modal -->
        <div x-show="activePreviewItem" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="activePreviewItem = null"></div>

                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl p-6 space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2 py-0.5 rounded uppercase" x-text="activePreviewItem?.category_name"></span>
                            <h4 class="text-base font-bold text-slate-950 dark:text-white font-display" x-text="activePreviewItem?.title"></h4>
                        </div>
                        <button @click="activePreviewItem = null" class="text-slate-400 hover:text-slate-500 font-mono text-xs cursor-pointer">Close ✕</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Left media columns -->
                        <div class="md:col-span-1 space-y-3">
                            <div class="h-32 bg-slate-100 dark:bg-slate-950 rounded-xl flex items-center justify-center border border-slate-200/40 dark:border-slate-800">
                                <span class="text-5xl" x-text="activePreviewItem?.category_slug === 'laravel' ? '🔴' : (activePreviewItem?.category_slug === 'templates' ? '💻' : (activePreviewItem?.category_slug === 'react' ? '⚛️' : '📦'))"></span>
                            </div>
                            <div class="space-y-1.5 text-xs">
                                <span class="text-[10px] text-slate-400 block font-mono uppercase">Technologies</span>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="tech in (activePreviewItem?.technology || [])">
                                        <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded text-[10px] font-mono" x-text="tech"></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Right details panel columns -->
                        <div class="md:col-span-2 space-y-4">
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed" x-text="activePreviewItem?.description"></p>
                            
                            <div class="space-y-1.5">
                                <span class="text-[10px] text-slate-400 block font-mono uppercase">Key Features Included</span>
                                <ul class="text-xs text-slate-700 dark:text-slate-300 list-disc pl-4 space-y-1">
                                    <template x-for="feature in (activePreviewItem?.features || ['Production Codebase', 'Complete documentation', 'Continuous Updates'])">
                                        <li x-text="feature"></li>
                                    </template>
                                </ul>
                            </div>

                            <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block uppercase font-mono">Price</span>
                                    <span class="font-extrabold text-sm text-slate-950 dark:text-white" x-text="'KES ' + Number(activePreviewItem?.price).toLocaleString()"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block uppercase font-mono">Rating</span>
                                    <span class="font-bold text-slate-950 dark:text-white" x-text="'★ ' + activePreviewItem?.rating + ' (' + activePreviewItem?.review_count + ' reviews)'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommended Section inside preview -->
                    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <h5 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider font-mono">Recommended Add-ons</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <template x-for="rec in recommendations">
                                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/80 p-3 rounded-xl flex flex-col justify-between hover:border-indigo-500/40 transition cursor-pointer"
                                     @click="openQuickPreview(rec)">
                                    <div>
                                        <span class="text-[9px] font-mono font-bold text-indigo-500" x-text="rec.category_name"></span>
                                        <h6 class="text-xs font-bold text-slate-950 dark:text-white truncate" x-text="rec.title"></h6>
                                        <p class="text-[10px] text-slate-400 line-clamp-1 leading-snug" x-text="rec.short_description"></p>
                                    </div>
                                    <div class="flex justify-between items-center mt-2 pt-1.5 border-t border-slate-200/40 dark:border-slate-800/50">
                                        <span class="text-[10px] font-bold text-slate-950 dark:text-white" x-text="'KES ' + Number(rec.price).toLocaleString()"></span>
                                        <span class="text-[9px] text-indigo-500 font-bold font-mono">Select ➔</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button @click="activePreviewItem = null" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">Cancel</button>
                        <button @click="activePreviewItem = null; $dispatch('trigger-toast', { message: '✓ Digital checkout initiated.', type: 'success' })" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500 transition cursor-pointer">Buy Now &amp; Download</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal Box (Dynamic view details) -->
        <div x-show="activeDetailsItem" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="activeDetailsItem = null"></div>

                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl p-6 space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h4 class="text-base font-bold text-slate-950 dark:text-white font-display" x-text="activeDetailsItem?.title"></h4>
                        <button @click="activeDetailsItem = null" class="text-slate-400 hover:text-slate-500 font-mono text-xs cursor-pointer">Close ✕</button>
                    </div>
                    
                    <div class="space-y-4 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                        <p class="leading-relaxed" x-text="activeDetailsItem?.description"></p>
                        
                        <div class="space-y-2">
                            <span class="text-[10px] text-slate-400 block font-mono uppercase">Screenshots &amp; Workspaces Included</span>
                            <div class="grid grid-cols-3 gap-2">
                                <template x-for="screen in (activeDetailsItem?.screenshots || ['Admin Panel View', 'Settings Page Layout', 'Export PDF Portal'])">
                                    <div class="bg-slate-100 dark:bg-slate-950 p-2.5 rounded-xl text-center border border-slate-200/40 dark:border-slate-800">
                                        <span class="text-[10px] font-mono font-bold text-slate-500 dark:text-slate-400" x-text="screen"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                            <div>
                                <span class="text-[10px] text-slate-400 block uppercase font-mono">Price</span>
                                <span class="font-extrabold text-sm text-indigo-600 dark:text-indigo-400" x-text="'KES ' + Number(activeDetailsItem?.price).toLocaleString()"></span>
                            </div>
                            <button @click="activeDetailsItem = null; $dispatch('trigger-toast', { message: '✓ Licensing key recorded. Digital checkout ready.', type: 'success' })" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500 transition cursor-pointer">Add to Checkout</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<x-toast />

<!-- Dynamic SEO Schema Markup (Category Page JSON-LD) -->
@push('scripts')
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "{{ $category->name }} Hub — JUANET Marketplace",
  "description": "{{ $category->description }}",
  "url": "{{ request()->url() }}",
  "numberOfItems": {{ count($initial_products) }},
  "breadcrumb": {
    "@type": "BreadcrumbList",
    "itemListElement": [
      {
        "@type": "ListItem",
        "position": 1,
        "name": "Home",
        "item": "{{ url('/') }}"
      },
      {
        "@type": "ListItem",
        "position": 2,
        "name": "Marketplace",
        "item": "{{ route('marketplace') }}"
      },
      {
        "@type": "ListItem",
        "position": 3,
        "name": "{{ $category->name }}",
        "item": "{{ request()->url() }}"
      }
    ]
  }
}
</script>
@endpush
@endsection
