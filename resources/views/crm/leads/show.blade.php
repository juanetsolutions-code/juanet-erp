@extends('layouts.app')

@section('header', 'Commercial Prospect Dossier')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Back to Index Header -->
    <div class="flex items-center justify-between">
        <a href="{{ route('crm.leads.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
            &larr; Back to Leads Directory
        </a>
        <div class="flex gap-2">
            <a href="{{ route('crm.leads.edit', $lead->id) }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
                Edit Details
            </a>
            @if($lead->status !== 'converted')
                <a href="{{ route('crm.leads.convert.form', $lead->id) }}" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-indigo-500">
                    Convert to Deal
                </a>
            @endif
            <form action="{{ route('crm.leads.destroy', $lead->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to archive this lead?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center rounded-lg bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 px-3.5 py-2 text-xs font-semibold hover:bg-red-100">
                    Archive Lead
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-400">
            {{ session('success') }}
        </div>
    @endif

    <!-- Profile Dossier Body -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Stats & Profile Card -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <span class="text-xs font-bold uppercase text-slate-400">Prospect Profile</span>
                        <h2 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $lead->name }}</h2>
                    </div>
                    @php
                        $variants = [
                            'new' => 'blue',
                            'contacted' => 'amber',
                            'qualified' => 'emerald',
                            'unqualified' => 'red',
                            'converted' => 'indigo',
                            'lost' => 'slate',
                        ];
                        $variant = $variants[$lead->status] ?? 'indigo';
                    @endphp
                    <x-badge :variant="$variant" class="text-sm px-3 py-1">{{ ucfirst($lead->status) }}</x-badge>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div>
                        <h4 class="text-slate-400 font-bold uppercase mb-2">Email Address</h4>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $lead->email }}</p>
                    </div>
                    <div>
                        <h4 class="text-slate-400 font-bold uppercase mb-2">Phone Number</h4>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $lead->phone ?? 'No phone number provided' }}</p>
                    </div>
                    <div>
                        <h4 class="text-slate-400 font-bold uppercase mb-2">Acquisition Source</h4>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $lead->source ? $lead->source->name : 'N/A' }}</p>
                    </div>
                    <div>
                        <h4 class="text-slate-400 font-bold uppercase mb-2">Registration Organization</h4>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $lead->organization ? $lead->organization->name : 'Global Platform' }}</p>
                    </div>
                </div>
            </div>

            <!-- Custom Fields & Metadata -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Enterprise Custom Fields</h3>
                @if($lead->custom_fields && count($lead->custom_fields) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                        @foreach($lead->custom_fields as $key => $val)
                            <div class="bg-slate-50 dark:bg-slate-950 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                                <span class="font-bold text-slate-400 block mb-1 uppercase tracking-wider text-[10px]">{{ str_replace('_', ' ', $key) }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-400 italic">No enterprise custom fields populated for this commercial prospect.</p>
                @endif
            </div>
        </div>

        <!-- Right Side Panel Controls -->
        <div class="space-y-6">
            <!-- Owner Assignment -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Lead Ownership</h3>
                <p class="text-xs text-slate-500 mb-4">Assign this lead to an account owner inside this tenant instance.</p>
                
                <form action="{{ route('crm.leads.assign', $lead->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="user_id" class="sr-only">Account Owner</label>
                        <select name="user_id" id="user_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Unassigned</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ $lead->user_id === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-indigo-600 text-white px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-indigo-500">
                        Update Owner
                    </button>
                </form>
            </div>

            <!-- Company Context Association -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Associated Company Context</h3>
                @if($lead->company)
                    <div class="space-y-2 text-xs">
                        <div class="bg-indigo-50/50 dark:bg-indigo-950/10 p-3 rounded-lg">
                            <span class="text-[10px] font-bold text-indigo-500 uppercase">Enterprise Name</span>
                            <p class="font-bold text-slate-900 dark:text-white mt-1">{{ $lead->company->name }}</p>
                        </div>
                        @if($lead->company->domain)
                            <p><span class="font-semibold text-slate-400">Domain:</span> <a href="https://{{ $lead->company->domain }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $lead->company->domain }}</a></p>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-400 italic">No enterprise company mapped yet. Create or map a company using deal flow conversion.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
