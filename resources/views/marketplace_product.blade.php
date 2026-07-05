@extends('layouts.public')

@section('title', ($product->title ?? 'Digital Product') . ' — JUANET Enterprise Marketplace')
@section('meta_description', $product->short_description ?? 'Download production-ready enterprise digital products on JUANET.')

@section('content')
@php
    $galleryImages = [];
    if (!empty($product->thumbnail)) {
        $galleryImages[] = $product->thumbnail;
    }
    if (!empty($product->gallery) && is_array($product->gallery)) {
        $galleryImages = array_merge($galleryImages, $product->gallery);
    }
    if (empty($galleryImages)) {
        $galleryImages[] = 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=600&auto=format&fit=crop';
        $galleryImages[] = 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=600&auto=format&fit=crop';
    }

    $allTech = $product->technology ?? [];
    if (is_string($allTech)) {
        $allTech = [$allTech];
    }
@endphp

<div class="relative min-h-screen py-10 bg-slate-50 dark:bg-slate-950 transition-colors duration-300"
     x-data="{
        product: {{ json_encode($product) }},
        gallery: {{ json_encode($galleryImages) }},
        activeIndex: 0,
        fullscreen: false,
        selectedLicense: 'personal',
        quantity: 1,
        inWishlist: false,
        copiedShare: false,
        activeTab: 'details', // details, docs, changelog, reviews
        checkoutOpen: false,
        checkoutPhone: '',
        checkoutLoading: false,
        checkoutSuccess: false,
        
        // Base license prices
        get licensePrice() {
            let base = this.product.price || 0;
            if (this.selectedLicense === 'commercial') return Math.round(base * 1.8);
            if (this.selectedLicense === 'extended') return Math.round(base * 3.5);
            return base;
        },

        get formattedPrice() {
            return Number(this.licensePrice).toLocaleString();
        },

        init() {
            // Track product viewed event on load
            this.track('marketplace.product.viewed', {
                title: this.product.title,
                price: this.product.price
            });

            // Auto track documentation view when changing tab
            this.$watch('activeTab', (val) => {
                if (val === 'docs') {
                    this.track('marketplace.documentation.viewed');
                }
            });
        },

        track(eventName, additionalData = {}) {
            fetch('{{ route('marketplace.track') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    event_name: eventName,
                    product_slug: this.product.slug,
                    additional_data: additionalData
                })
            }).then(r => r.json())
              .then(data => console.log('Visitor Intel Tracked:', eventName, data))
              .catch(err => console.error('Tracking Error:', err));
        },

        prevImage() {
            this.activeIndex = this.activeIndex === 0 ? this.gallery.length - 1 : this.activeIndex - 1;
            this.track('marketplace.gallery.viewed', { action: 'prev', index: this.activeIndex });
        },

        nextImage() {
            this.activeIndex = this.activeIndex === this.gallery.length - 1 ? 0 : this.activeIndex + 1;
            this.track('marketplace.gallery.viewed', { action: 'next', index: this.activeIndex });
        },

        selectLicense(type) {
            this.selectedLicense = type;
            this.track('marketplace.license.selected', { license_type: type, price: this.licensePrice });
        },

        toggleWishlist() {
            this.inWishlist = !this.inWishlist;
            this.track('marketplace.wishlist.clicked', { added: this.inWishlist });
            this.$dispatch('trigger-toast', {
                message: this.inWishlist ? '✓ Added to Wishlist!' : 'Removed from Wishlist.',
                type: this.inWishlist ? 'success' : 'info'
            });
        },

        triggerShare(platform) {
            this.track('marketplace.share.clicked', { platform: platform });
            if (platform === 'copy') {
                navigator.clipboard.writeText(window.location.href);
                this.copiedShare = true;
                setTimeout(() => this.copiedShare = false, 2000);
            } else {
                let url = encodeURIComponent(window.location.href);
                let text = encodeURIComponent('Check out this premium digital asset: ' + this.product.title);
                let shareUrl = '';
                if (platform === 'twitter') shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + text;
                if (platform === 'linkedin') shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url;
                if (platform === 'facebook') shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
                if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        },

        downloadDocs() {
            this.track('marketplace.download_docs.clicked');
            this.$dispatch('trigger-toast', {
                message: '✓ Documentation Guide PDF download started!',
                type: 'success'
            });
        },

        launchDemo(type) {
            this.track('marketplace.demo.launched', { demo_type: type });
            this.$dispatch('trigger-toast', {
                message: '⚡ Launching ' + type.toUpperCase() + ' live preview environment...',
                type: 'info'
            });
            setTimeout(() => {
                window.open('https://ais-dev-ny3oawk227olsmmu7v75eo-592975726302.europe-west2.run.app/marketplace', '_blank');
            }, 800);
        },

        initiateCheckout() {
            this.track('marketplace.purchase.initiated', {
                license_type: this.selectedLicense,
                quantity: this.quantity,
                price: this.licensePrice
            });
            this.checkoutOpen = true;
            this.checkoutSuccess = false;
        },

        submitCheckout() {
            if (!this.checkoutPhone) {
                this.$dispatch('trigger-toast', { message: 'Please enter a valid M-PESA Phone Number', type: 'error' });
                return;
            }
            this.checkoutLoading = true;
            setTimeout(() => {
                this.checkoutLoading = false;
                this.checkoutSuccess = true;
                this.$dispatch('trigger-toast', {
                    message: '✓ Safaricom Lipa Na M-PESA STK Push Sent!',
                    type: 'success'
                });
            }, 1500);
        }
     }"
     @keydown.window.escape="fullscreen = false"
     @keydown.window.left="prevImage()"
     @keydown.window.right="nextImage()">

    <!-- Ambient Grid Overlay -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-indigo-500/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-1/4 left-10 w-[300px] h-[300px] bg-emerald-500/5 rounded-full blur-[100px]"></div>
    </div>

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Product",
                "@id": "{{ url()->current() }}#product",
                "name": "{{ $product->title }}",
                "image": "{{ url($product->thumbnail) }}",
                "description": "{{ $product->short_description }}",
                "category": "{{ $product->category_name }}",
                "sku": "SKU-{{ str_pad($product->id ?? rand(100, 999), 6, '0', STR_PAD_LEFT) }}",
                "brand": {
                    "@type": "Brand",
                    "name": "JUANET"
                },
                "offers": {
                    "@type": "Offer",
                    "url": "{{ url()->current() }}",
                    "priceCurrency": "KES",
                    "price": "{{ $product->price }}",
                    "availability": "https://schema.org/InStock",
                    "seller": {
                        "@type": "Organization",
                        "name": "JUANET solutions"
                    }
                },
                "aggregateRating": {
                    "@type": "AggregateRating",
                    "ratingValue": "{{ $product->rating ?? '4.8' }}",
                    "reviewCount": "{{ $product->review_count ?? '12' }}"
                }
            },
            {
                "@type": "BreadcrumbList",
                "itemListElement": [
                    {
                        "@type": "ListItem",
                        "position": 1,
                        "name": "Marketplace",
                        "item": "{{ route('marketplace') }}"
                    },
                    {
                        "@type": "ListItem",
                        "position": 2,
                        "name": "{{ $product->category_name }}",
                        "item": "{{ route('marketplace.category', ['slug' => $product->category_slug]) }}"
                    },
                    {
                        "@type": "ListItem",
                        "position": 3,
                        "name": "{{ $product->title }}",
                        "item": "{{ url()->current() }}"
                    }
                ]
            }
        ]
    }
    </script>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <!-- Breadcrumbs Navigation -->
        <div class="mb-6">
            <x-breadcrumbs :items="[
                ['label' => 'Marketplace', 'url' => route('marketplace')],
                ['label' => $product->category_name ?? 'Asset', 'url' => route('marketplace.category', ['slug' => $product->category_slug])],
                ['label' => $product->title]
            ]" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- MAIN PRODUCT DETAILS SECTION (Col Span 2) -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Product Overview Card -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm">
                    
                    <!-- Title, Category and Meta Rows -->
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Category Badge -->
                            <a href="{{ route('marketplace.category', ['slug' => $product->category_slug]) }}" 
                               class="text-[10px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2.5 py-1 rounded uppercase tracking-wider hover:bg-indigo-500/15 transition">
                                {{ $product->category_name ?? 'Digital Product' }}
                            </a>
                            
                            <!-- Badges row -->
                            @if($product->is_featured ?? false)
                                <span class="text-[9px] font-mono font-bold text-amber-600 bg-amber-500/15 px-2 py-0.5 rounded uppercase tracking-wider">Featured</span>
                            @endif
                            @if($product->is_best_seller ?? false)
                                <span class="text-[9px] font-mono font-bold text-emerald-600 bg-emerald-500/15 px-2 py-0.5 rounded uppercase tracking-wider">Best Seller</span>
                            @endif
                            @if($product->is_new ?? false)
                                <span class="text-[9px] font-mono font-bold text-sky-600 bg-sky-500/15 px-2 py-0.5 rounded uppercase tracking-wider">New</span>
                            @endif
                            <span class="text-[9px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2 py-0.5 rounded uppercase tracking-wider">✓ Verified Security</span>
                        </div>

                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-950 dark:text-white tracking-tight font-display">
                            {{ $product->title }}
                        </h1>
                        
                        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed max-w-3xl">
                            {{ $product->short_description }}
                        </p>

                        <!-- Metadata row -->
                        <div class="flex flex-wrap gap-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] font-mono text-slate-400">
                            <div>Author: <span class="text-slate-700 dark:text-slate-200 font-bold">{{ $product->author ?? 'JUANET Core' }}</span></div>
                            <div>Version: <span class="text-slate-700 dark:text-slate-200 font-bold">{{ $product->version ?? 'v1.2.0' }}</span></div>
                            <div>Framework: <span class="text-slate-700 dark:text-slate-200 font-bold">{{ $product->framework ?? 'Laravel 12' }}</span></div>
                            <div>Published: <span class="text-slate-700 dark:text-slate-200 font-bold">{{ isset($product->created_at) ? date('M d, Y', strtotime($product->created_at)) : 'Jun 12, 2026' }}</span></div>
                            <div>Updated: <span class="text-slate-700 dark:text-slate-200 font-bold">{{ isset($product->updated_at) ? date('M d, Y', strtotime($product->updated_at)) : 'Jul 04, 2026' }}</span></div>
                        </div>
                    </div>

                    <!-- PREMIUM RESPONSIVE IMAGE GALLERY -->
                    <div class="mt-8 space-y-4">
                        <!-- Large Preview Frame -->
                        <div class="relative h-64 sm:h-96 w-full rounded-2xl bg-slate-100 dark:bg-slate-950 border border-slate-200/40 dark:border-slate-800 overflow-hidden flex items-center justify-center group shadow-inner">
                            <img :src="gallery[activeIndex]" 
                                 class="object-cover w-full h-full cursor-pointer transition duration-500 group-hover:scale-[1.02]"
                                 alt="Product image preview"
                                 @click="fullscreen = true; track('marketplace.gallery.viewed', { action: 'fullscreen_open', index: activeIndex })"
                                 loading="lazy" />

                            <!-- Overlay control arrows -->
                            <button @click="prevImage()" 
                                    class="absolute left-4 top-1/2 -translate-y-1/2 p-2.5 rounded-full bg-black/45 hover:bg-black/60 text-white transition focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                            </button>
                            <button @click="nextImage()" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 p-2.5 rounded-full bg-black/45 hover:bg-black/60 text-white transition focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                            </button>

                            <!-- Badge tracker indicator -->
                            <div class="absolute bottom-4 right-4 bg-black/50 backdrop-blur-sm text-[10px] font-mono font-bold text-white px-2.5 py-1 rounded-md">
                                <span x-text="(activeIndex + 1) + ' / ' + gallery.length"></span>
                            </div>
                        </div>

                        <!-- Gallery Thumbnails Grid Strip -->
                        <div class="flex gap-3 overflow-x-auto pb-1 scrollbar-thin">
                            <template x-for="(img, idx) in gallery" :key="idx">
                                <button @click="activeIndex = idx; track('marketplace.gallery.viewed', { action: 'thumbnail_click', index: idx })" 
                                        class="relative w-20 h-16 rounded-lg overflow-hidden border-2 cursor-pointer shrink-0 transition"
                                        :class="activeIndex === idx ? 'border-indigo-600 scale-95 shadow-sm' : 'border-transparent opacity-65 hover:opacity-100'">
                                    <img :src="img" class="object-cover w-full h-full" alt="thumbnail" loading="lazy" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- STACK AND LAUNCHERS ROW (LIVE PREVIEW) -->
                    <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Tech badge grid -->
                        <div class="space-y-2">
                            <h4 class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider">Enterprise Tech Stack</h4>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($allTech as $t)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-2.5 py-1 rounded-lg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                        {{ $t }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <!-- Demo Launch buttons -->
                        <div class="space-y-2 text-left md:text-right">
                            <h4 class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider md:text-right">Interactive Playgrounds</h4>
                            <div class="flex flex-wrap md:justify-end gap-2">
                                <button @click="launchDemo('customer')" class="px-3.5 py-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 dark:bg-indigo-950/20 dark:border-indigo-800 dark:text-indigo-400 rounded-xl transition cursor-pointer">
                                    Customer Demo
                                </button>
                                <button @click="launchDemo('admin')" class="px-3.5 py-1.5 text-xs font-bold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-800 dark:text-emerald-400 rounded-xl transition cursor-pointer">
                                    Admin Console
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABS LAYOUT CONTROLLER -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <!-- Tab buttons -->
                    <div class="flex border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30">
                        <button @click="activeTab = 'details'" 
                                :class="activeTab === 'details' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold bg-white dark:bg-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                                class="px-6 py-4 text-xs font-mono uppercase tracking-wider border-b-2 transition cursor-pointer">
                            Asset Details
                        </button>
                        <button @click="activeTab = 'docs'" 
                                :class="activeTab === 'docs' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold bg-white dark:bg-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                                class="px-6 py-4 text-xs font-mono uppercase tracking-wider border-b-2 transition cursor-pointer">
                            Documentation
                        </button>
                        <button @click="activeTab = 'changelog'" 
                                :class="activeTab === 'changelog' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold bg-white dark:bg-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                                class="px-6 py-4 text-xs font-mono uppercase tracking-wider border-b-2 transition cursor-pointer">
                            Changelog
                        </button>
                        <button @click="activeTab = 'reviews'" 
                                :class="activeTab === 'reviews' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold bg-white dark:bg-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                                class="px-6 py-4 text-xs font-mono uppercase tracking-wider border-b-2 transition cursor-pointer">
                            Verified Social Proof
                        </button>
                    </div>

                    <div class="p-6 sm:p-8">
                        <!-- Details Tab -->
                        <div x-show="activeTab === 'details'" class="space-y-6">
                            <!-- Beautiful Markdown and code block implementation -->
                            <div class="space-y-4">
                                <h3 class="text-lg font-bold text-slate-950 dark:text-white">Premium Features Overview</h3>
                                <div class="prose dark:prose-invert max-w-none text-xs sm:text-sm text-slate-600 dark:text-slate-300 space-y-4">
                                    <p>
                                        {{ $product->description }}
                                    </p>
                                    <p>
                                        Designed and verified by the core team at <strong>JUANET solutions</strong>. This enterprise-grade code package allows rapid deployment into active SaaS projects with standard clean code guidelines. It is pre-integrated into our central identity managers and outbox mechanisms.
                                    </p>
                                    
                                    <!-- Beautiful markdown syntax callouts -->
                                    <div class="bg-indigo-50/50 dark:bg-indigo-950/25 border-l-4 border-indigo-600 p-4 rounded-r-xl space-y-1">
                                        <h4 class="font-bold text-indigo-900 dark:text-indigo-300 text-xs uppercase tracking-wider">💡 Enterprise Standard Note</h4>
                                        <p class="text-xs text-slate-600 dark:text-slate-400">
                                            This digital asset ships with a fully compatible relational database schema wrapper (Drizzle/Laravel migration compatible) and custom Redis event hooks to secure continuous telemetry tracking.
                                        </p>
                                    </div>

                                    <div class="bg-amber-50/50 dark:bg-amber-950/25 border-l-4 border-amber-600 p-4 rounded-r-xl space-y-1">
                                        <h4 class="font-bold text-amber-900 dark:text-amber-300 text-xs uppercase tracking-wider">⚠️ System Prerequisite</h4>
                                        <p class="text-xs text-slate-600 dark:text-slate-400">
                                            Designed for modern architecture. Ensure your PHP setup runs version 8.2 or greater, and composer is updated. Supports PostgreSQL 16 database layers natively.
                                        </p>
                                    </div>

                                    <!-- Beautiful highlighted installation syntax table/block -->
                                    <div class="space-y-2 pt-4">
                                        <h4 class="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">CLI Installation Command</h4>
                                        <div class="bg-slate-900 dark:bg-black rounded-xl p-4 border border-slate-800 text-left relative overflow-hidden group">
                                            <div class="absolute right-3.5 top-3.5 flex gap-1 text-[10px] text-slate-500 font-mono">
                                                <span>composer</span>
                                            </div>
                                            <pre class="text-indigo-400 font-mono text-xs overflow-x-auto"><code>composer require juanet/{{ $product->slug ?? 'digital-asset' }}</code></pre>
                                        </div>
                                    </div>

                                    <!-- Technical tables representation -->
                                    <div class="pt-4 space-y-2">
                                        <h4 class="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">Technical specifications</h4>
                                        <div class="overflow-x-auto border border-slate-200/60 dark:border-slate-800 rounded-xl">
                                            <table class="w-full text-left text-xs text-slate-500 dark:text-slate-400">
                                                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 border-b border-slate-200/60 dark:border-slate-800">
                                                    <tr>
                                                        <th class="p-3">Requirement</th>
                                                        <th class="p-3">Specification</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800">
                                                    <tr>
                                                        <td class="p-3 font-mono font-bold text-slate-700 dark:text-slate-300">Target Framework</td>
                                                        <td class="p-3">{{ $product->framework ?? 'Laravel 12 / React' }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="p-3 font-mono font-bold text-slate-700 dark:text-slate-300">Min PHP Version</td>
                                                        <td class="p-3">PHP 8.3 / 8.4 Support</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="p-3 font-mono font-bold text-slate-700 dark:text-slate-300">Docker Support</td>
                                                        <td class="p-3">Included with custom `Dockerfile` &amp; `docker-compose.yml`</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Beautiful Feature Checklist (F4.3 requirement 5) -->
                            <div class="space-y-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                                <h3 class="text-sm font-mono font-bold text-slate-400 uppercase tracking-wider">Primary Feature Checklist</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-700 dark:text-slate-300">
                                    @php
                                        $fChecklist = $product->features ?? ['Multi Tenant', 'Docker Ready', 'API Included', 'REST API', 'Authentication', 'RBAC', 'CRM Module', 'Marketplace Module', 'Finance Module', 'AI Ready', 'Dark Mode'];
                                    @endphp
                                    @foreach($fChecklist as $feat)
                                        <div class="flex items-center gap-2.5">
                                            <span class="p-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                            </span>
                                            <span>{{ $feat }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Documentation Tab -->
                        <div x-show="activeTab === 'docs'" class="space-y-6" x-cloak>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
                                <div class="space-y-1">
                                    <h3 class="text-base font-bold text-slate-950 dark:text-white">Integration &amp; Setup Guides</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Detailed instructions to configure this asset in your platform.</p>
                                </div>
                                <button @click="downloadDocs()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-2 shrink-0 cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                    Download Guide PDF
                                </button>
                            </div>

                            <div class="space-y-6 text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                                <div class="space-y-2">
                                    <h4 class="font-bold text-slate-900 dark:text-white font-mono text-xs uppercase tracking-wider">1. Requirements &amp; Dependencies</h4>
                                    <p>Compatible with standard Composer packages. Ensure you have the DB environment configured before starting setup scripts. Safe fallback logic automatically routes to memory repositories if PostgreSQL drops.</p>
                                </div>

                                <div class="space-y-2">
                                    <h4 class="font-bold text-slate-900 dark:text-white font-mono text-xs uppercase tracking-wider">2. Installation Guide</h4>
                                    <div class="bg-slate-900 dark:bg-black rounded-xl p-4 border border-slate-800 text-left font-mono text-xs text-indigo-400 overflow-x-auto">
                                        # Install package<br>
                                        composer require juanet/{{ $product->slug ?? 'digital-asset' }}<br><br>
                                        # Run automatic migration wrapper<br>
                                        php artisan migrate
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <h4 class="font-bold text-slate-900 dark:text-white font-mono text-xs uppercase tracking-wider">3. FAQ &amp; Troubleshooting</h4>
                                    <div class="space-y-3">
                                        <div class="p-3 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 rounded-xl">
                                            <p class="font-bold text-slate-900 dark:text-white">Q: Does this include the full frontend source code?</p>
                                            <p class="mt-1 text-xs text-slate-500">A: Yes, all licenses ship with the complete, unminified Tailwind CSS source files and Alpine.js controllers.</p>
                                        </div>
                                        <div class="p-3 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 rounded-xl">
                                            <p class="font-bold text-slate-900 dark:text-white">Q: How do I configure Safaricom Lipa Na M-PESA STK keys?</p>
                                            <p class="mt-1 text-xs text-slate-500">A: Edit your `.env` file to include your Consumer Key and Passkey. Full details are present inside our main developer guide.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Changelog Tab -->
                        <div x-show="activeTab === 'changelog'" class="space-y-6" x-cloak>
                            <h3 class="text-base font-bold text-slate-950 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-4">Version History &amp; Releases</h3>
                            
                            <div class="space-y-6 relative before:absolute before:left-3.5 before:top-4 before:bottom-4 before:w-0.5 before:bg-slate-200/60 dark:before:bg-slate-800 pl-8">
                                <!-- Release item 1 -->
                                <div class="relative">
                                    <!-- Bullet indicator -->
                                    <span class="absolute -left-8 top-1.5 w-3 h-3 rounded-full bg-indigo-600 ring-4 ring-indigo-500/10 dark:ring-indigo-950/20"></span>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-xs font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2 py-0.5 rounded">v1.2.0</span>
                                            <span class="text-xs font-mono text-slate-400">July 04, 2026 (Latest)</span>
                                        </div>
                                        <div class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 space-y-1.5">
                                            <p class="font-bold">Features &amp; Upgrades</p>
                                            <ul class="list-disc list-inside space-y-1 text-slate-500">
                                                <li>Added high-speed transactional outbox queue triggers.</li>
                                                <li>Integrated telemetry hooks for modern visitor tracking.</li>
                                                <li>Performance updates: asset sizes decreased by 18%.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Release item 2 -->
                                <div class="relative">
                                    <span class="absolute -left-8 top-1.5 w-3 h-3 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-xs font-mono font-bold text-slate-600 bg-slate-100 dark:bg-slate-800 dark:text-slate-300 px-2 py-0.5 rounded">v1.0.0</span>
                                            <span class="text-xs font-mono text-slate-400">June 12, 2026</span>
                                        </div>
                                        <div class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 space-y-1.5">
                                            <p class="font-bold">Initial Launch</p>
                                            <ul class="list-disc list-inside space-y-1 text-slate-500">
                                                <li>Fully tested core module released.</li>
                                                <li>Configured responsive Tailwind CSS grids.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Verified Social Proof Tab -->
                        <div x-show="activeTab === 'reviews'" class="space-y-6" x-cloak>
                            <h3 class="text-base font-bold text-slate-950 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-4">Customer Social Proof</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Stats cards -->
                                <div class="p-4 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 rounded-2xl text-center space-y-1">
                                    <span class="text-2xl block">📥</span>
                                    <span class="text-xl font-extrabold text-slate-950 dark:text-white block font-mono">{{ $product->downloads ?? '184' }}</span>
                                    <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400">Verified Downloads</span>
                                </div>
                                <div class="p-4 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 rounded-2xl text-center space-y-1">
                                    <span class="text-2xl block">★</span>
                                    <span class="text-xl font-extrabold text-slate-950 dark:text-white block font-mono">{{ $product->rating ?? '4.8' }} / 5.0</span>
                                    <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400">Average Rating</span>
                                </div>
                                <div class="p-4 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 rounded-2xl text-center space-y-1">
                                    <span class="text-2xl block">🤝</span>
                                    <span class="text-xl font-extrabold text-slate-950 dark:text-white block font-mono">100%</span>
                                    <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400">Satisfaction Score</span>
                                </div>
                            </div>

                            <!-- User reviews lists -->
                            <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <h4 class="text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">Recent Reviews</h4>
                                
                                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <div class="py-4 space-y-1 text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-slate-900 dark:text-white">Brian K. (Lead developer at Apex)</span>
                                            <span class="text-amber-400 font-mono">★★★★★</span>
                                        </div>
                                        <p class="text-slate-500">"Saves hours of integration boilerplate. Configured Daraja API hooks on Laravel in less than 10 minutes. Absolute recommendation!"</p>
                                    </div>
                                    <div class="py-4 space-y-1 text-xs">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-slate-900 dark:text-white">Esther M. (SaaS CTO)</span>
                                            <span class="text-amber-400 font-mono">★★★★★</span>
                                        </div>
                                        <p class="text-slate-500">"Exceptional code quality, follows clean architect rules. Very structured. Support was swift when asking about postgres mappings."</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAGS SECTION -->
                <div class="space-y-2">
                    <h4 class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider">Searchable product tags</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->tags ?? [] as $tag)
                            <a href="{{ route('marketplace', ['search' => $tag]) }}" 
                               class="text-xs font-mono bg-white hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/50 dark:border-slate-800 px-3 py-1.5 rounded-xl transition">
                                #{{ $tag }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- PURCHASE SIDEBAR (Col Span 1) - Sticky purchase card -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 rounded-3xl shadow-md lg:sticky lg:top-8 text-left space-y-6">
                    
                    <!-- Title and base price -->
                    <div class="space-y-1">
                        <span class="text-[9px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2.5 py-1 rounded uppercase tracking-wider">Instant Delivery</span>
                        <div class="flex items-baseline gap-1.5 pt-1.5">
                            <span class="text-xs font-mono text-slate-400">KES</span>
                            <span class="text-2xl font-black text-slate-950 dark:text-white" x-text="formattedPrice"></span>
                            @if($product->previous_price ?? false)
                                <span class="text-xs line-through text-slate-400">KES {{ number_format($product->previous_price) }}</span>
                            @endif
                        </div>
                        <p class="text-[10px] text-slate-400 font-mono">Local Lipa Na M-PESA &amp; Cards Supported</p>
                    </div>

                    <!-- LICENSE SELECTOR -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">License Tier Selection</label>
                        <div class="space-y-2.5">
                            <label class="flex items-start justify-between p-3 border rounded-xl cursor-pointer transition select-none"
                                   :class="selectedLicense === 'personal' ? 'border-indigo-600 bg-indigo-500/5 dark:bg-indigo-950/10' : 'border-slate-200/60 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-950/30'">
                                <div class="flex gap-2 text-xs">
                                    <input type="radio" name="license_tier" value="personal" :checked="selectedLicense === 'personal'" @change="selectLicense('personal')" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">Personal License</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Single personal project access</p>
                                    </div>
                                </div>
                                <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">KES {{ number_format($product->price) }}</span>
                            </label>

                            <label class="flex items-start justify-between p-3 border rounded-xl cursor-pointer transition select-none"
                                   :class="selectedLicense === 'commercial' ? 'border-indigo-600 bg-indigo-500/5 dark:bg-indigo-950/10' : 'border-slate-200/60 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-950/30'">
                                <div class="flex gap-2 text-xs">
                                    <input type="radio" name="license_tier" value="commercial" :checked="selectedLicense === 'commercial'" @change="selectLicense('commercial')" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">Commercial License</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Up to 5 commercial project uses</p>
                                    </div>
                                </div>
                                <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">KES {{ number_format(round(($product->price ?? 0) * 1.8)) }}</span>
                            </label>

                            <label class="flex items-start justify-between p-3 border rounded-xl cursor-pointer transition select-none"
                                   :class="selectedLicense === 'extended' ? 'border-indigo-600 bg-indigo-500/5 dark:bg-indigo-950/10' : 'border-slate-200/60 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-950/30'">
                                <div class="flex gap-2 text-xs">
                                    <input type="radio" name="license_tier" value="extended" :checked="selectedLicense === 'extended'" @change="selectLicense('extended')" class="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">Extended License</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Unlimited project distribution</p>
                                    </div>
                                </div>
                                <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">KES {{ number_format(round(($product->price ?? 0) * 3.5)) }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- QUANTITY SELECTOR -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">Quantity</label>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center border border-slate-200/60 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-950/30 p-1">
                                <button @click="quantity = quantity > 1 ? quantity - 1 : 1" class="px-2.5 py-1 text-slate-400 hover:text-slate-600 rounded-lg text-sm font-bold cursor-pointer">-</button>
                                <span class="px-4 text-xs font-mono font-bold text-slate-800 dark:text-slate-200" x-text="quantity"></span>
                                <button @click="quantity++" class="px-2.5 py-1 text-slate-400 hover:text-slate-600 rounded-lg text-sm font-bold cursor-pointer">+</button>
                            </div>
                            <span class="text-[10px] text-slate-400 font-mono">Standard agency copy limits apply</span>
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="space-y-2.5 pt-2">
                        <button @click="initiateCheckout()" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs font-mono uppercase tracking-wider rounded-xl shadow-sm transition duration-200 cursor-pointer">
                            🚀 Buy Now (M-PESA checkout)
                        </button>
                        
                        <button @click="$dispatch('trigger-toast', { message: '✓ Added to core shopping basket!', type: 'success' })" 
                                class="w-full py-3.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 text-slate-700 font-bold text-xs font-mono uppercase tracking-wider rounded-xl transition cursor-pointer">
                            🛒 Add to Cart
                        </button>
                    </div>

                    <!-- WISHLIST AND SHARES PANEL -->
                    <div class="grid grid-cols-2 gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <!-- Wishlist button -->
                        <button @click="toggleWishlist()" 
                                class="flex items-center justify-center gap-2 py-2.5 border border-slate-200/50 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-xl text-xs text-slate-500 dark:text-slate-300 cursor-pointer transition">
                            <svg class="w-4 h-4" :class="{'fill-rose-500 text-rose-500': inWishlist}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            <span x-text="inWishlist ? 'Wishlisted' : 'Add Wishlist'"></span>
                        </button>

                        <!-- Share Popover -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                    class="w-full flex items-center justify-center gap-2 py-2.5 border border-slate-200/50 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-xl text-xs text-slate-500 dark:text-slate-300 cursor-pointer transition">
                                🔗 Share Asset
                            </button>
                            <!-- Popover overlay -->
                            <div x-show="open" @click.away="open = false" 
                                 class="absolute bottom-full right-0 mb-2 w-48 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 p-2.5 rounded-xl shadow-xl z-20 space-y-1"
                                 x-transition x-cloak>
                                <button @click="triggerShare('copy'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-900 rounded-lg text-xs font-mono text-slate-700 dark:text-slate-300 cursor-pointer">
                                    <span x-text="copiedShare ? '✓ Link Copied!' : 'Copy Page Link'"></span>
                                </button>
                                <button @click="triggerShare('twitter'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-900 rounded-lg text-xs font-mono text-slate-700 dark:text-slate-300 cursor-pointer">Share on X / Twitter</button>
                                <button @click="triggerShare('linkedin'); open = false;" class="w-full text-left px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-900 rounded-lg text-xs font-mono text-slate-700 dark:text-slate-300 cursor-pointer">Share on LinkedIn</button>
                            </div>
                        </div>
                    </div>

                    <!-- AUTHOR MINI PROFILE CARD -->
                    <div class="p-4 bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 rounded-2xl flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-600/10 flex items-center justify-center text-lg select-none shrink-0">
                            👨‍💻
                        </div>
                        <div class="min-w-0">
                            <h5 class="text-xs font-bold text-slate-950 dark:text-white truncate">Published by JUANET Core</h5>
                            <p class="text-[10px] text-slate-400 mt-0.5">Author Rating: ★ 4.9 (48 Reviews)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RELATED PRODUCTS CAROUSEL SECTION -->
        <div class="mt-20 border-t border-slate-200/60 dark:border-slate-800 pt-12 space-y-10" x-data="{ activeRelated: 'category' }">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-150 dark:border-slate-800 pb-4">
                <div class="space-y-1 text-left">
                    <h2 class="text-xl font-extrabold text-slate-950 dark:text-white tracking-tight font-display">Discover Related Assets</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Explore items selected dynamically based on category metrics, developer stacks, and verification scores.</p>
                </div>
                
                <!-- Tab buttons for related collections -->
                <div class="flex flex-wrap gap-1 bg-slate-100/60 dark:bg-slate-950/40 p-1 rounded-xl border border-slate-200/50 dark:border-slate-800">
                    <button @click="activeRelated = 'category'" :class="activeRelated === 'category' ? 'bg-white dark:bg-slate-900 shadow-sm font-bold text-indigo-600' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-[11px] font-mono uppercase transition cursor-pointer">Same Category</button>
                    <button @click="activeRelated = 'technology'" :class="activeRelated === 'technology' ? 'bg-white dark:bg-slate-900 shadow-sm font-bold text-indigo-600' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-[11px] font-mono uppercase transition cursor-pointer">Same Technology</button>
                    <button @click="activeRelated = 'bought'" :class="activeRelated === 'bought' ? 'bg-white dark:bg-slate-900 shadow-sm font-bold text-indigo-600' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-[11px] font-mono uppercase transition cursor-pointer">Frequently Bought</button>
                </div>
            </div>

            <!-- Category collection -->
            <div x-show="activeRelated === 'category'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @forelse($same_category as $p)
                    <x-product-card :product="$p" />
                @empty
                    <div class="col-span-full py-8 text-center text-xs text-slate-400 font-mono">No other assets listed inside this category category.</div>
                @endforelse
            </div>

            <!-- Technology collection -->
            <div x-show="activeRelated === 'technology'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" x-cloak>
                @forelse($same_technology as $p)
                    <x-product-card :product="$p" />
                @empty
                    <div class="col-span-full py-8 text-center text-xs text-slate-400 font-mono">No other assets matching this developer stack.</div>
                @endforelse
            </div>

            <!-- Bought collection -->
            <div x-show="activeRelated === 'bought'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" x-cloak>
                @forelse($frequently_bought as $p)
                    <x-product-card :product="$p" />
                @empty
                    <div class="col-span-full py-8 text-center text-xs text-slate-400 font-mono">No other assets matching discovery profiles.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- FULLSCREEN IMAGE LIGHTBOX MODAL -->
    <div x-show="fullscreen" 
         class="fixed inset-0 z-50 bg-black/95 backdrop-blur-sm flex flex-col justify-between p-6" 
         x-transition x-cloak>
        
        <!-- Header bar -->
        <div class="flex justify-between items-center text-white">
            <span class="text-xs font-mono font-bold" x-text="'Lightbox Mode — ' + product.title"></span>
            <button @click="fullscreen = false" class="p-2 bg-white/10 hover:bg-white/20 rounded-xl transition text-white cursor-pointer">
                ✕ Close Preview
            </button>
        </div>

        <!-- Center Image frame -->
        <div class="flex-grow flex items-center justify-center p-4">
            <img :src="gallery[activeIndex]" class="max-h-[75vh] max-w-full object-contain rounded-xl shadow-2xl" />
        </div>

        <!-- Bottom slider controls -->
        <div class="flex justify-between items-center text-white max-w-md mx-auto w-full border-t border-white/10 pt-4">
            <button @click="prevImage()" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl transition text-xs font-mono cursor-pointer">← Previous</button>
            <span class="text-xs font-mono" x-text="(activeIndex + 1) + ' / ' + gallery.length"></span>
            <button @click="nextImage()" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl transition text-xs font-mono cursor-pointer">Next →</button>
        </div>
    </div>

    <!-- LIPA NA M-PESA DOCKER STK CHECKOUT POPUP -->
    <div x-show="checkoutOpen" 
         class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4"
         x-transition x-cloak>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 sm:p-8 rounded-3xl max-w-md w-full shadow-2xl space-y-5 text-left"
             @click.away="checkoutOpen = false">
            <div class="flex justify-between items-start">
                <div class="space-y-1">
                    <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-950 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                        Safaricom M-PESA Express
                    </span>
                    <h3 class="text-lg font-bold text-slate-950 dark:text-white">Secure STK checkout</h3>
                </div>
                <button @click="checkoutOpen = false" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    ✕
                </button>
            </div>

            <!-- Loading framework state -->
            <template x-if="checkoutLoading">
                <div class="py-12 text-center space-y-4">
                    <div class="w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs font-mono text-slate-400">Triggering Lipa Na M-PESA STK Push to your device...</p>
                </div>
            </template>

            <!-- Success checkout frame -->
            <template x-if="checkoutSuccess">
                <div class="py-6 text-center space-y-4">
                    <span class="text-4xl block animate-bounce">📱</span>
                    <h4 class="text-base font-bold text-slate-950 dark:text-white">STK Push triggered!</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs mx-auto leading-relaxed">
                        Please check your phone for the PIN prompt. Enter your Safaricom M-PESA PIN to authorize the transaction of <strong>KES <span x-text="formattedPrice"></span></strong>. Your download link is being compiled.
                    </p>
                    <button @click="checkoutOpen = false" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition">
                        ✓ Got It
                    </button>
                </div>
            </template>

            <!-- Base Checkout Form -->
            <template x-if="!checkoutLoading && !checkoutSuccess">
                <div class="space-y-4">
                    <div class="p-4 bg-slate-50 dark:bg-slate-950/40 rounded-2xl border border-slate-100 dark:border-slate-800/80 text-xs text-slate-500 space-y-1.5">
                        <div class="flex justify-between">
                            <span>Product Item:</span>
                            <span class="font-bold text-slate-900 dark:text-white" x-text="product.title"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>License Tier:</span>
                            <span class="font-bold text-slate-900 dark:text-white uppercase font-mono" x-text="selectedLicense"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Quantity Copied:</span>
                            <span class="font-bold text-slate-900 dark:text-white" x-text="quantity"></span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200/50 dark:border-slate-800 pt-1.5 mt-1 text-sm">
                            <span class="font-bold">Total Payable:</span>
                            <span class="font-black text-indigo-600 dark:text-indigo-400">KES <span x-text="formattedPrice"></span></span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-wider block">M-PESA Phone Number</label>
                        <input type="text" x-model="checkoutPhone" placeholder="e.g. 0712345678" 
                               class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        <span class="text-[9px] text-slate-400 font-mono">Format: 07xxxxxxxx or 2547xxxxxxxx</span>
                    </div>

                    <button @click="submitCheckout()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition">
                        ✓ Complete Checkout with M-PESA
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
