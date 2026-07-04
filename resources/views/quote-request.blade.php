@extends('layouts.public')

@section('title', 'Request a Customized Project Quote — JUANET')
@section('meta_description', 'Request a custom quote for enterprise web portals, SaaS platforms, SEO campaigns, or branding design systems with JUANET Nairobi.')

@section('content')
<div class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300"
     x-data="{ 
        submitting: false,
        submitted: false,
        name: '',
        email: '',
        phone: '',
        company: '',
        projectType: 'Bespoke Web Application',
        budget: 'KES 250k - KES 500k',
        details: '',
        submitQuote() {
            if(!this.name || !this.email) {
                $dispatch('trigger-toast', { message: '⚠ Name and Email are required parameters.', type: 'error' });
                return;
            }
            this.submitting = true;
            setTimeout(() => {
                this.submitting = false;
                this.submitted = true;
                $dispatch('trigger-toast', { message: '✓ System quote request captured!', type: 'success' });
            }, 1200);
        }
     }">
    <!-- Background element -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[10%] left-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/5 rounded-full blur-[120px]"></div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 relative z-10">
        <!-- Section Title -->
        <x-section-title 
            badge="Project Quotation"
            title="Custom Software &amp; Platform Quote Request"
            subtitle="Outline your target project, and we will compile a comprehensive systems proposal including milestone roadmaps and granular KES cost structures."
        />

        <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 rounded-3xl p-6 sm:p-10 shadow-premium dark:shadow-premium-dark text-left mt-12">
            
            <!-- Success State -->
            <div x-show="submitted" class="text-center py-12 space-y-6" x-cloak>
                <span class="h-16 w-16 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-4xl mx-auto">✓</span>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-display">Quote Scope Received!</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed max-w-lg mx-auto">
                    Thank you, <span class="font-bold text-slate-900 dark:text-white" x-text="name"></span>. We have generated a custom systems scope token for <span class="font-bold text-slate-900 dark:text-white" x-text="email"></span>. An engineering lead from Nairobi kilimani office will email you a custom breakdown in less than 2 hours.
                </p>
                <button @click="submitted = false; name = ''; email = ''; phone = ''; company = ''; details = ''" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 transition cursor-pointer">
                    Request Another Quote
                </button>
            </div>

            <!-- Form -->
            <div x-show="!submitted" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Your Full Name</label>
                        <input type="text" x-model="name" placeholder="John Doe" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Email Address</label>
                        <input type="email" x-model="email" placeholder="john@company.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Phone / WhatsApp</label>
                        <input type="text" x-model="phone" placeholder="+254 700 ..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Company / Organization</label>
                        <input type="text" x-model="company" placeholder="Nairobi Agency" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Project Classification</label>
                        <select x-model="projectType" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option>High-Performance Landing Page</option>
                            <option>Bespoke Web Application</option>
                            <option>Multi-tenant SaaS Platform</option>
                            <option>Cross-Platform Mobile App</option>
                            <option>E-commerce / Marketplace Portal</option>
                            <option>Branding Identity Deck</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Estimated Budget (KES)</label>
                        <select x-model="budget" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option>KES 100k - KES 250k</option>
                            <option>KES 250k - KES 500k</option>
                            <option>KES 500k - KES 1M</option>
                            <option>KES 1M - KES 3M</option>
                            <option>KES 3M+</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Project Technical Specifications &amp; Requirements</label>
                    <textarea x-model="details" rows="4" placeholder="Detail your integrations (e.g., M-PESA STK Push, custom dashboards, administrative workflow systems, SMS routers)..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition"></textarea>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="button" @click="submitQuote()" :disabled="submitting" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-8 py-3.5 shadow-md hover:-translate-y-0.5 transition cursor-pointer">
                        <span x-show="!submitting">Generate Systems Proposal &rarr;</span>
                        <span x-show="submitting">Saving Request...</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<x-toast />
@endsection
