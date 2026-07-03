@extends('layouts.app')

@section('header', 'Modify Commercial Prospect Details')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Edit Lead</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Refine lead identity details and context attributes.</p>
        </div>
        <a href="{{ route('crm.leads.show', $lead->id) }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
            &larr; Back to Lead Dossier
        </a>
    </div>

    <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form action="{{ route('crm.leads.update', $lead->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Lead Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $lead->name) }}" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. John Doe">
                    @error('name')
                        <p class="text-red-500 text-[10px] mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" required value="{{ old('email', $lead->email) }}" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="john.doe@enterprise.com">
                    @error('email')
                        <p class="text-red-500 text-[10px] mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Phone Number</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $lead->phone) }}" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. +254 712 345678">
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Lifecycle Status</label>
                    <select name="status" id="status" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="new" {{ $lead->status === 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ $lead->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="qualified" {{ $lead->status === 'qualified' ? 'selected' : '' }}>Qualified</option>
                        <option value="unqualified" {{ $lead->status === 'unqualified' ? 'selected' : '' }}>Unqualified</option>
                        <option value="converted" {{ $lead->status === 'converted' ? 'selected' : '' }}>Converted</option>
                        <option value="lost" {{ $lead->status === 'lost' ? 'selected' : '' }}>Lost</option>
                    </select>
                </div>

                <!-- Lead Source -->
                <div>
                    <label for="lead_source_id" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Acquisition Lead Source</label>
                    <select name="lead_source_id" id="lead_source_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select a source (Optional)</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}" {{ $lead->lead_source_id === $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Company -->
                <div>
                    <label for="company_id" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Associated Company</label>
                    <select name="company_id" id="company_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select existing company (Optional)</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" {{ $lead->company_id === $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Assign Owner -->
                <div>
                    <label for="user_id" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">Assign Owner</label>
                    <select name="user_id" id="user_id" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Assign to a team member (Optional)</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $lead->user_id === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('crm.leads.show', $lead->id) }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-4 py-2.5 text-xs font-semibold hover:bg-slate-200">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-xs font-semibold shadow-sm hover:bg-indigo-500">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
