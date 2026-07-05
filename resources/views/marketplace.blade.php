@extends('layouts.public')

@section('title', 'Digital Products & Code Marketplace — JUANET')
@section('meta_description', 'Discover premium pre-built website templates, enterprise Laravel starter kits, Tailwind admin dashboards, design systems, and developer prompt resources.')

@section('content')
<div class="relative min-h-screen py-16 bg-slate-50 dark:bg-slate-950 transition-colors duration-300"
     x-data="{
        products: {{ json_encode($featured_products) }},
        categories: {{ json_encode($categories) }},
        searchQuery: '',
        selectedCategory: '',
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
        
        // Modal states
        activePreviewItem: {{ $active_product ? json_encode($active_product) : 'null' }},
        activeDetailsItem: null,
        recommendations: {{ json_encode($recommendations) }},
        
        // Newsletter States
        newsletterEmail: '',
        newsletterSubmitting: false,
        newsletterSubmitted: false,
        newsletterMessage: '',

        init() {
            // Track Marketplace Opened event on initial load
            this.$watch('searchQuery', () => this.debounceFetch());
            this.$watch('selectedCategory', () => this.fetchResults());
            this.$watch('selectedTechnology', () => this.fetchResults());
            this.$watch('minPrice', () => this.debounceFetch());
            this.$watch('maxPrice', () => this.debounceFetch());
            this.$watch('ratingFilter', () => this.fetchResults());
            this.$watch('filterFree', () => this.fetchResults());
            this.$watch('filterPremium', () => this.fetchResults());
            this.$watch('filterOnSale', () => this.fetchResults());
            this.$watch('filterNewest', () => this.fetchResults());
            this.$watch('filterPopular', () => this.fetchResults());
            this.$watch('filterFeatured', () => this.fetchResults());
        },

        debounceTimer: null,
        debounceFetch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.fetchResults();
            }, 300);
        },

        fetchResults() {
            let params = new URLSearchParams();
            if (this.searchQuery) params.append('search', this.searchQuery);
            if (this.selectedCategory) params.append('category', this.selectedCategory);
            if (this.selectedTechnology) params.append('technology', this.selectedTechnology);
            if (this.minPrice) params.append('min_price', this.minPrice);
            if (this.maxPrice) params.append('max_price', this.maxPrice);
            if (this.ratingFilter) params.append('rating', this.ratingFilter);
            if (this.filterFree) params.append('free', '1');
            if (this.filterPremium) params.append('premium', '1');
            if (this.filterOnSale) params.append('on_sale', '1');
            if (this.filterNewest) params.append('newest', '1');
            if (this.filterPopular) params.append('popular', '1');
            if (this.filterFeatured) params.append('featured', '1');

            fetch(`/api/marketplace/search?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.products = data.products;
                }
            })
            .catch(err => {
                console.error('Search fetch failed:', err);
            });
        },

        clearFilters() {
            this.searchQuery = '';
            this.selectedCategory = '';
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
            this.fetchResults();
            this.$dispatch('trigger-toast', { message: 'All filters cleared', type: 'info' });
        },

        openQuickPreview(product) {
            this.activePreviewItem = product;
            
            // Track Quick Preview event via a tracking trigger
            fetch('/api/marketplace/search', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            // Load recommendations
            fetch(`/api/marketplace/search?category=${product.category_slug}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.recommendations = data.products.filter(p => p.id !== product.id).slice(0, 3);
                    }
                });
        },

        openDetails(product) {
            window.location.href = `/marketplace/product/${product.slug}`;
        },

        submitNewsletter() {
            if (!this.newsletterEmail) {
                this.$dispatch('trigger-toast', { message: 'Please enter a valid email address.', type: 'error' });
                return;
            }
            this.newsletterSubmitting = true;

            fetch('/api/marketplace/newsletter', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ email: this.newsletterEmail })
            })
            .then(res => res.json())
            .then(data => {
                this.newsletterSubmitting = false;
                if (data.success) {
                    this.newsletterSubmitted = true;
                    this.newsletterMessage = data.message;
                    this.$dispatch('trigger-toast', { message: '✓ Subscribed successfully!', type: 'success' });
                } else {
                    this.$dispatch('trigger-toast', { message: data.message || 'An error occurred.', type: 'error' });
                }
            })
            .catch(err => {
                this.newsletterSubmitting = false;
                this.$dispatch('trigger-toast', { message: 'Failed to submit subscription.', type: 'error' });
            });
        }
     }">
    
    <!-- Ambient mesh overlay background effects -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-5%] right-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[-5%] left-[-10%] w-[50%] h-[50%] bg-emerald-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Marketplace Section Header Title -->
        <x-section-title 
            badge="Digital Catalog"
            title="Premium Digital Products &amp; Software Templates"
            subtitle="Accelerate your product delivery, elevate brand styling, and empower developer teams with production-ready Laravel starters, React frameworks, prompt logs, and vector kits."
        />

        <!-- Catalog Layout Grid: Filter sidebar + Products grid -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mt-12">
            
            <!-- Elegant Filter Panel (Sidebar on desktop) -->
            <div class="lg:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 rounded-2xl shadow-sm h-fit space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <h3 class="text-sm font-bold text-slate-950 dark:text-white uppercase tracking-wider font-mono">Filters</h3>
                    <button @click="clearFilters()" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-mono font-bold cursor-pointer">
                        Reset All
                    </button>
                </div>

                <!-- Live Keyword Search Inputs -->
                <div class="space-y-2">
                    <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Search Catalog</label>
                    <div class="relative">
                        <input type="text" x-model="searchQuery" placeholder="Title, tech, category..." 
                               class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        <span class="absolute right-3.5 top-3 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </span>
                    </div>
                </div>

                <!-- Categories filter -->
                <div class="space-y-2">
                    <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Category</label>
                    <select x-model="selectedCategory" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                        <option value="">All Categories</option>
                        <template x-for="cat in categories">
                            <option :value="cat.slug" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Technology filter -->
                <div class="space-y-2">
                    <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Technology</label>
                    <select x-model="selectedTechnology" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                        <option value="">All Technologies</option>
                        <option value="Laravel">Laravel</option>
                        <option value="React">React</option>
                        <option value="Tailwind CSS">Tailwind CSS</option>
                        <option value="Alpine.js">Alpine.js</option>
                        <option value="Gemini API">Gemini API</option>
                        <option value="Figma">Figma</option>
                        <option value="Next.js">Next.js</option>
                        <option value="PostgreSQL">PostgreSQL</option>
                    </select>
                </div>

                <!-- Price limits slider parameters -->
                <div class="space-y-2">
                    <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Price Limits (KES)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" x-model="minPrice" placeholder="Min" 
                               class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        <input type="number" x-model="maxPrice" placeholder="Max" 
                               class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                    </div>
                </div>

                <!-- Rating -->
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

                <!-- Badges, Flags & Features checklist -->
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

            <!-- Products Listing Grid -->
            <div class="lg:col-span-3 space-y-6">
                
                <!-- Quick Category Pill Selector Bar -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200/60 dark:border-slate-800/80 pb-6">
                    <div class="flex flex-wrap gap-2 text-[10px] font-mono font-bold uppercase tracking-wider">
                        <button @click="selectedCategory = ''" 
                                :class="selectedCategory === '' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                                class="px-3 py-1.5 rounded-lg transition cursor-pointer">All Assets</button>
                        <template x-for="cat in categories">
                            <button @click="selectedCategory = cat.slug" 
                                    :class="selectedCategory === cat.slug ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                                    class="px-3 py-1.5 rounded-lg transition cursor-pointer"
                                    x-text="cat.name"></button>
                        </template>
                    </div>
                    <div class="text-[11px] text-slate-400 font-mono tracking-tight">
                        Found <span class="font-bold text-slate-700 dark:text-indigo-400 font-mono" x-text="products.length"></span> digital assets
                    </div>
                </div>

                <!-- Empty State -->
                <div x-show="products.length === 0" class="py-16 text-center bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/80 rounded-2xl" x-cloak>
                    <span class="text-4xl block">🔍</span>
                    <h3 class="text-base font-bold text-slate-950 dark:text-white mt-4">No Digital Products Match Filters</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">Adjust your pricing limits, technology selections, or keyword inputs to broaden your search parameters.</p>
                    <button @click="clearFilters()" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition cursor-pointer">Clear All Filters</button>
                </div>

                <!-- Products Cards Grid container -->
                <div x-show="products.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-for="product in products" :key="product.id">
                        <x-product-card isAlpine="true" />
                    </template>
                </div>
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

        <!-- Callout Newsletter Subscription Banner -->
        <div class="mt-20 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-8 md:p-12 shadow-premium dark:shadow-premium-dark text-center max-w-4xl mx-auto">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 border border-indigo-200/20">
                Seller &amp; Affiliate Network
            </span>
            <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display mt-4">Want to Sell Your Digital Products?</h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-2 leading-relaxed max-w-2xl mx-auto">
                We are opening our regional marketplace to local creators, design leads, and Laravel specialists. Register your developer profile below to receive early documentation parameters and alpha workspace invites.
            </p>
            
            <div x-show="newsletterSubmitted" class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-950/50 p-4 rounded-2xl max-w-lg mx-auto mt-8 text-center" x-cloak>
                <h4 class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Early Access Invite Dispatched!</h4>
                <p class="text-xs text-slate-500 mt-1" x-text="newsletterMessage"></p>
            </div>

            <div x-show="!newsletterSubmitted" class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto mt-8">
                <input type="email" x-model="newsletterEmail" placeholder="E.g., dev@company.com" 
                       class="flex-grow bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                <button type="button" @click="submitNewsletter()" :disabled="newsletterSubmitting" 
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-6 py-3 shadow-sm hover:-translate-y-0.5 transition cursor-pointer shrink-0">
                    <span x-show="!newsletterSubmitting">Join Developer Alpha</span>
                    <span x-show="newsletterSubmitting">Registering...</span>
                </button>
            </div>
        </div>

    </div>
</div>

<x-toast />
@endsection
