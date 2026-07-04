@extends('layouts.public')

@section('title', 'Connect with Our Engineering Office — JUANET')
@section('meta_description', 'Contact the JUANET team in Nairobi. Request a website development quote, plan custom enterprise SaaS platforms, or organize M-PESA integrations.')

@section('content')
<div class="relative py-20 bg-slate-50 dark:bg-slate-950 transition-colors duration-300"
     x-data="{ 
        step: 1,
        submitting: false,
        submitted: false,
        interest: 'Build My Website',
        budget: 'KES 100k - KES 300k',
        name: '',
        email: '',
        phone: '',
        company: '',
        scope: '',
        submitLead() {
            if(!this.name || !this.email) {
                $dispatch('trigger-toast', { message: '⚠ Name and Email are required.', type: 'error' });
                return;
            }
            this.submitting = true;
            setTimeout(() => {
                this.submitting = false;
                this.submitted = true;
                $dispatch('trigger-toast', { message: '✓ Lead created in Enterprise CRM pipeline!', type: 'success' });
            }, 1500);
        }
     }">
    <!-- Background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[5%] left-[-10%] w-[50%] h-[50%] bg-indigo-500/5 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-[5%] right-[-10%] w-[50%] h-[50%] bg-violet-500/5 rounded-full blur-[140px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Title -->
        <x-section-title 
            badge="Enterprise Contact"
            title="Initiate Your Next Digital Venture"
            subtitle="Request customized technology proposals, organize a live system demonstration, or plan enterprise integrations with our Nairobi HQ engineers."
        />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mt-12 items-start">
            
            <!-- Left Panel: Company info & Contact detail cards -->
            <div class="lg:col-span-5 space-y-8 text-left">
                <div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-8 rounded-3xl space-y-6">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white font-display">Nairobi Headquarters</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        Our centralized design studio and engineering container clusters are operating directly from Kilimani, Nairobi. Drop us an email or file an automated scoping ticket below.
                    </p>
                    
                    <div class="space-y-4 text-xs">
                        <div class="flex items-center gap-3">
                            <span class="h-8 w-8 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center text-sm">📍</span>
                            <div>
                                <span class="font-bold text-slate-700 dark:text-slate-300 block">Location</span>
                                <span class="text-slate-400">Kilimani Road, Nairobi, Kenya</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="h-8 w-8 bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 rounded-xl flex items-center justify-center text-sm">✉️</span>
                            <div>
                                <span class="font-bold text-slate-700 dark:text-slate-300 block">General Inquiries</span>
                                <span class="text-slate-400 font-mono">juanetsolutions@gmail.com</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="h-8 w-8 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center text-sm">📞</span>
                            <div>
                                <span class="font-bold text-slate-700 dark:text-slate-300 block">Telephone Hotline</span>
                                <span class="text-slate-400 font-mono">+254 700 000 000</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-indigo-600 rounded-3xl p-6 sm:p-8 text-white space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-indigo-200">CRM Queue Active</span>
                    </div>
                    <h4 class="text-base sm:text-lg font-bold font-display">Fast-Track Response SLA</h4>
                    <p class="text-xs text-indigo-100 leading-relaxed font-light">
                        Every project scoping questionnaire submitted is automatically routed into our team's active sprint queues. Over 98% of qualified enterprise leads receive structural feedback under 2 hours.
                    </p>
                </div>
            </div>

            <!-- Right Panel: Interactive Lead Capture Wizard -->
            <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/80 p-6 sm:p-10 rounded-3xl shadow-premium dark:shadow-premium-dark relative">
                
                <!-- Success State -->
                <div x-show="submitted" class="text-center py-12 space-y-6" x-cloak>
                    <span class="h-16 w-16 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-4xl mx-auto">✓</span>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white font-display">We have Captured Your Scope!</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed max-w-lg mx-auto">
                        Thank you, <span class="font-bold text-slate-900 dark:text-white" x-text="name"></span>. A new CRM contact record has been initialized for <span class="font-bold text-slate-900 dark:text-white" x-text="email"></span>. An enterprise systems lead will email you a custom quote and technical scope sheets within 2 hours.
                    </p>
                    <button @click="submitted = false; name = ''; email = ''; phone = ''; company = ''; scope = ''; step = 1" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 shadow-md hover:-translate-y-0.5 transition cursor-pointer">
                        Submit New Request
                    </button>
                </div>

                <!-- Wizard Steps -->
                <div x-show="!submitted">
                    
                    <!-- Progress Bar -->
                    <div class="mb-8">
                        <div class="flex justify-between items-center text-[10px] font-mono text-slate-400 uppercase tracking-wider mb-2">
                            <span>Step <span x-text="step"></span> of 3</span>
                            <span x-text="step === 1 ? 'Primary Objective' : (step === 2 ? 'Project Scope' : 'Your Information')"></span>
                        </div>
                        <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-600 transition-all duration-300" :style="'width: ' + (step === 1 ? '33%' : (step === 2 ? '66%' : '100%'))"></div>
                        </div>
                    </div>

                    <!-- Step 1: Select Interest -->
                    <div x-show="step === 1" class="space-y-6">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">What is your primary technological objective?</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Option 1 -->
                            <label class="border p-4 rounded-2xl flex items-start gap-3 cursor-pointer hover:border-indigo-500 transition-all"
                                   :class="interest === 'Build My Website' ? 'border-indigo-600 bg-indigo-50/20 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                <input type="radio" x-model="interest" value="Build My Website" class="mt-1 h-3.5 w-3.5 text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                                <div class="text-left">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white block">Build My Website</span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5">Custom layouts, lightning-fast pages, full SEO setup.</span>
                                </div>
                            </label>

                            <!-- Option 2 -->
                            <label class="border p-4 rounded-2xl flex items-start gap-3 cursor-pointer hover:border-indigo-500 transition-all"
                                   :class="interest === 'Develop My SaaS' ? 'border-indigo-600 bg-indigo-50/20 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                <input type="radio" x-model="interest" value="Develop My SaaS" class="mt-1 h-3.5 w-3.5 text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                                <div class="text-left">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white block">Develop My SaaS</span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5">Multi-tenant structures, Isolated registers.</span>
                                </div>
                            </label>

                            <!-- Option 3 -->
                            <label class="border p-4 rounded-2xl flex items-start gap-3 cursor-pointer hover:border-indigo-500 transition-all"
                                   :class="interest === 'Automate My Business' ? 'border-indigo-600 bg-indigo-50/20 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                <input type="radio" x-model="interest" value="Automate My Business" class="mt-1 h-3.5 w-3.5 text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                                <div class="text-left">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white block">Automate My Business</span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5">AI integrations, automated task pipelines.</span>
                                </div>
                            </label>

                            <!-- Option 4 -->
                            <label class="border p-4 rounded-2xl flex items-start gap-3 cursor-pointer hover:border-indigo-500 transition-all"
                                   :class="interest === 'Create My Brand' ? 'border-indigo-600 bg-indigo-50/20 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                <input type="radio" x-model="interest" value="Create My Brand" class="mt-1 h-3.5 w-3.5 text-indigo-600 border-slate-300 focus:ring-indigo-500" />
                                <div class="text-left">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white block">Create My Brand</span>
                                    <span class="text-[10px] text-slate-400 block mt-0.5"> Bespoke logos, branding identity guides.</span>
                                </div>
                            </label>
                        </div>
                        
                        <div class="flex justify-end pt-4">
                            <button type="button" @click="step = 2" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 cursor-pointer">Continue &rarr;</button>
                        </div>
                    </div>

                    <!-- Step 2: Budget & Scope details -->
                    <div x-show="step === 2" class="space-y-6" x-cloak>
                        <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Target Budget &amp; Scope Requirements</h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-2">Select Estimated Budget</label>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <label class="border p-3 rounded-xl flex items-center gap-2 cursor-pointer" :class="budget === 'KES 100k - KES 300k' ? 'border-indigo-500 bg-indigo-50/10 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                        <input type="radio" x-model="budget" value="KES 100k - KES 300k" />
                                        <span class="text-slate-800 dark:text-white font-mono">100k - 300k KES</span>
                                    </label>
                                    <label class="border p-3 rounded-xl flex items-center gap-2 cursor-pointer" :class="budget === 'KES 300k - KES 1M' ? 'border-indigo-500 bg-indigo-50/10 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                        <input type="radio" x-model="budget" value="KES 300k - KES 1M" />
                                        <span class="text-slate-800 dark:text-white font-mono">300k - 1M KES</span>
                                    </label>
                                    <label class="border p-3 rounded-xl flex items-center gap-2 cursor-pointer" :class="budget === 'KES 1M - KES 5M' ? 'border-indigo-500 bg-indigo-50/10 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                        <input type="radio" x-model="budget" value="KES 1M - KES 5M" />
                                        <span class="text-slate-800 dark:text-white font-mono">1M - 5M KES</span>
                                    </label>
                                    <label class="border p-3 rounded-xl flex items-center gap-2 cursor-pointer" :class="budget === 'KES 5M+' ? 'border-indigo-500 bg-indigo-50/10 dark:bg-indigo-950/10' : 'border-slate-200 dark:border-slate-800'">
                                        <input type="radio" x-model="budget" value="KES 5M+" />
                                        <span class="text-slate-800 dark:text-white font-mono">5M+ KES Enterprise</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-2">Scoping Specifications (Optional)</label>
                                <textarea x-model="scope" rows="3" placeholder="List any design parameters, API integrations, or CRM custom modules required..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-between pt-4">
                            <button type="button" @click="step = 1" class="inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs px-6 py-3 cursor-pointer">&larr; Back</button>
                            <button type="button" @click="step = 3" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 cursor-pointer font-display">Next Step &rarr;</button>
                        </div>
                    </div>

                    <!-- Step 3: Identity & Submission -->
                    <div x-show="step === 3" class="space-y-6" x-cloak>
                        <h4 class="text-base font-bold text-slate-900 dark:text-white font-display">Your Contact Information</h4>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Full Name</label>
                                    <input type="text" x-model="name" placeholder="E.g., Jane Doe" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Email Address</label>
                                    <input type="email" x-model="email" placeholder="jane@company.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Telephone Number</label>
                                    <input type="text" x-model="phone" placeholder="+254 700 ..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase font-mono font-bold text-slate-400 mb-1">Company Name</label>
                                    <input type="text" x-model="company" placeholder="E.g., Nairobi Corp Ltd" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between pt-4">
                            <button type="button" @click="step = 2" class="inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs px-6 py-3 cursor-pointer">&larr; Back</button>
                            <button type="button" @click="submitLead()" :disabled="submitting" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-6 py-3 shadow-md hover:-translate-y-0.5 transition cursor-pointer">
                                <span x-show="!submitting">Submit Scoping Questionnaire &rarr;</span>
                                <span x-show="submitting">Saving in CRM...</span>
                            </button>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

<x-toast />
@endsection
