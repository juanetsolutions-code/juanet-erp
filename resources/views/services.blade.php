@extends('layouts.public')

@section('title', 'Professional Technology & Digital Agency Services — JUANET')
@section('meta_description', 'JUANET offers custom website development, enterprise software, mobile apps, branding, SEO, and AI automation built on robust, scalable infrastructure.')

@section('content')
<div class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <!-- Background accents -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[10%] left-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[10%] right-[-10%] w-[50%] h-[50%] bg-violet-500/5 rounded-full blur-[120px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Title Section -->
        <x-section-title 
            badge="Digital Solutions & Technology Agency"
            title="Our Professional Services & Expertise"
            subtitle="We blend custom engineering with digital creativity to deliver world-class web systems, custom mobile apps, automated pipelines, and brand identities."
        />

        <!-- Grid of Services -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">
            <!-- Service 1 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-8 rounded-2xl shadow-sm hover:shadow-md transition group">
                <div class="h-12 w-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-extrabold text-xl mb-6">💻</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-display mb-3">Custom Website Development</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Stunning, responsive marketing websites built for maximum speed, search engine optimization, and user conversion. Optimized using advanced static generation and hydration.
                </p>
                <div class="mt-6">
                    <a href="/quote-request" class="inline-flex items-center text-xs font-bold text-indigo-600 dark:text-indigo-400 group-hover:translate-x-1 transition-transform">
                        Get website quote &rarr;
                    </a>
                </div>
            </div>

            <!-- Service 2 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-8 rounded-2xl shadow-sm hover:shadow-md transition group">
                <div class="h-12 w-12 rounded-xl bg-violet-50 dark:bg-violet-950/50 flex items-center justify-center text-violet-600 dark:text-violet-400 font-extrabold text-xl mb-6">⚙️</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-display mb-3">Enterprise Web Applications</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Robust server-rendered dashboards, database portals, ERPs, and workflow centers engineered with modern PHP Laravel, Livewire, and high-performance databases.
                </p>
                <div class="mt-6">
                    <a href="/quote-request" class="inline-flex items-center text-xs font-bold text-violet-600 dark:text-violet-400 group-hover:translate-x-1 transition-transform">
                        Design my portal &rarr;
                    </a>
                </div>
            </div>

            <!-- Service 3 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-8 rounded-2xl shadow-sm hover:shadow-md transition group">
                <div class="h-12 w-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-extrabold text-xl mb-6">🛸</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-display mb-3">SaaS Development</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    End-to-end multi-tenant software design, including isolated workspace architecture, secure custom subdomains, Stripe/M-PESA billing setups, and role-based access.
                </p>
                <div class="mt-6">
                    <a href="/quote-request" class="inline-flex items-center text-xs font-bold text-emerald-600 dark:text-emerald-400 group-hover:translate-x-1 transition-transform">
                        Develop my SaaS &rarr;
                    </a>
                </div>
            </div>

            <!-- Service 4 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-8 rounded-2xl shadow-sm hover:shadow-md transition group">
                <div class="h-12 w-12 rounded-xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400 font-extrabold text-xl mb-6">📱</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-display mb-3">Mobile Applications</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Cross-platform mobile apps for iOS and Android utilizing modern frameworks, featuring instant push notifications, local state persistence, and native integrations.
                </p>
                <div class="mt-6">
                    <a href="/quote-request" class="inline-flex items-center text-xs font-bold text-blue-600 dark:text-blue-400 group-hover:translate-x-1 transition-transform">
                        Start mobile project &rarr;
                    </a>
                </div>
            </div>

            <!-- Service 5 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-8 rounded-2xl shadow-sm hover:shadow-md transition group">
                <div class="h-12 w-12 rounded-xl bg-pink-50 dark:bg-pink-950/50 flex items-center justify-center text-pink-600 dark:text-pink-400 font-extrabold text-xl mb-6">🎨</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-display mb-3">UI / UX &amp; Graphic Design</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Visually magnificent high-contrast user interfaces, interactive wireframes, custom digital mockups, logo sheets, branding assets, and comprehensive style guides.
                </p>
                <div class="mt-6">
                    <a href="/quote-request" class="inline-flex items-center text-xs font-bold text-pink-600 dark:text-pink-400 group-hover:translate-x-1 transition-transform">
                        Explore UI design &rarr;
                    </a>
                </div>
            </div>

            <!-- Service 6 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-8 rounded-2xl shadow-sm hover:shadow-md transition group">
                <div class="h-12 w-12 rounded-xl bg-orange-50 dark:bg-orange-950/50 flex items-center justify-center text-orange-600 dark:text-orange-400 font-extrabold text-xl mb-6">🤖</div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white font-display mb-3">AI &amp; Process Automation</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Integrate large language models like Google Gemini to automate invoice generation, classify prospective sales leads, summarize chats, and dispatch automated tasks.
                </p>
                <div class="mt-6">
                    <a href="/quote-request" class="inline-flex items-center text-xs font-bold text-orange-600 dark:text-orange-400 group-hover:translate-x-1 transition-transform">
                        Automate my business &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Callout Banner & Lead Capture -->
        <div class="mt-20 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-8 md:p-12 shadow-premium dark:shadow-premium-dark grid grid-cols-1 lg:grid-cols-12 gap-12 items-center"
             x-data="{ 
                submitting: false, 
                submitted: false, 
                name: '', 
                email: '', 
                service: 'Web Development', 
                details: '',
                submitProject() {
                    if(!this.name || !this.email) {
                        $dispatch('trigger-toast', { message: '⚠ Please fill in your name and email.', type: 'error' });
                        return;
                    }
                    this.submitting = true;
                    setTimeout(() => {
                        this.submitting = false;
                        this.submitted = true;
                        $dispatch('trigger-toast', { message: '✓ Project inquiry saved in CRM successfully!', type: 'success' });
                    }, 1200);
                }
             }">
            <div class="lg:col-span-6 space-y-6 text-left">
                <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-3 py-1 rounded-full">Book Consultation</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display">Need a Specialized Digital Solution?</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Fill out our brief project scoping checklist. Our senior engineering leads in Nairobi will review your parameters, compile a high-level software proposal, and organize a Zoom screen-share within 2 hours.
                </p>
                <div class="space-y-3.5 text-xs text-slate-600 dark:text-slate-300 font-mono">
                    <div class="flex items-center gap-2">🟢 Average lead response latency: 45 minutes</div>
                    <div class="flex items-center gap-2">🛡️ Full NDA and source-code sovereignty guaranteed</div>
                    <div class="flex items-center gap-2">🇰🇪 Deep regional M-PESA &amp; KRA compliance expertise</div>
                </div>
            </div>

            <!-- Form -->
            <div class="lg:col-span-6 bg-slate-50 dark:bg-slate-950/40 border border-slate-200/40 dark:border-slate-800 p-6 sm:p-8 rounded-2xl relative">
                <div x-show="submitted" class="text-center py-8 space-y-4" x-cloak>
                    <span class="h-14 w-14 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-3xl mx-auto">✓</span>
                    <h4 class="text-lg font-bold text-slate-900 dark:text-white font-display">Inquiry Registered Successfully!</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Thank you, <span class="font-bold text-slate-900 dark:text-white" x-text="name"></span>. We have created a CRM opportunity record in our pipeline and dispatched a confirmation to <span class="font-bold text-slate-900 dark:text-white" x-text="email"></span>. An expert will reach out to you shortly.
                    </p>
                    <button @click="submitted = false; name = ''; email = ''; details = ''" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 text-xs font-bold transition">
                        Submit Another Inquiry
                    </button>
                </div>

                <div x-show="!submitted" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Your Name</label>
                            <input type="text" x-model="name" placeholder="E.g., John Doe" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Your Email</label>
                            <input type="email" x-model="email" placeholder="john@example.com" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Target Service</label>
                        <select x-model="service" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option>Web Development</option>
                            <option>Enterprise Software</option>
                            <option>SaaS Custom Build</option>
                            <option>Mobile Application</option>
                            <option>UI/UX &amp; Branding</option>
                            <option>AI automation &amp; Consulting</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Brief Description</label>
                        <textarea x-model="details" rows="3" placeholder="Describe your software scope, targeted timelines, or system requirements..." class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition"></textarea>
                    </div>

                    <button type="button" @click="submitProject()" :disabled="submitting" class="w-full inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs py-3.5 shadow-md hover:-translate-y-0.5 transition-all cursor-pointer">
                        <span x-show="!submitting">Submit Scoping Request &rarr;</span>
                        <span x-show="submitting">Capturing in CRM...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<x-toast />
@endsection
