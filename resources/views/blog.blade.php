@extends('layouts.public')

@section('title', 'Knowledge Center & Technical Blog — JUANET')
@section('meta_description', 'Discover insights on Laravel web development, M-PESA API integrations, SEO strategies for African businesses, and pricing websites in Kenya.')

@section('content')
<div class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300" x-data="{ activeArticle: null }">
    <!-- Ambient mesh overlay -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-5%] left-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[-5%] right-[-10%] w-[50%] h-[50%] bg-violet-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Title -->
        <x-section-title 
            badge="Knowledge Platform"
            title="Insights, Engineering &amp; Strategy"
            subtitle="Deep dives, regional software pricing guides, and technical breakdowns written by senior builders in East Africa."
        />

        <!-- Main Blog Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mt-12">
            <!-- Left Side: Articles list -->
            <div class="lg:col-span-8 space-y-10">
                
                <!-- Article 1 -->
                <article class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xs hover:shadow-md transition text-left space-y-6">
                    <div class="h-56 rounded-2xl bg-gradient-to-tr from-slate-950 to-indigo-900 border border-indigo-500/10 p-6 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 text-9xl opacity-10">🇰🇪</div>
                        <span class="text-[10px] font-mono font-bold text-indigo-400 bg-indigo-950/80 px-2.5 py-1 rounded uppercase self-start">Business Strategy</span>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-mono">Market Rates 2026</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white font-display mt-1">How Much Does a Website Cost in Kenya?</h2>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-400 font-mono">
                        <div class="flex items-center gap-1.5">
                            <span class="h-5 w-5 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-[10px]">👤</span>
                            <span class="font-bold text-slate-600 dark:text-slate-300">Josphat Juan</span>
                        </div>
                        <span>&bull;</span>
                        <span>Published: July 2, 2026</span>
                        <span>&bull;</span>
                        <span>7 min read</span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        An honest, comprehensive cost breakdown of web engineering in East Africa. Learn the difference between low-cost template builders and bespoke, high-performance Laravel/React container systems. Understand web design fees, SEO options, domain hosts, and Lipa na M-PESA setups.
                    </p>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                        <button @click="activeArticle = { 
                            title: 'How Much Does a Website Cost in Kenya?', 
                            author: 'Josphat Juan &bull; Founder, Kijiji Digital', 
                            date: 'July 2, 2026', 
                            content: 'The web development landscape in Kenya has matured significantly. Low-cost builders often utilize templates that fail in page loading speeds, leading to extremely high bounce rates and poor Google Search visibility. For simple marketing web systems, prices range between KES 45,000 to KES 120,000. Bespoke, secure full-stack enterprise portals built using custom frameworks (like Laravel, React, and Supabase) start from KES 250,000 upwards due to complex billing callback routers, granular multi-workspace isolation, and API security compliance. Investing in customized cloud architectures reduces long-term server overhead by up to 60% and increases lead conversions.' 
                        }" class="inline-flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:translate-x-1 transition-transform cursor-pointer">
                            Continue Reading &rarr;
                        </button>
                    </div>
                </article>

                <!-- Article 2 -->
                <article class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xs hover:shadow-md transition text-left space-y-6">
                    <div class="h-56 rounded-2xl bg-gradient-to-tr from-slate-950 to-violet-900 border border-violet-500/10 p-6 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 text-9xl opacity-10">💻</div>
                        <span class="text-[10px] font-mono font-bold text-violet-400 bg-violet-950/80 px-2.5 py-1 rounded uppercase self-start">Engineering</span>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-mono">Framework Battles</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white font-display mt-1">Laravel vs WordPress: Which is Best for SaaS?</h2>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-400 font-mono">
                        <div class="flex items-center gap-1.5">
                            <span class="h-5 w-5 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-[10px]">👤</span>
                            <span class="font-bold text-slate-600 dark:text-slate-300">Wambui Kamau</span>
                        </div>
                        <span>&bull;</span>
                        <span>Published: June 28, 2026</span>
                        <span>&bull;</span>
                        <span>9 min read</span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        Comparing architectural platforms for subscription billing and data isolation. Why WordPress is great for static content but fails to scale securely for modern multi-tenant SaaS products requiring custom client permissions.
                    </p>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                        <button @click="activeArticle = { 
                            title: 'Laravel vs WordPress: Which is Best for SaaS?', 
                            author: 'Wambui Kamau &bull; Principal Software Engineer', 
                            date: 'June 28, 2026', 
                            content: 'When planning a multi-tenant SaaS platform, WordPress is fundamentally the wrong architectural foundation. While WordPress has a rich blogging ecosystem, its relational database structures and reliance on third-party plugins lead to substantial security vulnerabilities and database bloat. Laravel, on the other hand, provides native middleware routing, robust Eloquent ORM relationships, built-in queue workers for background processing, and complete cryptographic security libraries. Building with Laravel guarantees secure isolation of customer tenant registers and sub-second operational queries.' 
                        }" class="inline-flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:translate-x-1 transition-transform cursor-pointer">
                            Continue Reading &rarr;
                        </button>
                    </div>
                </article>

                <!-- Article 3 -->
                <article class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xs hover:shadow-md transition text-left space-y-6">
                    <div class="h-56 rounded-2xl bg-gradient-to-tr from-slate-950 to-emerald-900 border border-emerald-500/10 p-6 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 text-9xl opacity-10">📲</div>
                        <span class="text-[10px] font-mono font-bold text-emerald-400 bg-emerald-950/80 px-2.5 py-1 rounded uppercase self-start">Integrations</span>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-mono">Daraja API v2</span>
                            <h2 class="text-xl sm:text-2xl font-black text-white font-display mt-1">Integrating M-PESA into Laravel Safely</h2>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-400 font-mono">
                        <div class="flex items-center gap-1.5">
                            <span class="h-5 w-5 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-[10px]">👤</span>
                            <span class="font-bold text-slate-600 dark:text-slate-300">Faraji Mwenda</span>
                        </div>
                        <span>&bull;</span>
                        <span>Published: June 15, 2026</span>
                        <span>&bull;</span>
                        <span>12 min read</span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        A bulletproof developer guide to setting up Lipa Na M-PESA STK Push triggers. Learn how to configure Laravel routes for secure Safaricom callbacks, validate incoming transaction signatures, and update billing ledgers instantly.
                    </p>
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                        <button @click="activeArticle = { 
                            title: 'Integrating M-PESA into Laravel Safely', 
                            author: 'Faraji Mwenda &bull; Integration Specialist', 
                            date: 'June 15, 2026', 
                            content: 'Integrating Safaricom\'s Daraja API requires a deep understanding of callback security. Developers must create specific, CSRF-exempt webhook endpoints in Laravel routes to receive transaction confirmations from Safaricom servers. The callback data must be cryptographically verified to ensure it matches the original STK request dispatched by your server. Once validated, the system must perform atomic transactional updates in the PostgreSQL database to avoid race conditions. Emitting immediate events to synchronize CRM logs and notify users guarantees a pristine experience.' 
                        }" class="inline-flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:translate-x-1 transition-transform cursor-pointer">
                            Continue Reading &rarr;
                        </button>
                    </div>
                </article>

            </div>

            <!-- Right Side: Sidebar, Newsletter Signup & Categories -->
            <div class="lg:col-span-4 space-y-8">
                <!-- Newsletter Signup -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 rounded-3xl text-left space-y-4"
                     x-data="{ email: '', subscribed: false, subscribeNewsletter() {
                        if(!this.email) {
                            $dispatch('trigger-toast', { message: '⚠ Please provide your email.', type: 'error' });
                            return;
                        }
                        this.subscribed = true;
                        $dispatch('trigger-toast', { message: '✓ Thank you! Registered for weekly knowledge digests.', type: 'success' });
                     } }">
                    <span class="text-lg">📬</span>
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Subscribe to Knowledge Center</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Receive weekly, high-contrast engineering briefings on SaaS frameworks, M-PESA updates, and digital marketing strategies.</p>
                    
                    <div x-show="subscribed" class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-950/50 p-3 rounded-xl text-center" x-cloak>
                        <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">Successfully Subscribed!</span>
                    </div>

                    <div x-show="!subscribed" class="space-y-3">
                        <input type="email" x-model="email" placeholder="john@example.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        <button type="button" @click="subscribeNewsletter()" class="w-full inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold py-2.5 transition cursor-pointer">Subscribe Digest</button>
                    </div>
                </div>

                <!-- Categories -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-6 rounded-3xl text-left space-y-4">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Categories</h4>
                    <div class="space-y-2 font-mono text-[11px] text-slate-500">
                        <div class="flex justify-between items-center hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer"><span>Development (Laravel, React)</span><span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-bold">14</span></div>
                        <div class="flex justify-between items-center hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer"><span>Integrations &amp; API (M-PESA)</span><span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-bold">8</span></div>
                        <div class="flex justify-between items-center hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer"><span>Business Strategy &amp; Pricing</span><span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-bold">5</span></div>
                        <div class="flex justify-between items-center hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer"><span>AI &amp; Automation Triggers</span><span class="bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-bold">4</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popover Reading Drawer Modal -->
        <div x-show="activeArticle" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-slate-950/60 dark:bg-slate-950/80 backdrop-blur-sm transition-opacity" @click="activeArticle = null"></div>

                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl p-6 sm:p-8">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                        <span class="text-[10px] font-mono font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 px-2.5 py-1 rounded uppercase" x-text="activeArticle?.date"></span>
                        <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-display mt-2 leading-tight" x-text="activeArticle?.title"></h3>
                        <p class="text-[11px] text-slate-400 font-mono mt-1" x-text="activeArticle?.author"></p>
                    </div>
                    <div class="mt-6 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed space-y-4">
                        <p x-text="activeArticle?.content"></p>
                        <p class="font-bold text-slate-900 dark:text-white">To continue reading advanced engineering articles, subscribe to our knowledge newsletter or follow our weekly publications on development frameworks.</p>
                    </div>
                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button @click="activeArticle = null" class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">Close Article</button>
                        <a href="/quote-request" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-500 transition shadow-sm">Talk to an Expert</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<x-toast />
@endsection
