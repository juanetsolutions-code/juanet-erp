@extends('layouts.app')

@section('header', 'Convert Commercial Lead to Deal Flow')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Convert Lead: {{ $lead->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Convert this prospect into a contact, register an enterprise company, and kickstart a high-value pipeline opportunity.</p>
        </div>
        <a href="{{ route('crm.leads.show', $lead->id) }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
            &larr; Back to Dossier
        </a>
    </div>

    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm" x-data="{
        createCompany: true,
        createContact: true,
        createOpportunity: true
    }">
        <form action="{{ route('crm.leads.convert', $lead->id) }}" method="POST" class="space-y-6">
            @csrf

            <!-- Contact Creation Segment -->
            <div class="border-b border-slate-100 dark:border-slate-800 pb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">1. Core Contact Record</h3>
                        <p class="text-xs text-slate-500">Register a permanent CRM contact record with this prospect's info.</p>
                    </div>
                    <input type="checkbox" name="create_contact" value="1" x-model="createContact" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div x-show="createContact" class="bg-slate-50 dark:bg-slate-950 p-4 rounded-xl border border-slate-100 dark:border-slate-800 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="font-bold text-slate-400 block mb-1">Contact Name</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $lead->name }}</span>
                    </div>
                    <div>
                        <span class="font-bold text-slate-400 block mb-1">Contact Email</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $lead->email }}</span>
                    </div>
                </div>
            </div>

            <!-- Company Creation Segment -->
            <div class="border-b border-slate-100 dark:border-slate-800 pb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">2. Enterprise Company Registration</h3>
                        <p class="text-xs text-slate-500">Map or construct a brand new corporate entity to contextually nest this contact.</p>
                    </div>
                    <input type="checkbox" name="create_company" value="1" x-model="createCompany" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div x-show="createCompany" class="space-y-4 bg-slate-50 dark:bg-slate-950 p-4 rounded-xl border border-slate-100 dark:border-slate-800">
                    <div>
                        <label for="company_name" class="block text-[11px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Corporate Account Name</label>
                        <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $lead->company ? $lead->company->name : '') }}" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. Acme Corporation">
                    </div>
                </div>
            </div>

            <!-- Opportunity Creation Segment -->
            <div class="border-b border-slate-100 dark:border-slate-800 pb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">3. Pipeline Deal Opportunity</h3>
                        <p class="text-xs text-slate-500">Initialize a live, tracked commercial transaction on your pipeline board.</p>
                    </div>
                    <input type="checkbox" name="create_opportunity" value="1" x-model="createOpportunity" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div x-show="createOpportunity" class="space-y-4 bg-slate-50 dark:bg-slate-950 p-4 rounded-xl border border-slate-100 dark:border-slate-800">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="opportunity_name" class="block text-[11px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Opportunity Deal Title</label>
                            <input type="text" name="opportunity_name" id="opportunity_name" value="{{ old('opportunity_name', $lead->name . ' - Enterprise Deal') }}" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500" placeholder="e.g. Acme Enterprise License">
                        </div>
                        <div>
                            <label for="amount" class="block text-[11px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Estimated Contract Value ($)</label>
                            <input type="number" step="0.01" name="amount" id="amount" value="5000.00" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500" placeholder="5000.00">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="pipeline_id" class="block text-[11px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Target Commercial Pipeline</label>
                            <select name="pipeline_id" id="pipeline_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white p-3">
                                @foreach($pipelines as $pipeline)
                                    <option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="pipeline_stage_id" class="block text-[11px] font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Initial Funnel Stage</label>
                            <select name="pipeline_stage_id" id="pipeline_stage_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white p-3">
                                @foreach($pipelines->first()->stages ?? [] as $stage)
                                    <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button Section -->
            <div class="flex justify-end gap-3 pt-4">
                <a href="{{ route('crm.leads.show', $lead->id) }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-4 py-2.5 text-xs font-semibold hover:bg-slate-200">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-xs font-semibold shadow-sm hover:bg-indigo-500">
                    Execute Conversion Flow
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
