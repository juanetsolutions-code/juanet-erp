@extends('layouts.app')

@section('header', 'Compile Proposal & Quotation')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    sections: [
        { title: '1. Executive Summary', content: 'We are pleased to submit this proposal for professional services...', sort_order: 0 },
        { title: '2. Project Scope & Deliverables', content: 'Our scope of work includes the design, deployment, and documentation of...', sort_order: 1 },
        { title: '3. Technical Architecture', content: 'The proposed technical stack is built on a highly reliable containerized design...', sort_order: 2 },
        { title: '4. Service Schedule & Terms', content: 'Support services will be active for 12 months with a 99.9% availability SLA...', sort_order: 3 }
    ],
    items: [
        { description: 'Enterprise Digital Backbone Core Deployment', quantity: 1, unit_price: 32000 },
        { description: 'Cloud Systems Architecture & Security Hardening', quantity: 1, unit_price: 16000 }
    ],
    addSection() {
        this.sections.push({ title: 'New Section', content: '', sort_order: this.sections.length });
    },
    removeSection(index) {
        this.sections.splice(index, 1);
        this.sections.forEach((sec, idx) => sec.sort_order = idx);
    },
    addItem() {
        this.items.push({ description: '', quantity: 1, unit_price: 0 });
    },
    removeItem(index) {
        this.items.splice(index, 1);
    },
    calculateTotal() {
        return this.items.reduce((sum, item) => sum + (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)), 0);
    }
}">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Create Enterprise Proposal</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Structure, draft, and issue professional service bids and consultation contracts.</p>
        </div>
        <a href="{{ route('crm.leads.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
            &larr; Back to Leads
        </a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400 p-4 rounded-lg border border-emerald-200 dark:border-emerald-800 text-xs">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('crm.proposals.store') }}" method="POST" class="space-y-6">
        @csrf

        <!-- Core Fields -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
            <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">1. Basic Metadata</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div class="md:col-span-2">
                    <label for="title" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Proposal Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" required value="SaaS Architecture & Custom Digital Backend Core Implementation" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <!-- Recipient Client -->
                <div>
                    <label for="client_id" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Recipient Client <span class="text-red-500">*</span></label>
                    <select name="client_id" id="client_id" required class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }} ({{ $client->email }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Linked Pipeline Lead -->
                <div>
                    <label for="lead_id" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Linked CRM Lead</label>
                    <select name="lead_id" id="lead_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- No linked lead --</option>
                        @foreach($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->name }} - {{ $lead->company_name ?? 'Individual' }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Expiration Constraint -->
                <div>
                    <label for="expires_at" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Expiration Constraint <span class="text-red-500">*</span></label>
                    <input type="date" name="expires_at" id="expires_at" required value="{{ date('Y-m-d', strtotime('+30 days')) }}" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
        </div>

        <!-- Sections Builder -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider font-sans">2. Proposal Scope & Sections</h2>
                <button type="button" @click="addSection()" class="inline-flex items-center rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 text-xs font-semibold hover:bg-indigo-100">
                    + Add Section Block
                </button>
            </div>

            <div class="space-y-4">
                <template x-for="(section, index) in sections" :key="index">
                    <div class="p-4 border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/20 rounded-lg space-y-3 relative">
                        <div class="absolute top-4 right-4">
                            <button type="button" @click="removeSection(index)" class="text-red-500 hover:text-red-700 text-xs">
                                Remove Block
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-3">
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Section Header Title</label>
                                <input type="text" :name="'sections['+index+'][title]'" x-model="section.title" required class="block w-full rounded border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-2">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Sort Index</label>
                                <input type="number" :name="'sections['+index+'][sort_order]'" x-model="section.sort_order" required class="block w-full rounded border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-2">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Detailed Content (Markdown Supported)</label>
                            <textarea :name="'sections['+index+'][content]'" x-model="section.content" rows="4" required class="block w-full rounded border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-2"></textarea>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Quotation Builder -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">3. Quotation & Line-Item Estimation</h2>
                <button type="button" @click="addItem()" class="inline-flex items-center rounded bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 text-xs font-semibold hover:bg-indigo-100">
                    + Add Quote Line Item
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    <thead>
                        <tr class="text-slate-500 dark:text-slate-400 text-left font-bold uppercase">
                            <th class="py-3 px-4">Service / Product Description</th>
                            <th class="py-3 px-4 w-24 text-right">Units / Hrs</th>
                            <th class="py-3 px-4 w-32 text-right">Unit Price ($)</th>
                            <th class="py-3 px-4 w-32 text-right">Line Total ($)</th>
                            <th class="py-3 px-4 w-16 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="text-slate-700 dark:text-slate-300">
                                <td class="py-3 px-4">
                                    <input type="text" :name="'items['+index+'][description]'" x-model="item.description" placeholder="e.g. Consulting, Hardware Supply" required class="block w-full rounded border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2 text-xs">
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <input type="number" :name="'items['+index+'][quantity]'" x-model="item.quantity" min="0.01" step="0.01" required class="block w-20 rounded border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2 text-xs text-right">
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <input type="number" :name="'items['+index+'][unit_price]'" x-model="item.unit_price" min="0" step="0.01" required class="block w-28 rounded border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2 text-xs text-right">
                                </td>
                                <td class="py-3 px-4 text-right font-medium align-middle">
                                    <span x-text="'$' + (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)).toFixed(2)"></span>
                                </td>
                                <td class="py-3 px-4 text-center align-middle">
                                    <button type="button" @click="removeItem(index)" class="text-red-500 hover:text-red-700">
                                        &times;
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Dynamic Total Display -->
            <div class="border-t border-slate-200 dark:border-slate-800 pt-4 flex justify-end">
                <div class="text-right space-y-1">
                    <p class="text-[10px] uppercase font-bold text-slate-400">Aggregated Proposal Valuation</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white" x-text="'$' + calculateTotal().toFixed(2)"></p>
                </div>
            </div>
        </div>

        <!-- Submit Controls -->
        <div class="flex justify-end gap-3">
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-xs font-bold text-white shadow-sm hover:bg-indigo-500">
                Compile & Create Proposal Draft
            </button>
        </div>
    </form>
</div>
@endsection
