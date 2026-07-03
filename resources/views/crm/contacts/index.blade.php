@extends('layouts.app')

@section('header', 'CRM Contacts Directory')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Contacts Registry</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Unified register of individual contact points and stakeholders.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.leads.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
                Back to Leads Directory
            </a>
        </div>
    </div>

    <!-- Search Controls -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form action="{{ route('crm.contacts.index') }}" method="GET" class="flex items-center gap-4">
            <div class="flex-1">
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search contacts by name, email..." class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3">
            </div>
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-xs font-semibold hover:bg-indigo-500">
                Search
            </button>
            <a href="{{ route('crm.contacts.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-4 py-2.5 text-xs font-semibold">
                Reset
            </a>
        </form>
    </div>

    <!-- Contacts List -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 shadow-sm rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                <thead>
                    <tr class="text-[10px] uppercase font-bold text-slate-400">
                        <th class="py-4 px-6 text-left">Contact Name</th>
                        <th class="py-4 px-6 text-left">Enterprise Email</th>
                        <th class="py-4 px-6 text-left">Phone Number</th>
                        <th class="py-4 px-6 text-left">Corporate Context</th>
                        <th class="py-4 px-6 text-left">Registered Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                    @forelse($contacts as $contact)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/20">
                            <td class="py-4 px-6 font-bold text-slate-900 dark:text-white">
                                {{ $contact->full_name }}
                            </td>
                            <td class="py-4 px-6 text-indigo-600 dark:text-indigo-400">
                                {{ $contact->email }}
                            </td>
                            <td class="py-4 px-6">
                                {{ $contact->phone ?? 'N/A' }}
                            </td>
                            <td class="py-4 px-6">
                                {{ $contact->company ? $contact->company->name : 'Independent Stakeholder' }}
                            </td>
                            <td class="py-4 px-6 text-slate-400">
                                {{ $contact->created_at?->toFormattedDateString() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 italic">
                                No contact records populated yet. Convert commercial leads to generate contacts automatically.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($contacts->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
