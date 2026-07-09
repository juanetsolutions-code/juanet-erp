@extends('layouts.public')

@section('title', 'JUANET — The Enterprise Platform for Growing Businesses')
@section('meta_description', 'Unify CRM, digital marketplaces, project trackers, finance ledgers, custom CMS, client support, and AI automation into a single, high-performance enterprise platform.')

@section('hero')
<div class="relative min-h-screen pt-24 pb-16 flex flex-col justify-center overflow-hidden bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-50 transition-colors duration-300">
    <!-- Ambient animated mesh glow -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-gradient-to-tr from-indigo-500/10 to-violet-500/10 dark:from-indigo-500/10 dark:to-violet-500/5 rounded-full blur-[140px] animate-pulse" style="animation-duration: 8s;"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-gradient-to-br from-emerald-500/10 to-teal-500/10 dark:from-emerald-500/5 dark:to-teal-500/5 rounded-full blur-[120px] animate-pulse" style="animation-duration: 12s;"></div>
        <!-- Grid pattern overlay -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:14px_24px] dark:bg-[linear-gradient(to_right,#ffffff05_1px,transparent_1px),linear-gradient(to_bottom,#ffffff05_1px,transparent_1px)]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            
            <!-- Hero Content -->
            <div class="lg:col-span-7 space-y-8 text-left">
                <!-- Premium Pill Badge -->
                <span class="inline-flex items-center gap-x-2 rounded-full bg-indigo-50/80 px-3.5 py-1.5 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 border border-indigo-200/30 dark:border-indigo-800/40 shadow-sm backdrop-blur-sm animate-fade-in">
                    <span class="h-2 w-2 rounded-full bg-indigo-600 dark:bg-indigo-400 animate-pulse"></span>
                    <span class="tracking-wide uppercase font-display text-[10px]">Phase F2 Production Release</span>
                </span>

                <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black font-display tracking-tight text-slate-900 dark:text-white leading-[1.05] drop-shadow-sm">
                    The Enterprise Platform <br class="hidden sm:inline" />
                    <span class="bg-gradient-to-r from-indigo-600 via-violet-600 to-indigo-600 bg-clip-text text-transparent dark:from-indigo-400 dark:via-violet-400 dark:to-indigo-300">
                        for Growing Businesses.
                    </span>
                </h1>

                <p class="text-base sm:text-lg md:text-xl text-slate-600 dark:text-slate-400 font-light leading-relaxed max-w-2xl">
                    JUANET unifies CRM pipelines, digital marketplaces, team project tracking, M-PESA-integrated financial ledgers, robust CMS portfolios, SLA support, and secure AI-driven automations. Built from the ground up for high-performance scale.
                </p>

                <!-- Hero CTA Buttons -->
                <div class="flex flex-wrap gap-4 items-center">
                    <a href="/register" class="group inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg hover:bg-indigo-500 hover:-translate-y-0.5 transition-all duration-200">
                        Get Started
                        <svg class="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                    <button @click="document.getElementById('final-cta').scrollIntoView({ behavior: 'smooth' })" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-200/80 px-6 py-3.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 hover:border-slate-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800/80 hover:-translate-y-0.5 transition-all duration-200">
                        Book a Live Demo
                    </button>
                </div>

                <!-- Small info details -->
                <div class="flex flex-wrap gap-x-8 gap-y-2 pt-4 text-xs text-slate-400 dark:text-slate-500 font-mono">
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        No credit card required
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        14-day premium sandbox
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Local Daraja Integration ready
                    </div>
                </div>
            </div>

            <!-- Floating Glassmorphism Hero Graphic Card Grid -->
            <div class="lg:col-span-5 relative w-full flex items-center justify-center lg:justify-end">
                <div class="relative w-full max-w-[420px] aspect-square flex items-center justify-center">
                    
                    <!-- Decorative background ring -->
                    <div class="absolute w-[120%] h-[120%] bg-indigo-500/5 dark:bg-indigo-500/5 rounded-full border border-indigo-500/10 animate-spin" style="animation-duration: 40s;"></div>
                    <div class="absolute w-[80%] h-[80%] bg-violet-500/5 dark:bg-violet-500/5 rounded-full border border-violet-500/5 border-dashed"></div>

                    <!-- Main center card: Core platform stats -->
                    <div class="absolute z-20 w-[90%] bg-white/70 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200/50 dark:border-slate-800/80 p-6 rounded-2xl shadow-premium dark:shadow-premium-dark hover:scale-[1.02] transition-transform duration-300">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="h-6 w-6 rounded-md bg-indigo-600 flex items-center justify-center text-white text-[11px] font-black">J</span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-900 dark:text-white font-display">JUANET OS</span>
                            </div>
                            <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 px-2 py-0.5 rounded-full font-bold">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live State
                            </span>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">Q2 Platform Throughput</span>
                                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display mt-0.5">KES 14,892,400</div>
                            </div>
                            <!-- Styled Mini Chart bar representation -->
                            <div class="space-y-1.5">
                                <div class="flex justify-between text-[10px] text-slate-400 font-mono">
                                    <span>DAR_LNM_SUCCESS</span>
                                    <span>98.6%</span>
                                </div>
                                <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full" style="width: 98.6%"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-left">
                                <div>
                                    <span class="text-[9px] text-slate-400 block uppercase">CRM Opportunities</span>
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">48 Active</span>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-400 block uppercase">Task SLA</span>
                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">99.4% On-time</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Business Activity Feed (Continuous Alpine.js Simulation) -->
                    <div class="absolute -bottom-10 -right-8 z-30 w-[290px] sm:w-[330px] bg-white/90 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-250/50 dark:border-slate-800/80 p-4 rounded-2xl shadow-premium dark:shadow-premium-dark text-left hover:scale-[1.02] transition-transform duration-300"
                         x-data="{
                            events: [
                                { id: 1, title: 'New lead captured', detail: 'Nairobi Manufacturing Ltd.', time: 'Just now', module: 'CRM', color: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400', icon: '👥' },
                                { id: 2, title: 'M-PESA payment received', detail: 'KES 24,500 via Daraja API', time: '2m ago', module: 'Finance', color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', icon: '🇰🇪' },
                                { id: 3, title: 'AI proposal generated', detail: 'Completed in 6.0 seconds', time: '5m ago', module: 'AI', color: 'bg-violet-500/10 text-violet-600 dark:text-violet-400', icon: '🤖' },
                                { id: 4, title: 'CRM stage updated', detail: 'Safaricom &rarr; Negotiation stage', time: '12m ago', module: 'CRM', color: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400', icon: '🤝' },
                                { id: 5, title: 'Inventory synchronized', detail: 'All active digital catalog nodes', time: '18m ago', module: 'Marketplace', color: 'bg-teal-500/10 text-teal-600 dark:text-teal-400', icon: '📦' },
                                { id: 6, title: 'Invoice paid via Lipa Na M-PESA', detail: 'Acme Logistics &bull; KES 120,000', time: '24m ago', module: 'Finance', color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', icon: '💳' },
                                { id: 7, title: 'Support SLA met', detail: 'Ticket #JN-1082 resolved under 15m', time: '30m ago', module: 'Support', color: 'bg-blue-500/10 text-blue-600 dark:text-blue-400', icon: '🛠️' },
                                { id: 8, title: 'Marketing campaign launched', detail: 'Q3 Enterprise Reach active', time: '45m ago', module: 'CMS', color: 'bg-orange-500/10 text-orange-600 dark:text-orange-400', icon: '📰' },
                                { id: 9, title: 'Customer onboarded', detail: 'Mombasa Shipping Group active', time: '50m ago', module: 'CRM', color: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400', icon: '🏢' },
                                { id: 10, title: 'Website published', detail: 'Client custom portal sub-domain live', time: '1h ago', module: 'CMS', color: 'bg-teal-500/10 text-teal-600 dark:text-teal-400', icon: '🌐' }
                            ],
                            visibleEvents: [],
                            currentIndex: 3,
                            init() {
                                this.visibleEvents = [
                                    {...this.events[0]},
                                    {...this.events[1]},
                                    {...this.events[2]}
                                ];
                                setInterval(() => {
                                    let nextEv = { ...this.events[this.currentIndex] };
                                    nextEv.time = 'Just now';
                                    
                                    this.visibleEvents[0].time = '1m ago';
                                    this.visibleEvents[1].time = '3m ago';
                                    
                                    this.visibleEvents = [nextEv, this.visibleEvents[0], this.visibleEvents[1]];
                                    this.currentIndex = (this.currentIndex + 1) % this.events.length;
                                }, 3500);
                            }
                         }">
                        <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800/80 pb-2">
                            <div class="flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 font-mono">Live Activity Feed</span>
                            </div>
                            <span class="text-[8px] font-mono text-slate-400 uppercase bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">Sync Active</span>
                        </div>
                        <div class="relative h-[225px] overflow-hidden space-y-2.5">
                            <template x-for="(event, idx) in visibleEvents" :key="event.id">
                                <div x-transition:enter="transition ease-out duration-300 transform"
                                     x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     class="flex items-start gap-3 bg-slate-50/50 dark:bg-slate-950/40 p-2 rounded-xl border border-slate-200/50 dark:border-slate-800/50 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition duration-150">
                                     <div :class="'h-7 w-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 ' + event.color" x-text="event.icon"></div>
                                     <div class="flex-1 min-w-0">
                                         <div class="flex items-center justify-between gap-1">
                                             <span class="text-[10px] font-bold text-slate-900 dark:text-white truncate" x-text="event.title"></span>
                                             <span class="text-[8px] font-mono text-slate-400 shrink-0" x-text="event.time"></span>
                                         </div>
                                         <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate mt-0.5" x-html="event.detail"></p>
                                         <div class="mt-1 flex items-center gap-1.5">
                                             <span class="inline-flex items-center text-[7px] font-mono font-bold uppercase tracking-wider px-1 bg-slate-100 dark:bg-slate-850 text-slate-500 dark:text-slate-400 rounded" x-text="event.module"></span>
                                         </div>
                                     </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Floating Companion Badge: AI Engine status -->
                    <div class="absolute -top-12 -left-10 z-30 w-[190px] bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-200/50 dark:border-slate-800/80 p-3.5 rounded-2xl shadow-md hover:scale-[1.05] hover:-rotate-1 transition-transform duration-300 text-left">
                        <div class="flex items-center gap-2">
                            <span class="flex h-2 w-2 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                            </span>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 font-mono">AI Engine Online</span>
                        </div>
                        <div class="mt-2 text-[10px] text-slate-600 dark:text-slate-300 leading-normal">
                            Active model <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">gemini-2.5-flash</span> ready for server automation triggers.
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('content')
<!-- ==========================================
     LIVE PLATFORM PREVIEW
     ========================================== -->
<section id="preview" class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300" x-data="{
    activeTab: 'crm',
    aiTyping: 'Analyzing lead source data... Success. Synchronized Safaricom account. Initiating lipa na m-pesa payment request on behalf of Wambui...',
    aiResponse: 'STK push verified. Enterprise CRM record has been promoted to Active Partner. Billing record #JN-2026-902 successfully posted.',
    typingDone: false,
    stkProgress: 0,
    animateSTK() {
        this.stkProgress = 0;
        let interval = setInterval(() => {
            if(this.stkProgress < 100) {
                this.stkProgress += 5;
            } else {
                clearInterval(interval);
                $dispatch('trigger-toast', { message: '✓ M-PESA STK Push payment of KES 1,200,000 received successfully!', type: 'success' });
            }
        }, 100);
    }
}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <x-section-title 
            badge="Interactive Demonstration"
            title="Experience the Core Platform Control Center"
            subtitle="Explore our ultra-fast dashboard console. Switch through tabs below to see how our micro-states and live M-PESA Daraja verification engines run."
        />

        <!-- Interactive Dashboard Console Frame -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-premium dark:shadow-premium-dark overflow-hidden transition-all duration-300">
            <!-- Console Top Control Header -->
            <div class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200/80 dark:border-slate-800 px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="flex gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-red-400"></span>
                        <span class="h-3 w-3 rounded-full bg-yellow-400"></span>
                        <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                    </span>
                    <span class="text-xs text-slate-400 font-mono dark:text-slate-500">console.juanet.cloud/home</span>
                </div>

                <!-- Tabs navigation inside console layout -->
                <div class="flex bg-slate-100 dark:bg-slate-950/80 p-1 rounded-xl border border-slate-200/50 dark:border-slate-800/60 text-xs">
                    <button @click="activeTab = 'crm'" :class="activeTab === 'crm' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'" class="px-4 py-2 rounded-lg font-semibold transition cursor-pointer">
                        CRM Pipeline
                    </button>
                    <button @click="activeTab = 'marketplace'" :class="activeTab === 'marketplace' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'" class="px-4 py-2 rounded-lg font-semibold transition cursor-pointer">
                        Marketplace Orders
                    </button>
                    <button @click="activeTab = 'finance'" :class="activeTab === 'finance' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'" class="px-4 py-2 rounded-lg font-semibold transition cursor-pointer">
                        Financial Ledger
                    </button>
                    <button @click="activeTab = 'ai'" :class="activeTab === 'ai' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'" class="px-4 py-2 rounded-lg font-semibold transition cursor-pointer">
                        AI Automation
                    </button>
                </div>
            </div>

            <!-- Console Inner Dashboard View Content -->
            <div class="p-6 md:p-8 min-h-[420px] transition-all">
                
                <!-- 1. CRM TAB VIEW -->
                <div x-show="activeTab === 'crm'" class="space-y-6" x-transition:enter="transition duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white font-display">Active Customer Pipelines</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Real-time enterprise pipeline value and account distribution tracking.</p>
                        </div>
                        <span class="text-xs font-mono bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 border border-indigo-200/20 px-3 py-1 rounded-xl">Value: KES 84.5M</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- CRM Card 1: Inbox/Lead -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/60 pb-2">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider font-mono">Incoming leads (4)</span>
                                <span class="h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></span>
                            </div>
                            <div class="space-y-3">
                                <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/60 p-3 rounded-xl shadow-xs space-y-1">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Amani Wegesa</div>
                                    <div class="text-[10px] text-slate-500">Equity Bank Group</div>
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-[9px] font-mono text-indigo-600 dark:text-indigo-400 font-semibold">KES 4.5M</span>
                                        <x-badge variant="indigo">SLA Gold</x-badge>
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/60 p-3 rounded-xl shadow-xs space-y-1">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Josphat Juan</div>
                                    <div class="text-[10px] text-slate-500">Nairobi Dev Solutions</div>
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-[9px] font-mono text-indigo-600 dark:text-indigo-400 font-semibold">KES 1.8M</span>
                                        <x-badge variant="success">Assigned</x-badge>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CRM Card 2: STK Push/Verification -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/60 pb-2">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider font-mono">Verification Gate (1)</span>
                                <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                            </div>
                            <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/60 p-3.5 rounded-xl shadow-xs space-y-2">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Safaricom Enterprise Billing</div>
                                <div class="text-[10px] text-slate-500">Wambui Kamau &middot; Head of Corp Tech</div>
                                <div class="bg-slate-50 dark:bg-slate-950 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800/60">
                                    <div class="flex justify-between text-[9px] text-slate-400 font-mono">
                                        <span>STK Push Pending</span>
                                        <span x-text="stkProgress + '%'">0%</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full mt-1 overflow-hidden">
                                        <div class="h-full bg-indigo-600 dark:bg-indigo-400 rounded-full transition-all duration-100" :style="'width: ' + stkProgress + '%'"></div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-[9px] font-mono text-slate-900 dark:text-white font-bold">KES 1,200,000</span>
                                    <button @click="animateSTK()" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[9px] px-2.5 py-1 rounded-lg transition">
                                        Trigger STK Push
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CRM Card 3: Converted/Closed Won -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/60 pb-2">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider font-mono">Converted Closed-Won</span>
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            </div>
                            <div class="space-y-3">
                                <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/60 p-3 rounded-xl shadow-xs space-y-1 border-l-4 border-l-emerald-500">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Aura Web Agency</div>
                                    <div class="text-[10px] text-slate-500">Phase 1 Invoice Settled</div>
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-[9px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">KES 750,000</span>
                                        <x-badge variant="success">Completed</x-badge>
                                    </div>
                                </div>
                                <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/60 p-3 rounded-xl shadow-xs space-y-1 border-l-4 border-l-emerald-500">
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">Rift Venture Holdings</div>
                                    <div class="text-[10px] text-slate-500">Direct Daraja Ledger Synced</div>
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-[9px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">KES 3,200,000</span>
                                        <x-badge variant="success">Completed</x-badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. MARKETPLACE TAB VIEW -->
                <div x-show="activeTab === 'marketplace'" class="space-y-6" x-transition:enter="transition duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white font-display">Digital Marketplace Ledger</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Track online enterprise marketplace orders, API subscription activations, and service sales.</p>
                        </div>
                        <x-badge variant="success">Operational Ingress</x-badge>
                    </div>

                    <x-table :headers="['Order Ref', 'Service Asset', 'Customer Entity', 'Fulfillment', 'Price']">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-900 dark:text-white">#JN-MK-9204</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Daraja API Integration Suite</div>
                                <div class="text-[9px] text-slate-400">Core Developer Module Bundle</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">Nairobi Tech Hub</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-badge variant="success">Instant Dispatched</x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-900 dark:text-white">KES 49,000</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-900 dark:text-white">#JN-MK-9203</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">CRM Custom Pipeline Extender</div>
                                <div class="text-[9px] text-slate-400">Plugin Asset Asset Bundle</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">Kijiji Media LTD</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-badge variant="success">Instant Dispatched</x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-900 dark:text-white">KES 24,500</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-900 dark:text-white">#JN-MK-9202</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs font-bold text-slate-900 dark:text-white">Enterprise AI Assistant Agent</div>
                                <div class="text-[9px] text-slate-400">Gemini-fueled Assistant Node</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">Apex Logic EA</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-badge variant="indigo">SLA Verification</x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono font-bold text-slate-900 dark:text-white">KES 149,000</td>
                        </tr>
                    </x-table>
                </div>

                <!-- 3. FINANCE TAB VIEW -->
                <div x-show="activeTab === 'finance'" class="space-y-6" x-transition:enter="transition duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Revenue widget -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl">
                            <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">Ledger Gross Revenue</span>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-display mt-1">KES 142.4M</div>
                            <span class="text-[10px] text-emerald-500 font-bold mt-2 inline-block">↑ 24.3% YoY Growth</span>
                        </div>
                        <!-- Completed transactions widget -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl">
                            <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">Transactions Solved</span>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-display mt-1">12,490</div>
                            <span class="text-[10px] text-slate-400 mt-2 inline-block">M-PESA LNM + Card Payments</span>
                        </div>
                        <!-- Refund rate -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl">
                            <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">Dispute Margin</span>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-display mt-1">0.02%</div>
                            <span class="text-[10px] text-emerald-500 font-bold mt-2 inline-block">Excellent rating metrics</span>
                        </div>
                        <!-- API availability -->
                        <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-5 rounded-2xl">
                            <span class="text-[10px] text-slate-400 uppercase tracking-widest font-mono">Daraja SLA Availability</span>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-white font-display mt-1">99.99%</div>
                            <span class="text-[10px] text-emerald-500 font-bold mt-2 inline-block">Nairobi East edge servers</span>
                        </div>
                    </div>

                    <!-- Custom beautiful graphic chart placeholder -->
                    <div class="bg-slate-50 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 p-6 rounded-2xl relative overflow-hidden">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-xs font-bold text-slate-900 dark:text-white font-display">Transaction Trends (Past 6 Months)</span>
                            <span class="text-[10px] font-mono text-slate-400">Total processed KES value</span>
                        </div>
                        <!-- SVG Premium Mock Chart -->
                        <svg class="w-full h-32 text-indigo-500" viewBox="0 0 1000 100" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="currentColor" stop-opacity="0.2"/>
                                    <stop offset="100%" stop-color="currentColor" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="M0 80 Q 200 40, 400 60 T 800 20 T 1000 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                            <path d="M0 80 Q 200 40, 400 60 T 800 20 T 1000 10 L 1000 100 L 0 100 Z" fill="url(#chartGradient)"></path>
                        </svg>
                        <div class="flex justify-between text-[10px] text-slate-400 font-mono mt-2">
                            <span>JAN</span>
                            <span>FEB</span>
                            <span>MAR</span>
                            <span>APR</span>
                            <span>MAY</span>
                            <span>JUN</span>
                        </div>
                    </div>
                </div>

                <!-- 4. AI AUTOMATION TAB VIEW -->
                <div x-show="activeTab === 'ai'" class="space-y-6" x-transition:enter="transition duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-cloak>
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🤖</span>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white font-mono">JUANET AI Intelligence Core</h3>
                                <span class="text-[10px] text-slate-400 block font-mono">ACTIVE MODULE: gemini-2.5-flash-agent</span>
                            </div>
                        </div>
                        <button @click="typingDone = !typingDone" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Simulate new automation log
                        </button>
                    </div>

                    <div class="space-y-4 font-mono text-xs text-left">
                        <!-- AI Terminal Screen mockup -->
                        <div class="bg-slate-950 text-emerald-400 p-5 rounded-2xl border border-slate-900 space-y-3 leading-relaxed">
                            <div class="flex items-center gap-1.5 text-slate-500 text-[10px] border-b border-slate-900 pb-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                <span>SYSTEM_LOGS: REALTIME_AI_STREAMING</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex gap-2">
                                    <span class="text-slate-500">[02:53:51]</span>
                                    <span class="text-indigo-400">&gt;_ sys.initialize_pipeline_analysis</span>
                                </div>
                                <div class="text-slate-200" x-text="typingDone ? aiResponse : aiTyping"></div>
                                <div class="flex gap-2 text-[11px] text-slate-500 pt-1" x-show="typingDone">
                                    <span>[LATENCY: 12ms]</span>
                                    <span>[TOKENS: 48]</span>
                                    <span>[AGENT_STATE: RECONCILED]</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     TRUSTED BY SECTION (LOGO WALL)
     ========================================== -->
<section id="trust" class="py-12 bg-white dark:bg-slate-950 border-y border-slate-100 dark:border-slate-900 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-8 font-mono">Trusted by Africa's Ambitious Tech & Enterprise Teams</p>
        
        <!-- Scrolling Logo Grid Representation -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-8 items-center justify-items-center opacity-60 dark:opacity-40">
            <!-- Brand 1 -->
            <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-slate-900 dark:bg-white flex items-center justify-center text-white dark:text-slate-900 font-extrabold text-xs">S</span>
                <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white uppercase font-display">Safaricom PLC</span>
            </div>
            <!-- Brand 2 -->
            <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-indigo-600 flex items-center justify-center text-white font-extrabold text-xs">E</span>
                <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white uppercase font-display">Equity Group</span>
            </div>
            <!-- Brand 3 -->
            <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-emerald-600 flex items-center justify-center text-white font-extrabold text-xs">K</span>
                <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white uppercase font-display">Kijiji Media</span>
            </div>
            <!-- Brand 4 -->
            <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-violet-600 flex items-center justify-center text-white font-extrabold text-xs">A</span>
                <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white uppercase font-display">Apex Logic</span>
            </div>
            <!-- Brand 5 -->
            <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-red-600 flex items-center justify-center text-white font-extrabold text-xs">N</span>
                <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white uppercase font-display">Nairobi Tech</span>
            </div>
            <!-- Brand 6 -->
            <div class="flex items-center gap-2">
                <span class="h-6 w-6 rounded bg-teal-600 flex items-center justify-center text-white font-extrabold text-xs">R</span>
                <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white uppercase font-display">Rift Ventures</span>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     FEATURES LIST SECTION
     ========================================== -->
<section id="features" class="py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-section-title 
            badge="Unified Modules Suite"
            title="Eight Modules. One Single Engine."
            subtitle="Ditch the fragmented tool stack. JUANET compiles all critical enterprise workflows into a sub-millisecond local state application."
        />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Module 1: CRM -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-lg font-bold mb-4">👥</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Unified CRM Suite</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Manage pipelines, log comprehensive lead histories, and assign enterprise opportunities seamlessly.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <a href="/crm" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore CRM Suite &rarr;
                    </a>
                </div>
            </div>

            <!-- Module 2: Marketplace -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-lg font-bold mb-4">🛒</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Digital Marketplace</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Publish cloud resources, digital assets, or billing products with instant secure key dispatch.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <button @click="alert('✓ Digital Marketplace listings handled natively via our CRM catalog integrations.')" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore Marketplace &rarr;
                    </button>
                </div>
            </div>

            <!-- Module 3: Projects -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-violet-50 dark:bg-violet-950/40 flex items-center justify-center text-violet-600 dark:text-violet-400 text-lg font-bold mb-4">📋</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Project Trackers</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Deploy Scrum sprint tables, assign tasks, track timesheets, and enforce delivery deadlines.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <a href="/dashboard" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore Projects &rarr;
                    </a>
                </div>
            </div>

            <!-- Module 4: Finance -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-pink-50 dark:bg-pink-950/40 flex items-center justify-center text-pink-600 dark:text-pink-400 text-lg font-bold mb-4">💳</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Financial Ledgers</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Direct lipa na m-pesa online integration with automated STK push notifications and invoice pdf generators.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <button @click="alert('✓ Live daraja secure ledger available upon setting environment API keys.')" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore Ledgers &rarr;
                    </button>
                </div>
            </div>

            <!-- Module 5: CMS Portfolio -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-orange-50 dark:bg-orange-950/40 flex items-center justify-center text-orange-600 dark:text-orange-400 text-lg font-bold mb-4">📰</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Dynamic Content Suite</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Publish search-engine-optimized company announcements, blogs, careers, and portfolio pages.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <button @click="alert('✓ Blog suite incorporates rich SEO, canonical tag links, and RSS feeds.')" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore CMS Suite &rarr;
                    </button>
                </div>
            </div>

            <!-- Module 6: SLA Support Desk -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-950/40 flex items-center justify-center text-blue-600 dark:text-blue-400 text-lg font-bold mb-4">🛠️</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Enterprise Support</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Log SLA resolution tickets, assign support desks, and track agent responses in real-time.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <a href="/dashboard" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore Support &rarr;
                    </a>
                </div>
            </div>

            <!-- Module 7: AI Agents -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-violet-50 dark:bg-violet-950/40 flex items-center justify-center text-violet-600 dark:text-violet-400 text-lg font-bold mb-4">🤖</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Gemini AI Assistant</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Inject Gemini models inside CRM lead parsing, workspace scheduling, and support ticket triage.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <button @click="alert('✓ AI capabilities run server-side using Google Gemini SDK.')" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore AI Agents &rarr;
                    </button>
                </div>
            </div>

            <!-- Module 8: Automation Triggers -->
            <div class="group relative bg-white border border-slate-200/60 p-6 rounded-2xl shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:bg-slate-900 dark:border-slate-800">
                <div class="h-10 w-10 rounded-xl bg-teal-50 dark:bg-teal-950/40 flex items-center justify-center text-teal-600 dark:text-teal-400 text-lg font-bold mb-4">⚡</div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display">Secure Automation</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">Establish webhook triggers, coordinate background workers, and connect third-party platforms.</p>
                <div class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/60">
                    <button @click="alert('✓ Background workers track webhook payloads instantly.')" class="inline-flex items-center text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                        Explore Automation &rarr;
                    </button>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ==========================================
     HOW IT WORKS SECTION
     ========================================== -->
<section id="how-it-works" class="py-24 bg-white dark:bg-slate-950 border-t border-slate-100 dark:border-slate-900 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-section-title 
            badge="Simplified Scaling"
            title="Accelerate Enterprise Performance in Three Steps"
            subtitle="Transform disjointed operations into a streamlined automated powerhouse. Standard onboarding takes less than ten minutes."
        />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 relative">
            <!-- Connecting dashed line for desktop -->
            <div class="hidden md:block absolute top-[50px] left-[15%] right-[15%] h-0.5 border-t border-dashed border-slate-200 dark:border-slate-800 z-0"></div>

            <!-- Step 1 -->
            <div class="text-center space-y-4 relative z-10">
                <div class="h-14 w-14 rounded-full bg-indigo-600 text-white font-black font-display text-lg flex items-center justify-center mx-auto shadow-md border-4 border-slate-50 dark:border-slate-950">
                    01
                </div>
                <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Create Workspace</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-xs mx-auto">Register an organization, define custom subdomains, configure permission roles, and invite collaborative team administrators.</p>
            </div>

            <!-- Step 2 -->
            <div class="text-center space-y-4 relative z-10">
                <div class="h-14 w-14 rounded-full bg-indigo-600 text-white font-black font-display text-lg flex items-center justify-center mx-auto shadow-md border-4 border-slate-50 dark:border-slate-950">
                    02
                </div>
                <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Grow Business</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-xs mx-auto">Publish products in the digital marketplace, map custom CRM pipelines, and handle real-time lipa na m-pesa payment requests.</p>
            </div>

            <!-- Step 3 -->
            <div class="text-center space-y-4 relative z-10">
                <div class="h-14 w-14 rounded-full bg-emerald-500 text-white font-black font-display text-lg flex items-center justify-center mx-auto shadow-md border-4 border-slate-50 dark:border-slate-950">
                    03
                </div>
                <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Scale with AI</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-xs mx-auto">Inject intelligent Gemini bots inside lead logs, automate support ticket answers, and schedule transactional SLA triggers.</p>
            </div>

        </div>
    </div>
</section>

<!-- ==========================================
     PLATFORM SHOWCASE (ALTERNATE SECTIONS)
     ========================================== -->
<section id="showcase" class="py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300 space-y-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Showcase Header -->
        <x-section-title 
            badge="The Platform Inside"
            title="Premium Visual Workspaces"
            subtitle="Explore how our visual modules look inside. Designed for maximum high-density information visibility on any display width."
        />

        <!-- Section 1: CRM & Pipeline Showcase (Text left, Graphic right) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            <div class="lg:col-span-6 space-y-6 text-left">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 font-mono">Module 01 / CUSTOMER RELATIONSHIPS</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display tracking-tight leading-tight">High-Contrast Kanban Opportunity Pipelines</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Natively structured to optimize salesperson focus. Track every contact, organization touchpoint, past communications, pending invoices, and project handovers in a singular workspace ledger. No screen jumps required.
                </p>
                <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Drag and drop pipeline opportunity promotions
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Automated organization accounts categorization
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Real-time synchronization with active billing ledgers
                    </div>
                </div>
                <div class="pt-2">
                    <a href="/crm" class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 px-5 py-2.5 text-xs font-bold transition">
                        Open CRM Console
                    </a>
                </div>
            </div>

            <!-- Graphic Right: Immersive Mock Pipeline -->
            <div class="lg:col-span-6 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-6 rounded-2xl shadow-premium dark:shadow-premium-dark space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-indigo-500"></span>
                        <span class="text-xs font-bold text-slate-800 dark:text-white font-display">Deal Board: Safaricom Account</span>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400">Total Pipeline: KES 14.5M</span>
                </div>
                <div class="grid grid-cols-2 gap-4 text-left">
                    <div class="bg-slate-50 dark:bg-slate-950/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800/60">
                        <span class="text-[9px] text-slate-400 block uppercase font-mono">Assigned Partner</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-white">Wambui Kamau</span>
                        <span class="text-[9px] text-slate-500 block">Safaricom Corp Tech</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-950/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800/60">
                        <span class="text-[9px] text-slate-400 block uppercase font-mono">Deal Value</span>
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">KES 1,200,000</span>
                        <span class="text-[9px] text-emerald-500 block">STK push verified</span>
                    </div>
                </div>
                <div class="bg-slate-50 dark:bg-slate-950/40 p-4 rounded-xl border border-slate-100 dark:border-slate-800/60 space-y-2">
                    <div class="flex justify-between text-[10px] font-mono text-slate-400">
                        <span>Lipa Na M-PESA STK Push Logs</span>
                        <span class="text-emerald-500">Verified success</span>
                    </div>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/60 p-2.5 rounded-lg font-mono text-[9px] text-slate-500 space-y-1">
                        <div>[02:53:51] STK_PUSH_DISPATCHED -&gt; ID: 90248249</div>
                        <div class="text-emerald-500">[02:53:54] CALLBACK_SUCCESS -&gt; MPESA_REF: KSK90248</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="my-24 border-t border-slate-100 dark:border-slate-900"></div>

        <!-- Section 2: Marketplace & Projects (Text right, Graphic left) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            
            <!-- Graphic Left: Marketplace & Project Mock Card -->
            <div class="lg:col-span-6 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-6 rounded-2xl shadow-premium dark:shadow-premium-dark space-y-6 order-last lg:order-first">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-800 dark:text-white font-display">Task &amp; Dispatch Controller</span>
                    <x-badge variant="indigo">Sprint Active</x-badge>
                </div>
                
                <div class="space-y-3.5">
                    <!-- Task item 1 -->
                    <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-950/40 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800/60">
                        <div class="flex items-center gap-3">
                            <span class="h-5 w-5 bg-emerald-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">✓</span>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-white block">Compile Daraja Webhook Client</span>
                                <span class="text-[9px] text-slate-400">Assigned: Josphat Juan</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-mono text-slate-400">SLA Met</span>
                    </div>
                    <!-- Task item 2 -->
                    <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-950/40 p-3.5 rounded-xl border border-slate-100 dark:border-slate-800/60">
                        <div class="flex items-center gap-3">
                            <span class="h-5 w-5 bg-emerald-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">✓</span>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-white block">Optimize CMS Canonical Link Structure</span>
                                <span class="text-[9px] text-slate-400">Assigned: Wambui Kamau</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-mono text-slate-400">SLA Met</span>
                    </div>
                </div>

                <div class="bg-indigo-50/50 dark:bg-indigo-950/20 p-4 rounded-xl border border-indigo-100/30 dark:border-indigo-950/50 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg">⚡</span>
                        <div>
                            <span class="text-[11px] font-bold text-indigo-900 dark:text-indigo-400 block">CRM Automation Trigger</span>
                            <span class="text-[9px] text-indigo-600/70 dark:text-indigo-400/70">On Sprint Task Resolution</span>
                        </div>
                    </div>
                    <span class="text-[9px] bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-400 font-mono px-2 py-0.5 rounded-lg">Instant Slack Sync</span>
                </div>
            </div>

            <!-- Text Right -->
            <div class="lg:col-span-6 space-y-6 text-left">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 font-mono">Module 02 / SPRINT DELIVERABLES</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display tracking-tight leading-tight">Advanced Agile Projects &amp; Automatic Key Dispatch</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    Bridge client contracts with actual engineering sprints. Upon settling invoices via our financial ledger portals, JUANET instantly allocates sprint tickets, establishes SLA parameters, and dispatches critical API credentials automatically.
                </p>
                <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Sub-second local sprint state refreshes
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Automatic secure license key and file download dispatching
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Interactive developer dashboard for key tracking
                    </div>
                </div>
                <div class="pt-2">
                    <a href="/dashboard" class="inline-flex items-center justify-center rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 px-5 py-2.5 text-xs font-bold transition">
                        Open Project Console
                    </a>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- ==========================================
     STATISTICS SECTION
     ========================================== -->
<section id="statistics" class="py-24 bg-white dark:bg-slate-950 border-y border-slate-100 dark:border-slate-900 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 border border-indigo-200/20">
                Enterprise Readiness
            </span>
            <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white font-display mt-3">Pre-Launch Platform Specifications</h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-2">Engineered to meet the absolute highest standards of performance, security, and integration.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Card 1: Infrastructure -->
            <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200/40 dark:border-slate-800/80 p-6 rounded-2xl space-y-4 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-lg">☁️</span>
                    <span class="text-[10px] font-mono text-emerald-500 font-bold bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded-full">99.9% SLA</span>
                </div>
                <div class="space-y-1">
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Cloud Native</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">High-performance scalable architecture containerized for zero cold-start delivery.</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-200/30 dark:border-slate-800/50">
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Laravel 12</span>
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Supabase Pg</span>
                </div>
            </div>

            <!-- Card 2: Integration -->
            <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200/40 dark:border-slate-800/80 p-6 rounded-2xl space-y-4 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-lg">🇰🇪</span>
                    <span class="text-[10px] font-mono text-indigo-500 font-bold bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded-full">Daraja v2</span>
                </div>
                <div class="space-y-1">
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">M-PESA Ready</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Pre-integrated Safaricom callbacks for sub-second automated billing ledgers.</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-200/30 dark:border-slate-800/50">
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Built for Africa</span>
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">VAT Ready</span>
                </div>
            </div>

            <!-- Card 3: Tenant Isolation -->
            <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200/40 dark:border-slate-800/80 p-6 rounded-2xl space-y-4 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-lg">🔒</span>
                    <span class="text-[10px] font-mono text-indigo-500 font-bold bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded-full">Active Isolation</span>
                </div>
                <div class="space-y-1">
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Multi-Tenant SaaS</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Robust multi-workspace architecture keeping customer records isolated securely.</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-200/30 dark:border-slate-800/50">
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Enterprise Ready</span>
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Secure by Design</span>
                </div>
            </div>

            <!-- Card 4: Intelligence -->
            <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200/40 dark:border-slate-800/80 p-6 rounded-2xl space-y-4 hover:shadow-md transition">
                <div class="flex items-center justify-between">
                    <span class="text-lg">🤖</span>
                    <span class="text-[10px] font-mono text-violet-500 font-bold bg-violet-50 dark:bg-violet-950/50 px-2 py-0.5 rounded-full">Gemini Core</span>
                </div>
                <div class="space-y-1">
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">AI-Ready Platform</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Integrated SDK pipeline ready to orchestrate cognitive triggers and data tagging.</p>
                </div>
                <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-200/30 dark:border-slate-800/50">
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">AI-Ready</span>
                    <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Event-Driven</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     OUR EXPERTISE & DIGITAL AGENCY SERVICES
     ========================================== -->
<section id="expertise" class="relative py-24 bg-white dark:bg-slate-900 border-t border-b border-slate-150 dark:border-slate-800 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <x-section-title 
            badge="Solutions Studio"
            title="Our Core Engineering &amp; Creative Expertise"
            subtitle="We engineer enterprise-grade web applications and high-fidelity brand systems designed for continuous, high-performance market growth."
        />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-12">
            <!-- Expertise 1 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">💻</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">Custom Website Development</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Stunning corporate sites optimized for high search engine visibility, lightning speed, and maximum brand positioning.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Build My Website &rarr;
                </a>
            </div>

            <!-- Expertise 2 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">⚙️</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">Enterprise Web Applications</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Secure, responsive web dashboards, administrative control portals, and multi-tenant billing engines built with Laravel.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Develop My SaaS &rarr;
                </a>
            </div>

            <!-- Expertise 3 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">📱</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">Mobile Applications</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Robust cross-platform iOS and Android mobile solutions featuring instant push alerts and offline-first state caching.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Start My Project &rarr;
                </a>
            </div>

            <!-- Expertise 4 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">🎨</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">UI / UX &amp; Branding</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Aesthetic wireframing, high-contrast user interface mockups, modern vector logo design, and brand guideline sheets.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Create My Brand &rarr;
                </a>
            </div>

            <!-- Expertise 5 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">🚀</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">Digital Marketing &amp; SEO</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Bespoke technical search optimization strategies to drive localized organic leads and enhance regional business visibility.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Book a Consultation &rarr;
                </a>
            </div>

            <!-- Expertise 6 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">🤖</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">AI Automation &amp; Consulting</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Leveraging generative models like Gemini to automate billing registers, classify user intent, and audit financial ledgers.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Automate My Business &rarr;
                </a>
            </div>

            <!-- Expertise 7 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">⚡</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">Cloud Solutions &amp; APIs</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Robust API integrations with Safaricom Daraja, webhook processing, database architecture, and container cluster routing.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Talk to an Expert &rarr;
                </a>
            </div>

            <!-- Expertise 8 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800/80 p-6 rounded-2xl shadow-xs hover:shadow-sm hover:border-slate-300 dark:hover:border-slate-700 transition group text-left">
                <span class="text-xl">📚</span>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white font-display mt-3 mb-1.5">Knowledge Platforms</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Interactive e-learning frameworks, documentation portals, and corporate knowledge bases for internal training.</p>
                <a href="/quote-request" class="inline-flex items-center text-[10px] font-bold text-indigo-600 dark:text-indigo-400 mt-4 group-hover:translate-x-1 transition-transform">
                    Build My Platform &rarr;
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     PORTFOLIO / CASE STUDIES PREVIEW
     ========================================== -->
<section id="portfolio-preview" class="relative py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <x-section-title 
            badge="SaaS Showcase"
            title="Premium Showcase of Design &amp; Technology Work"
            subtitle="Take an immersive look at completed and upcoming software systems engineered for regional scale-to-zero efficiency."
        />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12 text-left">
            <!-- Project 1 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition">
                <div class="h-40 bg-gradient-to-tr from-slate-950 to-indigo-900 p-6 flex flex-col justify-between relative">
                    <span class="text-[9px] font-mono font-bold text-indigo-300 bg-indigo-950/80 border border-indigo-800 px-2 py-0.5 rounded uppercase self-start">Active System</span>
                    <div>
                        <span class="text-[9px] text-indigo-200 block uppercase font-mono tracking-wider">CRM Systems &amp; Logistics</span>
                        <h4 class="text-base font-bold text-white font-display mt-0.5">Apex Corporate CRM Platform</h4>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Integrated Daraja M-PESA billing gateway, automated client workspace creation, and granular task workflows.</p>
                    <div class="flex flex-wrap gap-1">
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">Laravel 12</span>
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">Daraja v2</span>
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">PostgreSQL</span>
                    </div>
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <a href="/portfolio" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">View Case Study &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Project 2 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition">
                <div class="h-40 bg-gradient-to-tr from-slate-950 to-violet-900 p-6 flex flex-col justify-between relative">
                    <span class="text-[9px] font-mono font-bold text-violet-300 bg-violet-950/80 border border-violet-800 px-2 py-0.5 rounded uppercase self-start">Completed Node</span>
                    <div>
                        <span class="text-[9px] text-violet-200 block uppercase font-mono tracking-wider">Marketplaces &amp; Retail</span>
                        <h4 class="text-base font-bold text-white font-display mt-0.5">Kijiji Assets Marketplace</h4>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Scale-to-zero digital asset index delivering instant download tokens and automated watermark compliance.</p>
                    <div class="flex flex-wrap gap-1">
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">Tailwind CSS</span>
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">AlpineJS</span>
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">Supabase PG</span>
                    </div>
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <a href="/portfolio" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">View Case Study &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Project 3 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition">
                <div class="h-40 bg-gradient-to-tr from-slate-950 to-emerald-900 p-6 flex flex-col justify-between relative">
                    <span class="text-[9px] font-mono font-bold text-emerald-300 bg-emerald-950/80 border border-emerald-800 px-2 py-0.5 rounded uppercase self-start">Under Development</span>
                    <div>
                        <span class="text-[9px] text-emerald-200 block uppercase font-mono tracking-wider">AI Solutions &amp; Analytics</span>
                        <h4 class="text-base font-bold text-white font-display mt-0.5">Safaricom Analytics Integrations</h4>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Cognitive data sorting engines utilizing Gemini API workflows to automate tax compliance auditing logs.</p>
                    <div class="flex flex-wrap gap-1">
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">Gemini SDK</span>
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">Server AI</span>
                        <span class="text-[8px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded">VAT Audits</span>
                    </div>
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80">
                        <a href="/portfolio" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">View Case Study &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-12 text-center">
            <a href="/portfolio" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 transition shadow-sm">
                Explore Full Portfolio Showcase &rarr;
            </a>
        </div>
    </div>
</section>

<!-- ==========================================
     DIGITAL PRODUCTS MARKETPLACE PREVIEW
     ========================================== -->
<section id="marketplace-preview" class="relative py-24 bg-white dark:bg-slate-900 border-t border-b border-slate-150 dark:border-slate-800 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <x-section-title 
            badge="Developer Assets"
            title="Explore Upcoming Marketplace Assets"
            subtitle="Acquire pre-built software blueprints, admin setups, brand packs, and AI assets compiled by senior creators."
        />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-12 text-left">
            <!-- Product 1 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 p-4 rounded-2xl flex flex-col justify-between group">
                <div class="space-y-3">
                    <div class="h-28 rounded-xl bg-gradient-to-br from-indigo-500/10 to-transparent p-3 flex flex-col justify-between">
                        <span class="text-[8px] font-mono font-bold text-indigo-500 bg-indigo-500/10 px-1.5 py-0.5 rounded uppercase self-start">Starter Kit</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-white font-display block">Laravel Daraja Boilerplate</span>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">★★★★★</span>
                        <span class="text-slate-900 dark:text-white font-bold">KES 14,500</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/60 mt-4">
                    <a href="/marketplace" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 block group-hover:translate-x-1 transition-transform">Preview Boilerplate &rarr;</a>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 p-4 rounded-2xl flex flex-col justify-between group">
                <div class="space-y-3">
                    <div class="h-28 rounded-xl bg-gradient-to-br from-violet-500/10 to-transparent p-3 flex flex-col justify-between">
                        <span class="text-[8px] font-mono font-bold text-violet-500 bg-violet-500/10 px-1.5 py-0.5 rounded uppercase self-start">Dashboard</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-white font-display block">Apex Admin UI Kit</span>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">★★★★★</span>
                        <span class="text-slate-900 dark:text-white font-bold">KES 8,000</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/60 mt-4">
                    <a href="/marketplace" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 block group-hover:translate-x-1 transition-transform">Preview Dashboard &rarr;</a>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 p-4 rounded-2xl flex flex-col justify-between group">
                <div class="space-y-3">
                    <div class="h-28 rounded-xl bg-gradient-to-br from-teal-500/10 to-transparent p-3 flex flex-col justify-between">
                        <span class="text-[8px] font-mono font-bold text-teal-500 bg-teal-500/10 px-1.5 py-0.5 rounded uppercase self-start">Branding</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-white font-display block">Brand Identity Deck</span>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">★★★★☆</span>
                        <span class="text-slate-900 dark:text-white font-bold">KES 6,000</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/60 mt-4">
                    <a href="/marketplace" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 block group-hover:translate-x-1 transition-transform">Preview Deck &rarr;</a>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 p-4 rounded-2xl flex flex-col justify-between group">
                <div class="space-y-3">
                    <div class="h-28 rounded-xl bg-gradient-to-br from-emerald-500/10 to-transparent p-3 flex flex-col justify-between">
                        <span class="text-[8px] font-mono font-bold text-emerald-500 bg-emerald-500/10 px-1.5 py-0.5 rounded uppercase self-start">Prompts</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-white font-display block">Gemini Prompt Library</span>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">★★★★★</span>
                        <span class="text-slate-900 dark:text-white font-bold">KES 3,200</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/60 mt-4">
                    <a href="/marketplace" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 block group-hover:translate-x-1 transition-transform">Preview Library &rarr;</a>
                </div>
            </div>
        </div>

        <div class="mt-12 text-center">
            <a href="/marketplace" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 transition shadow-sm">
                Open Marketplace Assets Directory &rarr;
            </a>
        </div>
    </div>
</section>

<!-- ==========================================
     KNOWLEDGE CENTER / BLOG PREVIEW
     ========================================== -->
<section id="blog-preview" class="relative py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <x-section-title 
            badge="Technical Insights"
            title="Read from Our Knowledge Platform"
            subtitle="Comprehensive guidelines on regional integrations, digital strategy, and modular SaaS billing structures."
        />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12 text-left">
            <!-- Article 1 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl flex flex-col justify-between">
                <div class="space-y-3">
                    <span class="text-[9px] font-mono font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded uppercase">Business Strategy</span>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display leading-tight">How Much Does a Website Cost in Kenya?</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">A detailed breakdown of pricing parameters, custom design overheads, and hosting setup costs in East Africa.</p>
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 mt-5 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                    <span>Published: July 2, 2026</span>
                    <a href="/blog" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">Continue Reading &rarr;</a>
                </div>
            </div>

            <!-- Article 2 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl flex flex-col justify-between">
                <div class="space-y-3">
                    <span class="text-[9px] font-mono font-bold text-violet-500 bg-violet-50 dark:bg-violet-950/40 px-2 py-0.5 rounded uppercase">Engineering</span>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display leading-tight">Laravel vs WordPress: Best SaaS Framework</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Analyzing tenant isolation, multi-workspace scalability, Eloquent security protocols, and queue structures.</p>
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 mt-5 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                    <span>Published: June 28, 2026</span>
                    <a href="/blog" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">Continue Reading &rarr;</a>
                </div>
            </div>

            <!-- Article 3 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl flex flex-col justify-between">
                <div class="space-y-3">
                    <span class="text-[9px] font-mono font-bold text-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded uppercase">API Integrations</span>
                    <h4 class="text-base font-bold text-slate-900 dark:text-white font-display leading-tight">Integrating M-PESA into Laravel Safely</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Secure Safaricom Daraja callback handlers, STK verification, VAT ledger posting, and transaction integrity.</p>
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 mt-5 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                    <span>Published: June 15, 2026</span>
                    <a href="/blog" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">Continue Reading &rarr;</a>
                </div>
            </div>
        </div>

        <div class="mt-12 text-center">
            <a href="/blog" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 transition shadow-sm">
                Open Blog Directory &rarr;
            </a>
        </div>
    </div>
</section>

<!-- ==========================================
     CLIENT TRUST & DEVELOPMENT COVENANT
     ========================================== -->
<section id="covenant" class="relative py-24 bg-white dark:bg-slate-900 border-t border-b border-slate-150 dark:border-slate-800 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-left">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            <div class="lg:col-span-5 space-y-6">
                <span class="text-xs font-mono font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 px-3 py-1 rounded-full">Development Process</span>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white font-display leading-tight">Our Modern Delivery &amp; Security Covenant</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                    We deliver mission-critical software frameworks using highly organized Agile sprints. From initial wireframing to CI/CD automated test integrations, we guarantee 100% source-code sovereignty and absolute SLA performance.
                </p>
                <div class="space-y-3 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                    <div>🟢 <strong>African Market Expertise:</strong> Over 10 years of combined systems deployment.</div>
                    <div>⚡ <strong>Guaranteed Uptime:</strong> Containerized environments with 99.99% platform SLAs.</div>
                    <div>🛡️ <strong>Compliance:</strong> ISO-27001 standard data encryption and KRA tax audit exports.</div>
                </div>
            </div>

            <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Trust item 1 -->
                <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-200/50 dark:border-slate-800">
                    <span class="text-lg">⚙️</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wider mt-2">Technologies Used</h4>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">We build with PHP Laravel 12, React 19, TypeScript, PostgreSQL, and Docker containers for peak speed.</p>
                </div>

                <!-- Trust item 2 -->
                <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-200/50 dark:border-slate-800">
                    <span class="text-lg">🔒</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wider mt-2">Security Standards</h4>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">AES-256 local database snapshot backups, fine-grained client access privileges, and SSL certificates.</p>
                </div>

                <!-- Trust item 3 -->
                <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-200/50 dark:border-slate-800">
                    <span class="text-lg">☁️</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wider mt-2">Cloud Infrastructure</h4>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">Scale-to-zero server fleets on Google Cloud Run delivering sub-second static file serving.</p>
                </div>

                <!-- Trust item 4 -->
                <div class="bg-slate-50 dark:bg-slate-950 p-5 rounded-2xl border border-slate-200/50 dark:border-slate-800">
                    <span class="text-lg">🛠️</span>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wider mt-2">Enterprise Support</h4>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">Continuous developer tracking logs, Zoom screen-shares, and ticketing system integration.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     BUILT FOR AFRICA SECTION
     ========================================== -->
<section id="built-for-africa" class="relative py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300 overflow-hidden border-b border-slate-100 dark:border-slate-900">
    <!-- Abstract world/Africa map ambient grid pattern background -->
    <div class="absolute inset-0 pointer-events-none opacity-45 dark:opacity-25 flex items-center justify-center">
        <!-- SVG Vector representation of abstract nodes/connections representing the African continent or abstract geography -->
        <svg class="w-[800px] h-[600px] text-indigo-500/10 dark:text-indigo-400/5" viewBox="0 0 800 600" fill="none">
            <path d="M400,100 C450,150 500,120 520,180 C540,240 510,280 550,330 C590,380 540,430 510,480 C480,530 430,500 390,520 C350,540 310,480 290,440 C270,400 310,360 280,310 C250,260 290,210 320,170 C350,130 350,50 400,100 Z" stroke="currentColor" stroke-width="2" stroke-dasharray="8 8" />
            <circle cx="400" cy="100" r="4" fill="currentColor" />
            <circle cx="520" cy="180" r="4" fill="currentColor" />
            <circle cx="550" cy="330" r="4" fill="currentColor" />
            <circle cx="510" cy="480" r="4" fill="currentColor" />
            <circle cx="390" cy="520" r="4" fill="currentColor" />
            <circle cx="290" cy="440" r="4" fill="currentColor" />
            <circle cx="280" cy="310" r="4" fill="currentColor" />
            <circle cx="320" cy="170" r="4" fill="currentColor" />
            <path d="M400,100 L520,180 L550,330 L510,480 L390,520 L290,440 L280,310 L320,170 Z" fill="currentColor" class="opacity-10" />
        </svg>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <x-section-title 
            badge="Regional Sovereignty"
            title="Built for Africa. Designed for the World."
            subtitle="Engineered specifically for modern African businesses while meeting international enterprise standards. Experience deep regional integration combined with global architectural capabilities."
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mt-12">
            
            <!-- Card 1: M-PESA -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">🇲🇿 🇰🇪 🇹🇿</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Native M-PESA</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Direct Daraja API listeners with instant STK Push triggers, callback validation, and ledger entries.</p>
            </div>

            <!-- Card 2: Multi-Currency -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">💵 💶 💷</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Multi-Currency</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Transact in KES, USD, EUR, and UGX seamlessly with daily dynamic exchange-rate syncing.</p>
            </div>

            <!-- Card 3: Multi-Language -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">🌍 🗣️</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Multi-Language</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Localize layouts, emails, and invoices. English, Kiswahili, and French fully supported.</p>
            </div>

            <!-- Card 4: Multi-Tenant -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">🏢 🛡️</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Multi-Tenant SaaS</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Deploy secure tenant subdomain environments with strictly isolated workspaces and team permissions.</p>
            </div>

            <!-- Card 5: VAT Ready -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">💳 🧾</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">VAT &amp; Tax Ready</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Automated regional tax calculation, localized invoice PDFs, and direct compliance audit export.</p>
            </div>

            <!-- Card 6: AI Automation -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">🤖 ⚡</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">AI-Powered</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Leverage server-side Gemini models for smart lead classification, CRM logs, and support routing.</p>
            </div>

            <!-- Card 7: Security -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">🔒 🔐</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Enterprise Security</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">SSL endpoints, automated database snapshot backups, and fine-grained IAM action logs.</p>
            </div>

            <!-- Card 8: Cloud Native -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">☁️ 🛸</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Cloud Native</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Built on containerized scale-to-zero server fleets delivering maximum resource efficiency.</p>
            </div>

            <!-- Card 9: Business Intelligence -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">📊 📈</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Business Intel</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Analyze pipeline conversion metrics, monthly recurring revenue, and active project SLAs.</p>
            </div>

            <!-- Card 10: Event-Driven -->
            <div class="group bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 text-left">
                <div class="text-xl mb-3">⚡ 🏹</div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white font-display uppercase tracking-wide">Event-Driven</h4>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">Sub-millisecond pub/sub hooks dispatching alerts on task resolution or payment success.</p>
            </div>

        </div>
    </div>
</section>

<!-- ==========================================
     TESTIMONIALS SECTION (ROTATING GRID)
     ========================================== -->
<section id="testimonials" class="py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-section-title 
            badge="Customer Satisfaction"
            title="Endorsed by Top Engineering Founders"
            subtitle="Explore how regional software development companies, digital agencies, and financial entities use JUANET to power daily growth."
        />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Testimonial 1 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-8 rounded-2xl shadow-sm flex flex-col justify-between hover:shadow-md transition">
                <div class="space-y-4">
                    <!-- Rating Stars -->
                    <div class="flex text-amber-400 text-xs">★★★★★</div>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 italic leading-relaxed">
                        "Enforcing Lipa na M-PESA STK Push triggers inside our project delivery modules was an absolute nightmare with standard systems. JUANET brought our CRM opportunities and real-time payment ledgers into a singular workspace. We launched our subscription model in under four days."
                    </p>
                </div>
                <div class="mt-6 flex items-center gap-3">
                    <x-avatar name="Wambui Kamau" size="sm" />
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white block leading-none">Wambui Kamau</h4>
                        <span class="text-[10px] text-slate-400">Engineering Principal, Safaricom Tech Dev</span>
                    </div>
                </div>
            </div>

            <!-- Testimonial 2 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-8 rounded-2xl shadow-sm flex flex-col justify-between hover:shadow-md transition border-l-4 border-l-indigo-600">
                <div class="space-y-4">
                    <div class="flex text-amber-400 text-xs">★★★★★</div>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 italic leading-relaxed">
                        "The information density and visual layout are magnificent. Our teams migrated completely from standard bloated CRM templates to JUANET's Vercel-inspired high-contrast dark workspaces. Daily developer throughput has surged by over 38% almost immediately."
                    </p>
                </div>
                <div class="mt-6 flex items-center gap-3">
                    <x-avatar name="Faraji Mwenda" size="sm" />
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white block leading-none">Faraji Mwenda</h4>
                        <span class="text-[10px] text-slate-400">Product Lead, Apex Logic East Africa</span>
                    </div>
                </div>
            </div>

            <!-- Testimonial 3 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-8 rounded-2xl shadow-sm flex flex-col justify-between hover:shadow-md transition">
                <div class="space-y-4">
                    <div class="flex text-amber-400 text-xs">★★★★★</div>
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 italic leading-relaxed">
                        "Having our digital API marketplace catalog, client invoices, agile task boards, and Google Gemini automations live in a singular responsive application is incredible. Perfect developer experience."
                    </p>
                </div>
                <div class="mt-6 flex items-center gap-3">
                    <x-avatar name="Josphat Juan" size="sm" />
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white block leading-none">Josphat Juan</h4>
                        <span class="text-[10px] text-slate-400">Founder, Kijiji Digital Solutions</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ==========================================
     PRICING PREVIEW SECTION
     ========================================== -->
<section id="pricing" class="py-24 bg-white dark:bg-slate-950 border-t border-slate-100 dark:border-slate-900 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-section-title 
            badge="Predictable Pricing"
            title="Scalable Plans Configured for Modern Operations"
            subtitle="Choose a plan tailored to your team's operational velocity. All tiers feature zero local payment transaction commissions."
        />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Tier 1: Starter -->
            <x-pricing-card 
                name="Starter Suite"
                price="Free"
                period=""
                description="Perfect for sandbox testing, individual developers, and local CRM pipeline exploration."
                :features="['1 Active CRM Workspace', 'Up to 100 Contacts Limit', 'Daraja sandbox testing credentials', 'Standard Markdown CMS Blogs']"
                ctaText="Start Free Trial"
                ctaHref="/register"
            />

            <!-- Tier 2: Business -->
            <x-pricing-card 
                name="Business Professional"
                price="KES 6,500"
                period="/mo"
                description="Ideal for scaling agencies executing payment portals and client task tracking."
                :features="['5 Fully Synced Workspaces', 'Up to 5,000 CRM Contacts', 'Real Lipa Na M-PESA STK pushes', 'Scrum Sprint Project Tracker', 'Standard Email Support SLA']"
                popular="true"
                ctaText="Get Started Professional"
                ctaHref="/register"
            />

            <!-- Tier 3: Enterprise -->
            <x-pricing-card 
                name="Enterprise Authority"
                price="KES 24,000"
                period="/mo"
                description="Designed for high-volume regional payment ledgers and highly regulated operations."
                :features="['Unlimited CRM Workspaces', 'Unlimited Contact Storage', 'Dedicated Secure Database Nodes', 'Gemini AI Intelligent Integrations', 'Custom OAuth Login Portals', '24/7 Telephone Escalation Support']"
                ctaText="Contact Enterprise Sales"
                ctaHref="/register"
            />

        </div>
    </div>
</section>

<!-- ==========================================
     FAQ SECTION (ACCORDION)
     ========================================== -->
<section id="faq" class="py-24 bg-slate-50 dark:bg-slate-950 transition-colors duration-300" x-data="{ activeFaq: null }">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-section-title 
            badge="Frequently Asked Questions"
            title="Enterprise Operational Clarifications"
            subtitle="Have questions about Daraja M-PESA payment flows, CRM account syncing, or database residency? We have answers."
        />

        <div class="space-y-4">
            
            <!-- Question 1 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-xl overflow-hidden transition-all duration-200">
                <button @click="activeFaq = (activeFaq === 1 ? null : 1)" class="w-full text-left px-6 py-4 flex justify-between items-center focus:outline-none cursor-pointer">
                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white font-display">How fast are M-PESA Daraja callback updates processed?</span>
                    <span class="text-xs font-bold text-slate-400" x-text="activeFaq === 1 ? '−' : '+'"></span>
                </button>
                <div x-show="activeFaq === 1" x-collapse class="px-6 pb-5 text-xs text-slate-500 dark:text-slate-400 border-t border-slate-50 dark:border-slate-800/60 pt-4 leading-relaxed" x-cloak>
                    Our integrated M-PESA Daraja listener parses transactions in sub-100ms speeds once Safaricom triggers the STK callback. Invoices, financial ledgers, and CRM pipelines are updated synchronously across all active workspaces instantly.
                </div>
            </div>

            <!-- Question 2 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-xl overflow-hidden transition-all duration-200">
                <button @click="activeFaq = (activeFaq === 2 ? null : 2)" class="w-full text-left px-6 py-4 flex justify-between items-center focus:outline-none cursor-pointer">
                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white font-display">Does JUANET enforce secure data residency compliant with East Africa frameworks?</span>
                    <span class="text-xs font-bold text-slate-400" x-text="activeFaq === 2 ? '−' : '+'"></span>
                </button>
                <div x-show="activeFaq === 2" x-collapse class="px-6 pb-5 text-xs text-slate-500 dark:text-slate-400 border-t border-slate-50 dark:border-slate-800/60 pt-4 leading-relaxed" x-cloak>
                    Yes. All persistent databases, file systems, and CRM registers are hosted inside secure Nairobi-based enterprise cloud data centers, adhering perfectly to GDPR-aligned regional data protection regulations.
                </div>
            </div>

            <!-- Question 3 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-xl overflow-hidden transition-all duration-200">
                <button @click="activeFaq = (activeFaq === 3 ? null : 3)" class="w-full text-left px-6 py-4 flex justify-between items-center focus:outline-none cursor-pointer">
                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white font-display">Can we inject custom Google Workspace OAuth scopes?</span>
                    <span class="text-xs font-bold text-slate-400" x-text="activeFaq === 3 ? '−' : '+'"></span>
                </button>
                <div x-show="activeFaq === 3" x-collapse class="px-6 pb-5 text-xs text-slate-500 dark:text-slate-400 border-t border-slate-50 dark:border-slate-800/60 pt-4 leading-relaxed" x-cloak>
                    Certainly. Enterprise workspaces can deploy unique custom client secrets to support direct calendar bookings, sheet automations, document summaries, or automated secure email responses using client scopes.
                </div>
            </div>

            <!-- Question 4 -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-xl overflow-hidden transition-all duration-200">
                <button @click="activeFaq = (activeFaq === 4 ? null : 4)" class="w-full text-left px-6 py-4 flex justify-between items-center focus:outline-none cursor-pointer">
                    <span class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white font-display">Is custom theme dark-mode support consistent across components?</span>
                    <span class="text-xs font-bold text-slate-400" x-text="activeFaq === 4 ? '−' : '+'"></span>
                </button>
                <div x-show="activeFaq === 4" x-collapse class="px-6 pb-5 text-xs text-slate-500 dark:text-slate-400 border-t border-slate-50 dark:border-slate-800/60 pt-4 leading-relaxed" x-cloak>
                    Yes, absolutely. Every premium blade component is completely dark-mode optimized, dynamically utilizing high-contrast, beautiful zinc/slate gray palettes to maintain exceptional WCAG AA accessibility.
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ==========================================
     FINAL CTA CONVERSION PANEL
     ========================================== -->
<section id="final-cta" class="py-24 bg-white dark:bg-slate-950 transition-colors duration-300">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative bg-gradient-to-tr from-indigo-600 via-indigo-700 to-violet-800 rounded-3xl overflow-hidden p-8 md:p-16 text-center text-white shadow-xl">
            <!-- Decorative light patterns -->
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_right,rgba(255,255,255,0.1),transparent_50%)] pointer-events-none"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.05),transparent_40%)] pointer-events-none"></div>

            <div class="relative z-10 space-y-6 max-w-2xl mx-auto">
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-black font-display tracking-tight leading-tight">Ready to Transform Your Business?</h2>
                <p class="text-xs sm:text-sm md:text-base text-indigo-100 leading-relaxed font-light">
                    Join thousands of ambitious East African businesses consolidating their CRM pipelines, project trackers, digital marketplaces, and billing ledgers under a single, secure enterprise-grade workspace.
                </p>
                <div class="pt-4 flex flex-wrap gap-4 justify-center items-center">
                    <a href="/register" class="inline-flex items-center justify-center bg-white text-indigo-700 font-bold text-xs sm:text-sm px-6 py-3 rounded-xl hover:bg-slate-50 hover:-translate-y-0.5 transition-all duration-200 shadow-md">
                        Start 14-day Free Trial
                    </a>
                    <button @click="alert('✓ Demo calendar booking: A dedicated representative from Nairobi HQ will email you at: juanetsolutions@gmail.com within 2 hours to coordinate a screen-share.')" class="inline-flex items-center justify-center bg-indigo-500/30 border border-white/20 text-white font-bold text-xs sm:text-sm px-6 py-3 rounded-xl hover:bg-indigo-500/50 hover:-translate-y-0.5 transition-all duration-200">
                        Book Live Screen-Share
                    </button>
                </div>
                <p class="text-[10px] text-indigo-200/80 font-mono">Zero lock-in &middot; Self-service sandbox migration ready</p>
            </div>
        </div>
    </div>
</section>

<!-- Test Drawer Component -->
<x-drawer name="test-drawer" title="Design System Operations Drawer">
    <div class="space-y-6">
        <p>This drawer demonstrates the slide-in Alpine transition. It can handle full form inputs, details tables, or diagnostic telemetry logs.</p>
        
        <x-input label="Direct Database Connection URI" name="db_uri" value="postgresql://user:***@localhost:5432/juanet" disabled />
        
        <div class="bg-slate-50 dark:bg-slate-950 p-4 rounded-xl border border-slate-100 dark:border-slate-800 text-xs space-y-2">
            <div class="font-bold">Diagnostics</div>
            <div class="flex justify-between"><span>Laravel Version</span><span class="font-mono text-[11px] text-indigo-600 dark:text-indigo-400">12.0.0</span></div>
            <div class="flex justify-between"><span>Tailwind CSS</span><span class="font-mono text-[11px] text-indigo-600 dark:text-indigo-400">Playground CDN</span></div>
        </div>
    </div>
    <x-slot name="footer">
        <x-button variant="outline" size="sm" @click="$dispatch('close-drawer-test-drawer')">
            Close Panel
        </x-button>
        <x-button variant="primary" size="sm" @click="alert('✓ Operation complete'); $dispatch('close-drawer-test-drawer')">
            Apply Settings
        </x-button>
    </x-slot>
</x-drawer>

<!-- Toast Controller Instance -->
<x-toast />

@endsection
