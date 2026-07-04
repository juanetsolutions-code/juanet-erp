@extends('layouts.public')

@section('title', 'Digital Products & Code Marketplace — JUANET')
@section('meta_description', 'Discover premium pre-built website templates, enterprise Laravel starter kits, Tailwind admin dashboards, design systems, and developer prompt resources.')

@section('content')
<div class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300" x-data="{ activePreviewItem: null }">
    <!-- Ambient mesh overlay -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-5%] right-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[-5%] left-[-10%] w-[50%] h-[50%] bg-emerald-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Title -->
        <x-section-title 
            badge="Digital Catalog"
            title="Premium Digital Products &amp; Software Templates"
            subtitle="Boost your engineering velocity and brand positioning with pre-built Laravel starter kits, UI collections, source code, and comprehensive identity systems."
        />

        <!-- Filter Sub-header -->
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/60 dark:border-slate-800/80 pb-6 gap-4">
            <div class="flex flex-wrap gap-2 text-xs font-mono font-bold uppercase tracking-wider text-slate-500">
                <span class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg">All Assets</span>
                <span class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-lg hover:border-slate-300 dark:hover:border-slate-700 transition cursor-pointer">Starter Kits</span>
                <span class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-lg hover:border-slate-300 dark:hover:border-slate-700 transition cursor-pointer">UI Kits</span>
                <span class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-lg hover:border-slate-300 dark:hover:border-slate-700 transition cursor-pointer">Brand Packs</span>
            </div>
            <div class="text-[11px] text-slate-400 font-mono">10 digital product structures pre-compiled for Phase F2 release</div>
        </div>

        <!-- Marketplace grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">
            
            <!-- Card 1: Laravel Starter Kits -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition text-left flex flex-col justify-between group">
                <div class="space-y-4">
                    <!-- Gradient Cover Image Placeholder -->
                    <div class="h-44 rounded-xl bg-gradient-to-br from-red-500/20 via-orange-500/10 to-transparent border border-orange-500/10 flex flex-col justify-between p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15">🔴</div>
                        <span class="text-[10px] font-mono font-bold text-red-500 bg-red-500/10 px-2 py-0.5 rounded uppercase self-start">Starter Kit</span>
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 font-mono">JUANET Boilerplate</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight font-display">Laravel 12 &amp; Daraja SaaS</h4>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold uppercase text-slate-400">Software Template</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                <span>★★★★★</span>
                                <span class="text-slate-400 font-mono text-[10px] ml-1">(4.9)</span>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1">Laravel Ultimate Starter Kit</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed line-clamp-2">Complete setup with authentication, isolated tenant workspaces, and real-time M-PESA STK Push listeners built in.</p>
                    </div>
                </div>
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-mono">Instant Download</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">KES 14,500</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="activePreviewItem = { title: 'Laravel Ultimate Starter Kit', desc: 'A production-ready SaaS boilerplate. Contains multi-tenant workspaces, integrated Jetstream, dark mode support out of the box, and structured Daraja API listeners for instant callbacks.', price: 'KES 14,500', tech: 'Laravel 12, AlpineJS, Tailwind, PostgreSQL' }" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer">Preview</button>
                        <button @click="$dispatch('trigger-toast', { message: '✓ Ultimate Starter Kit is preparing for checkout. Registry active.', type: 'info' })" class="px-3.5 py-2 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition cursor-pointer">Buy Now</button>
                    </div>
                </div>
            </div>

            <!-- Card 2: Website Templates -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition text-left flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="h-44 rounded-xl bg-gradient-to-br from-indigo-500/20 via-blue-500/10 to-transparent border border-indigo-500/10 flex flex-col justify-between p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15">💻</div>
                        <span class="text-[10px] font-mono font-bold text-indigo-500 bg-indigo-500/10 px-2 py-0.5 rounded uppercase self-start">Templates</span>
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 font-mono">Modern Portfolio DX</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight font-display">Agency Display Canvas</h4>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold uppercase text-slate-400">Marketing Pack</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                <span>★★★★★</span>
                                <span class="text-slate-400 font-mono text-[10px] ml-1">(4.8)</span>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1">SaaS Agency Website Template</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed line-clamp-2">Stunning high-contrast landing page. Fully optimized for dark mode toggle, search engines, and lightning-fast loading speeds.</p>
                    </div>
                </div>
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-mono">Instant Download</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">KES 4,500</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="activePreviewItem = { title: 'SaaS Agency Website Template', desc: 'A minimalist landing page template designed specifically for tech consulting and agency portfolios. Fully modular HTML/CSS styled using pure Tailwind utility grids.', price: 'KES 4,500', tech: 'Tailwind CSS, Alpine.js, HTML5, SEO Canonical' }" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer">Preview</button>
                        <button @click="$dispatch('trigger-toast', { message: '✓ Agency template preparing for checkout.', type: 'info' })" class="px-3.5 py-2 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition cursor-pointer">Buy Now</button>
                    </div>
                </div>
            </div>

            <!-- Card 3: Admin Dashboards -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition text-left flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="h-44 rounded-xl bg-gradient-to-br from-violet-500/20 via-pink-500/10 to-transparent border border-violet-500/10 flex flex-col justify-between p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15">📊</div>
                        <span class="text-[10px] font-mono font-bold text-violet-500 bg-violet-500/10 px-2 py-0.5 rounded uppercase self-start">Dashboard</span>
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 font-mono">Admin Console OS</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight font-display">Bento Grid Console</h4>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold uppercase text-slate-400">UI / UX</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                <span>★★★★★</span>
                                <span class="text-slate-400 font-mono text-[10px] ml-1">(5.0)</span>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1">Apex Admin Dashboard Panel</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed line-clamp-2">Complete set of beautiful CRM charts, transaction histories, file drawers, and notification modules styled using clean Tailwind CSS.</p>
                    </div>
                </div>
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-mono">Instant Download</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">KES 8,000</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="activePreviewItem = { title: 'Apex Admin Dashboard Panel', desc: 'Bento-grid styled control center. Includes pre-wired charts using Recharts/D3 and responsive sidebar navigation built natively with Alpine transitions.', price: 'KES 8,000', tech: 'React, Tailwind CSS, AlpineJS, D3.js' }" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer">Preview</button>
                        <button @click="$dispatch('trigger-toast', { message: '✓ Dashboard panel preparing for checkout.', type: 'info' })" class="px-3.5 py-2 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition cursor-pointer">Buy Now</button>
                    </div>
                </div>
            </div>

            <!-- Card 4: Brand Identity Packs -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition text-left flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="h-44 rounded-xl bg-gradient-to-br from-teal-500/20 via-emerald-500/10 to-transparent border border-teal-500/10 flex flex-col justify-between p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15">✒️</div>
                        <span class="text-[10px] font-mono font-bold text-teal-500 bg-teal-500/10 px-2 py-0.5 rounded uppercase self-start">Branding</span>
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 font-mono">Corporate Identity</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight font-display">Modern Brand Deck</h4>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold uppercase text-slate-400">Design Asset</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                <span>★★★★☆</span>
                                <span class="text-slate-400 font-mono text-[10px] ml-1">(4.6)</span>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1">Brand Identity Packs &amp; Logos</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed line-clamp-2">Complete vector-based corporate guidelines, logo collections, premium color sheets, typography sheets, and printable business deck cards.</p>
                    </div>
                </div>
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-mono">Instant Download</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">KES 6,000</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="activePreviewItem = { title: 'Brand Identity Packs & Logos', desc: 'Vector brand guide. Includes SVG logo collections, high-resolution guidelines, custom slide decks, business card vectors, and email newsletter layouts.', price: 'KES 6,000', tech: 'Adobe Illustrator, SVG, Figma, PDF' }" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer">Preview</button>
                        <button @click="$dispatch('trigger-toast', { message: '✓ Brand packs preparing for checkout.', type: 'info' })" class="px-3.5 py-2 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition cursor-pointer">Buy Now</button>
                    </div>
                </div>
            </div>

            <!-- Card 5: Prompt Libraries -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition text-left flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="h-44 rounded-xl bg-gradient-to-br from-emerald-500/20 via-blue-500/10 to-transparent border border-emerald-500/10 flex flex-col justify-between p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15">🤖</div>
                        <span class="text-[10px] font-mono font-bold text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded uppercase self-start">AI Prompts</span>
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 font-mono">Cognitive Pipeline</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight font-display">Gemini Integration Guide</h4>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold uppercase text-slate-400">AI / Tech</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                <span>★★★★★</span>
                                <span class="text-slate-400 font-mono text-[10px] ml-1">(4.9)</span>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1">Prompt Libraries &amp; AI Workflows</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed line-clamp-2">Engineered prompt chains and systemic structures built specifically for Gemini models to analyze client emails, format CRM databases, and trigger callbacks.</p>
                    </div>
                </div>
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-mono">Instant Download</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">KES 3,200</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="activePreviewItem = { title: 'Prompt Libraries & AI Workflows', desc: 'Pre-formatted JSON prompt architectures for Google Gemini API integration. Includes systems for context caching, diagnostic testing, and chat logs routing.', price: 'KES 3,200', tech: 'JSON structure, Prompt Chains, Gemini API SDK' }" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer">Preview</button>
                        <button @click="$dispatch('trigger-toast', { message: '✓ Prompt libraries preparing for checkout.', type: 'info' })" class="px-3.5 py-2 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition cursor-pointer">Buy Now</button>
                    </div>
                </div>
            </div>

            <!-- Card 6: UI Kits -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition text-left flex flex-col justify-between group">
                <div class="space-y-4">
                    <div class="h-44 rounded-xl bg-gradient-to-br from-pink-500/20 via-orange-500/10 to-transparent border border-pink-500/10 flex flex-col justify-between p-4 relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 text-7xl opacity-15">📐</div>
                        <span class="text-[10px] font-mono font-bold text-pink-500 bg-pink-500/10 px-2 py-0.5 rounded uppercase self-start">UI Kits</span>
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-slate-400 font-mono">Bento Elements</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight font-display">Vector Web Components</h4>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-mono font-bold uppercase text-slate-400">Design Kit</span>
                            <div class="flex items-center text-amber-400 text-xs">
                                <span>★★★★★</span>
                                <span class="text-slate-400 font-mono text-[10px] ml-1">(4.7)</span>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mt-1">Premium Figma UI Kit</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed line-clamp-2">Hundreds of premium component structures (cards, navigation blocks, modal popups, data lists) styled natively for custom SaaS platforms.</p>
                    </div>
                </div>
                <div class="pt-6 border-t border-slate-100 dark:border-slate-800/80 mt-6 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-mono">Instant Download</span>
                        <span class="text-sm font-black text-slate-900 dark:text-white">KES 5,000</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="activePreviewItem = { title: 'Premium Figma UI Kit', desc: 'A meticulously designed Figma component library featuring auto-layout v5, dark/light variations, custom bento sections, and complete dashboard frameworks.', price: 'KES 5,000', tech: 'Figma Auto-Layout 5.0, Custom Tokens' }" class="px-3 py-2 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-xl transition cursor-pointer">Preview</button>
                        <button @click="$dispatch('trigger-toast', { message: '✓ Figma UI Kit preparing for checkout.', type: 'info' })" class="px-3.5 py-2 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-xl transition cursor-pointer">Buy Now</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Popover Preview Panel Modal -->
        <div x-show="activePreviewItem" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <!-- Overlay -->
                <div class="fixed inset-0 bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="activePreviewItem = null"></div>

                <!-- Modal box -->
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white font-display" x-text="activePreviewItem?.title"></h4>
                        <button @click="activePreviewItem = null" class="text-slate-400 hover:text-slate-500 font-mono text-xs cursor-pointer">Close ✕</button>
                    </div>
                    <div class="mt-4 space-y-4">
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed" x-text="activePreviewItem?.desc"></p>
                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                            <div>
                                <span class="text-[10px] text-slate-400 block uppercase font-mono">Pricing</span>
                                <span class="font-bold text-slate-800 dark:text-white" x-text="activePreviewItem?.price"></span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 block uppercase font-mono">Technology</span>
                                <span class="font-bold text-slate-800 dark:text-white" x-text="activePreviewItem?.tech"></span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button @click="activePreviewItem = null" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">Cancel</button>
                        <button @click="activePreviewItem = null; $dispatch('trigger-toast', { message: '✓ Product locked for download.', type: 'success' })" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500 transition cursor-pointer">Add to Workspace</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Callout Banner -->
        <div class="mt-20 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-8 md:p-12 shadow-premium dark:shadow-premium-dark text-center max-w-4xl mx-auto"
             x-data="{ email: '', registered: false, registering: false, submitNewsletter() {
                if(!this.email) {
                    $dispatch('trigger-toast', { message: '⚠ Please fill in your email.', type: 'error' });
                    return;
                }
                this.registering = true;
                setTimeout(() => {
                    this.registering = false;
                    this.registered = true;
                    $dispatch('trigger-toast', { message: '✓ Registered for early digital marketplace access!', type: 'success' });
                }, 1200);
             } }">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/20">
                Seller &amp; Affiliate Network
            </span>
            <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display mt-4">Want to Sell Your Digital Products?</h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-2 leading-relaxed max-w-2xl mx-auto">
                We are opening our regional marketplace to local creators, design leads, and Laravel specialists. Register your developer profile below to receive early documentation parameters and alpha workspace invites.
            </p>
            
            <div x-show="registered" class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-950/50 p-4 rounded-2xl max-w-lg mx-auto mt-8 text-center" x-cloak>
                <h4 class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Early Access Invite Dispatched!</h4>
                <p class="text-xs text-slate-500 mt-1">We have logged <span class="font-bold text-slate-700 dark:text-slate-300" x-text="email"></span> in our early registry pipeline. A tech lead from Nairobi HQ will connect shortly.</p>
            </div>

            <div x-show="!registered" class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto mt-8">
                <input type="email" x-model="email" placeholder="E.g., dev@company.com" class="flex-grow bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                <button type="button" @click="submitNewsletter()" :disabled="registering" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-6 py-3 shadow-sm hover:-translate-y-0.5 transition cursor-pointer shrink-0">
                    <span x-show="!registering">Join Developer Alpha</span>
                    <span x-show="registering">Registering...</span>
                </button>
            </div>
        </div>

    </div>
</div>

<x-toast />
@endsection
