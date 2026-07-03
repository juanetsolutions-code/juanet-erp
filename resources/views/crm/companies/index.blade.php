@extends('layouts.app')

@section('header', 'CRM Companies Directory')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Enterprise Companies Registry</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Registry of client organizations, nested domains, and industries.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.leads.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
                Back to Leads Directory
            </a>
        </div>
    </div>

    <!-- Search Controls -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form action="{{ route('crm.companies.index') }}" method="GET" class="flex items-center gap-4">
            <div class="flex-1">
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search corporate accounts by company name or domain..." class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-3">
            </div>
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-4 py-2.5 text-xs font-semibold hover:bg-indigo-500">
                Search
            </button>
            <a href="{{ route('crm.companies.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-4 py-2.5 text-xs font-semibold">
                Reset
            </a>
        </form>
    </div>

    <!-- Companies List -->
    <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 shadow-sm rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                <thead>
                    <tr class="text-[10px] uppercase font-bold text-slate-400">
                        <th class="py-4 px-6 text-left">Company Name</th>
                        <th class="py-4 px-6 text-left">Registry Domain</th>
                        <th class="py-4 px-6 text-left">Headquarters Phone</th>
                        <th class="py-4 px-6 text-left">Industry Context</th>
                        <th class="py-4 px-6 text-left">Onboarding Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                    @forelse($companies as $company)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/20">
                            <td class="py-4 px-6 font-bold text-slate-900 dark:text-white">
                                {{ $company->name }}
                            </td>
                            <td class="py-4 px-6 text-indigo-600 dark:text-indigo-400">
                                @if($company->domain)
                                    <a href="https://{{ $company->domain }}" target="_blank" class="hover:underline">{{ $company->domain }}</a>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="py-4 px-6 text-slate-500">
                                {{ $company->phone ?? 'N/A' }}
                            </td>
                            <td class="py-4 px-6 text-slate-500">
                                {{ $company->industry ? $company->industry->name : 'General Business' }}
                            </td>
                            <td class="py-4 px-6 text-slate-400">
                                {{ $company->created_at?->toFormattedDateString() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400 italic">
                                No company records populated yet. Convert commercial leads to generate companies automatically.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($companies->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
