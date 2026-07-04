@extends('layouts.public')

@section('title', 'Engineering Case Studies & Portfolio — JUANET')
@section('meta_description', 'Explore completed and upcoming projects delivered by JUANET Solutions, including multi-tenant SaaS panels, M-PESA integrations, and bespoke corporate branding.')

@section('content')
<div class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
    <!-- Ambient ambient glow -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[20%] left-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[20%] right-[-10%] w-[50%] h-[50%] bg-violet-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Title -->
        <x-section-title 
            badge="Engineering Deliverables"
            title="Completed Projects &amp; Case Studies"
            subtitle="Explore how we deliver mission-critical software solutions, regional billing interfaces, and bespoke brand platforms to leading corporations."
        />

        <!-- Filter Sub-header -->
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200/60 dark:border-slate-800/80 pb-6 gap-4">
            <div class="flex flex-wrap gap-2 text-xs font-mono font-bold uppercase tracking-wider text-slate-500">
                <span class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg">All Case Studies</span>
                <span class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-lg hover:border-slate-300 dark:hover:border-slate-700 transition cursor-pointer">CRM Systems</span>
                <span class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-lg hover:border-slate-300 dark:hover:border-slate-700 transition cursor-pointer">SaaS Platforms</span>
                <span class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-lg hover:border-slate-300 dark:hover:border-slate-700 transition cursor-pointer">Websites</span>
            </div>
            <div class="text-[11px] text-slate-400 font-mono">Showcasing real metrics and design system deliverables</div>
        </div>

        <!-- Case Studies Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">
            
            <!-- Case Study 1: Apex Logistics CRM -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition text-left flex flex-col justify-between">
                <div>
                    <!-- Visual Cover -->
                    <div class="h-48 bg-gradient-to-tr from-slate-900 to-indigo-950 p-6 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute right-[-20%] bottom-[-20%] text-[120px] opacity-10">👥</div>
                        <div class="flex justify-between items-start">
                            <span class="text-[9px] font-mono font-bold text-indigo-400 bg-indigo-950/80 border border-indigo-800/50 px-2 py-0.5 rounded uppercase">Active System</span>
                            <span class="text-[9px] font-mono font-bold text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded uppercase">Success KES 48M+</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-mono tracking-widest block">Logistics &amp; Transport</span>
                            <h4 class="text-lg font-black text-white font-display mt-0.5">Apex Corporate CRM Platform</h4>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            A secure multi-workspace CRM tailored for East African shipping agents. Integrates live customer portals, dynamic invoice generators, and Lipa Na M-PESA Daraja callback listeners.
                        </p>
                        <div class="flex flex-wrap gap-1.5 pt-2">
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Laravel 12</span>
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Daraja v2</span>
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">PostgreSQL</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-6 pt-2">
                    <button @click="alert('✓ Case Study: Apex Logistics. Complete CRM dashboard with fine-grained tracking. Successfully increased payment collection rates by over 42% utilizing automated SMS and STK pushes.')" class="w-full inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200/60 text-slate-700 hover:bg-slate-100 dark:bg-slate-800/40 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800 text-xs font-bold py-2.5 transition cursor-pointer">
                        View Complete Case Study
                    </button>
                </div>
            </div>

            <!-- Case Study 2: Kijiji Marketplace -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition text-left flex flex-col justify-between">
                <div>
                    <div class="h-48 bg-gradient-to-tr from-slate-900 to-violet-950 p-6 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute right-[-20%] bottom-[-20%] text-[120px] opacity-10">📦</div>
                        <div class="flex justify-between items-start">
                            <span class="text-[9px] font-mono font-bold text-violet-400 bg-violet-950/80 border border-violet-800/50 px-2 py-0.5 rounded uppercase">Active System</span>
                            <span class="text-[9px] font-mono font-bold text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded uppercase">SLA 99.98%</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-mono tracking-widest block">E-Commerce &amp; Retail</span>
                            <h4 class="text-lg font-black text-white font-display mt-0.5">Kijiji Digital Assets Portal</h4>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            Multi-tenant digital assets catalog featuring sub-second global search capabilities, automated watermark filters, instant licensing key delivery, and real-time ledger accounting.
                        </p>
                        <div class="flex flex-wrap gap-1.5 pt-2">
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Tailwind CSS</span>
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">AlpineJS</span>
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Supabase PG</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-6 pt-2">
                    <button @click="alert('✓ Case Study: Kijiji Marketplace. Built scale-to-zero containerized infrastructure delivering rapid key dispatch. Reduced monthly operational server overhead by over 60%.')" class="w-full inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200/60 text-slate-700 hover:bg-slate-100 dark:bg-slate-800/40 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800 text-xs font-bold py-2.5 transition cursor-pointer">
                        View Complete Case Study
                    </button>
                </div>
            </div>

            <!-- Case Study 3: Safaricom Analytics Core (AI Integration) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-md transition text-left flex flex-col justify-between">
                <div>
                    <div class="h-48 bg-gradient-to-tr from-slate-900 to-emerald-950 p-6 flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute right-[-20%] bottom-[-20%] text-[120px] opacity-10">🤖</div>
                        <div class="flex justify-between items-start">
                            <span class="text-[9px] font-mono font-bold text-emerald-400 bg-emerald-950/80 border border-emerald-800/50 px-2 py-0.5 rounded uppercase">Active System</span>
                            <span class="text-[9px] font-mono font-bold text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded uppercase">Gemini Core</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase font-mono tracking-widest block">Artificial Intelligence</span>
                            <h4 class="text-lg font-black text-white font-display mt-0.5">Safaricom Analytics Integrations</h4>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                            Cognitive business automation pipeline running Google Gemini API models. Analyzes raw daily sales logs, structures multi-currency revenue vectors, and exports financial ledger projections.
                        </p>
                        <div class="flex flex-wrap gap-1.5 pt-2">
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Gemini SDK</span>
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">Server-Side AI</span>
                            <span class="text-[9px] font-mono font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded">VAT Audits</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-6 pt-2">
                    <button @click="alert('✓ Case Study: Safaricom Analytics Core. Deployed fine-tuned semantic tagging schemas that categorizes client interactions with 98% accuracy. Streamlines administrative processes instantly.')" class="w-full inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200/60 text-slate-700 hover:bg-slate-100 dark:bg-slate-800/40 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800 text-xs font-bold py-2.5 transition cursor-pointer">
                        View Complete Case Study
                    </button>
                </div>
            </div>

        </div>

        <!-- Scoping Call-to-Action -->
        <div class="mt-20 bg-indigo-600 rounded-3xl p-8 md:p-12 shadow-xl text-center text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_right,rgba(255,255,255,0.1),transparent_50%)] pointer-events-none"></div>
            <div class="relative z-10 max-w-2xl mx-auto space-y-6">
                <h3 class="text-2xl sm:text-3xl font-extrabold font-display leading-tight">Ready to Engineer Your Own Digital Solution?</h3>
                <p class="text-xs sm:text-sm text-indigo-100 leading-relaxed font-light">
                    Join leading regional enterprises in Kenya and East Africa. Let our expert engineering team scoping, prototype, and build your specialized CRM, SaaS, custom website, or billing integration.
                </p>
                <div class="pt-2 flex flex-wrap gap-4 justify-center items-center">
                    <a href="/quote-request" class="inline-flex items-center justify-center bg-white text-indigo-700 font-bold text-xs px-6 py-3.5 rounded-xl hover:bg-slate-50 hover:-translate-y-0.5 transition shadow-sm">
                        Request a Project Quote
                    </a>
                    <a href="/contact" class="inline-flex items-center justify-center bg-indigo-500/30 border border-white/20 text-white font-bold text-xs px-6 py-3.5 rounded-xl hover:bg-indigo-500/50 hover:-translate-y-0.5 transition">
                        Book Developer Consult
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
