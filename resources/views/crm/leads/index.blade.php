@extends('layouts.app')

@section('header', 'CRM Lead Management')

@section('content')
<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Leads Command Center</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Acquire, qualify, and track your commercial pipeline leads.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('crm.contacts.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
                Contacts
            </a>
            <a href="{{ route('crm.companies.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
                Companies
            </a>
            <a href="{{ route('crm.opportunities.index') }}" class="inline-flex items-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-indigo-100">
                Pipeline Board
            </a>
            <a href="{{ route('crm.leads.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-indigo-500">
                + Create Lead
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-400">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filtering & Search -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form action="{{ route('crm.leads.index') }}" method="GET" class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex-1">
                <label for="search" class="sr-only">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search leads by name or email..." class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <select name="status" id="status" class="rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs p-3">
                    <option value="">All Statuses</option>
                    <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
                    <option value="contacted" {{ request('status') === 'contacted' ? 'selected' : '' }}>Contacted</option>
                    <option value="qualified" {{ request('status') === 'qualified' ? 'selected' : '' }}>Qualified</option>
                    <option value="unqualified" {{ request('status') === 'unqualified' ? 'selected' : '' }}>Unqualified</option>
                    <option value="converted" {{ request('status') === 'converted' ? 'selected' : '' }}>Converted</option>
                    <option value="lost" {{ request('status') === 'lost' ? 'selected' : '' }}>Lost</option>
                </select>
                <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-xs font-semibold shadow-sm hover:bg-indigo-500">
                    Apply Filter
                </button>
                <a href="{{ route('crm.leads.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-4 py-2.5 text-xs font-semibold">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Leads Table / Grid -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 shadow-sm rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                <thead>
                    <tr class="text-[10px] uppercase font-bold text-slate-400">
                        <th class="py-4 px-6 text-left">Lead Name</th>
                        <th class="py-4 px-6 text-left">Contact Info</th>
                        <th class="py-4 px-6 text-left">Organization Info</th>
                        <th class="py-4 px-6 text-left">Status</th>
                        <th class="py-4 px-6 text-left">Assigned To</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/20">
                            <td class="py-4 px-6">
                                <a href="{{ route('crm.leads.show', $lead->id) }}" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $lead->name }}
                                </a>
                            </td>
                            <td class="py-4 px-6">
                                <p>{{ $lead->email }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ $lead->phone ?? 'No phone' }}</p>
                            </td>
                            <td class="py-4 px-6">
                                <p class="font-semibold">{{ $lead->company ? $lead->company->name : 'No Company Associated' }}</p>
                                @if($lead->source)
                                    <span class="inline-flex items-center rounded-md bg-indigo-50/50 dark:bg-indigo-950/20 px-1.5 py-0.5 text-[9px] font-medium text-indigo-600 dark:text-indigo-400 mt-1">
                                        Source: {{ $lead->source->name }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
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
                                <x-badge :variant="$variant">{{ ucfirst($lead->status) }}</x-badge>
                            </td>
                            <td class="py-4 px-6 text-slate-500">
                                {{ $lead->owner ? $lead->owner->name : 'Unassigned' }}
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('crm.leads.edit', $lead->id) }}" class="inline-flex items-center rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 px-2 py-1 text-[11px] font-semibold">
                                        Edit
                                    </a>
                                    @if($lead->status !== 'converted')
                                        <a href="{{ route('crm.leads.convert.form', $lead->id) }}" class="inline-flex items-center rounded bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 text-indigo-600 dark:text-indigo-400 px-2 py-1 text-[11px] font-semibold">
                                            Convert
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                No leads match the current filters. Click "+ Create Lead" to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($leads->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
