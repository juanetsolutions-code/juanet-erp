@extends('layouts.app')

@section('header', 'CRM Pipeline Board')

@section('content')
<div class="space-y-6 h-full flex flex-col">
    <!-- Header Control -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Active Pipeline: {{ $pipeline->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage and update deal opportunities across standard commercial stages.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.leads.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 px-3.5 py-2 text-xs font-semibold shadow-sm hover:bg-slate-200">
                Leads directory
            </a>
        </div>
    </div>

    <!-- Kanban Stages Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 overflow-x-auto pb-6">
        @foreach($pipeline->stages as $stage)
            <div class="flex flex-col bg-slate-100/50 dark:bg-slate-900/60 border border-slate-200/50 dark:border-slate-800 p-4 rounded-xl min-w-[250px] space-y-4">
                <!-- Column Header -->
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                    <span class="text-xs font-black uppercase text-slate-700 dark:text-slate-300">{{ $stage->name }}</span>
                    <span class="inline-flex items-center rounded bg-slate-200 dark:bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-400">
                        {{ $stage->opportunities->count() }}
                    </span>
                </div>

                <!-- Opportunity Cards -->
                <div class="flex-1 space-y-3 overflow-y-auto">
                    @forelse($stage->opportunities as $opportunity)
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-100 dark:border-slate-800 shadow-sm space-y-3">
                            <div>
                                <span class="text-[9px] font-bold text-slate-400 uppercase">Deal Opportunity</span>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white mt-0.5">{{ $opportunity->name }}</h4>
                            </div>

                            <div class="text-[10px] space-y-1 text-slate-500">
                                @if($opportunity->company)
                                    <p class="font-semibold text-slate-700 dark:text-slate-300">🏢 {{ $opportunity->company->name }}</p>
                                @endif
                                @if($opportunity->contact)
                                    <p>👤 {{ $opportunity->contact->full_name }}</p>
                                @endif
                                <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-2">${{ number_format($opportunity->amount, 2) }}</p>
                            </div>

                            <!-- Stage Changer Form -->
                            <form action="{{ route('crm.opportunities.move') }}" method="POST" class="pt-2 border-t border-slate-50 dark:border-slate-900">
                                @csrf
                                <input type="hidden" name="opportunity_id" value="{{ $opportunity->id }}">
                                <select name="stage_id" onchange="this.form.submit()" class="block w-full rounded-md border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-[10px] p-1.5">
                                    @foreach($pipeline->stages as $targetStage)
                                        <option value="{{ $targetStage->id }}" {{ $targetStage->id === $stage->id ? 'selected' : '' }}>Move to: {{ $targetStage->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    @empty
                        <p class="text-[10px] text-slate-400 italic text-center py-6">No deal opportunities in this stage.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
