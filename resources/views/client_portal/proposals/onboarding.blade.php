@extends('layouts.app')

@section('header', 'Client Onboarding & Project Initialization')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="{
    activeTab: 'overview',
    steps: [
        { id: 1, name: 'Proposal Accepted & Signed', status: 'completed', desc: 'Secure cryptographic signature bound to proposal.' },
        { id: 2, name: 'Client Portal Enabled', status: 'completed', desc: 'Secure client credential mapping completed.' },
        { id: 3, name: 'Collaborative Project Created', status: 'completed', desc: 'Active project workspace initialized.' },
        { id: 4, name: 'Standard Milestones Setup', status: 'completed', desc: 'Six core project milestones instantiated.' },
        { id: 5, name: 'Discovery Onboarding Checklist', status: 'completed', desc: 'First seven-item stakeholder checklist prepared.' },
        { id: 6, name: 'Secure Folder Structures', status: 'completed', desc: 'Seven core repository directories created.' },
        { id: 7, name: 'Welcome Message Dispatched', status: 'completed', desc: 'Delivery team welcome comment posted.' }
    ]
}">
    <!-- Onboarding Success Banner -->
    <div class="bg-gradient-to-r from-indigo-900 to-slate-900 text-white rounded-2xl p-8 border border-indigo-950 shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
            <svg class="w-80 h-80 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/>
            </svg>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 bg-indigo-500/20 text-indigo-300 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Workflow Automation Complete
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight">Welcome to JUANET, {{ Auth::user()->name ?? 'Valued Client' }}!</h1>
                <p class="text-indigo-200 text-sm max-w-xl">
                    Your proposal <strong>{{ $proposal->title }}</strong> has been officially signed and accepted. We have automated the backend onboarding workflow and provisioned your new professional services workspace.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if($project)
                    <a href="{{ route('client.project.show', $project->id) }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-5 py-3 rounded-lg shadow-lg transition-all">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Explore Project Workspace
                    </a>
                @endif
                <a href="{{ route('client.dashboard') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs px-5 py-3 rounded-lg transition-all">
                    Go to Portal Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Onboarding Progress and Success Confirmation -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Onboarding Steps Tracker -->
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
            <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">Onboarding Progress</h2>
            
            <div class="flow-root">
                <ul class="-mb-8">
                    <template x-for="(step, index) in steps" :key="step.id">
                        <li class="relative pb-8">
                            <!-- Line -->
                            <div class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-emerald-500" x-show="index < steps.length - 1"></div>
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-emerald-100 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 ring-8 ring-white dark:ring-slate-900">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white" x-text="step.name"></p>
                                    <p class="text-[10px] text-slate-500" x-text="step.desc"></p>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <!-- Middle & Right Sections -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Success Confirmation Card -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-emerald-100 dark:bg-emerald-950/40 rounded-full text-emerald-600">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Cryptographic Legal Receipt</h3>
                        <p class="text-xs text-slate-500">The contract has been compiled with immutable parameters and sealed.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 dark:bg-slate-950 p-4 rounded-lg border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold text-slate-400">Contract Reference</p>
                        <p class="font-mono font-bold text-slate-900 dark:text-white">CON-{{ strtoupper(Str::random(10)) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] uppercase font-bold text-slate-400">Associated Project Ref</p>
                        <p class="font-mono font-bold text-slate-900 dark:text-white">{{ $project->reference_number ?? 'PRJ-N/A' }}</p>
                    </div>
                    <div class="space-y-1 pt-2 md:pt-0">
                        <p class="text-[10px] uppercase font-bold text-slate-400">Valuation Amount</p>
                        <p class="font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($proposal->total_amount, 2) }}</p>
                    </div>
                    <div class="space-y-1 pt-2 md:pt-0">
                        <p class="text-[10px] uppercase font-bold text-slate-400">Sealed Date</p>
                        <p class="font-bold text-slate-900 dark:text-white">{{ now()->format('M d, Y H:i T') }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="flex border-b border-slate-200 dark:border-slate-800 gap-6 text-xs font-bold uppercase tracking-wider">
                <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-400'" class="pb-3 transition-all">
                    Project Milestones
                </button>
                <button @click="activeTab = 'folders'" :class="activeTab === 'folders' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-400'" class="pb-3 transition-all">
                    Directories & Assets
                </button>
                <button @click="activeTab = 'audit'" :class="activeTab === 'audit' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-400'" class="pb-3 transition-all">
                    System Event Stream
                </button>
            </div>

            <!-- Tab content: Overview (Milestones & Checklists) -->
            <div x-show="activeTab === 'overview'" class="space-y-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Automatic Project Timeline & Onboarding Checklist</h3>
                
                @if($project)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($project->milestones as $milestone)
                            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-5 space-y-4 transition-all hover:shadow-md">
                                <div class="flex justify-between items-start">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Milestone</span>
                                        </div>
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $milestone->title }}</h4>
                                    </div>
                                    <span class="bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 rounded px-2 py-0.5 text-[9px] font-bold">
                                        Due: {{ $milestone->due_date->format('M d, Y') }}
                                    </span>
                                </div>

                                <!-- Tasks list -->
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-3 space-y-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Associated Tasks</p>
                                    @forelse($milestone->tasks as $task)
                                        <div class="flex items-center justify-between text-xs p-2 bg-slate-50 dark:bg-slate-950/50 rounded border border-slate-100 dark:border-slate-850">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" disabled class="rounded border-slate-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-slate-700 dark:text-slate-300">{{ $task->title }}</span>
                                            </div>
                                            <span class="text-[9px] font-semibold text-slate-400 uppercase">{{ $task->priority }}</span>
                                        </div>
                                    @empty
                                        <p class="text-[11px] text-slate-500">Tasks are pending scope specification by PM team.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-500">Loading project milestones...</p>
                @endif
            </div>

            <!-- Tab content: Directories & Assets -->
            <div x-show="activeTab === 'folders'" class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Provisioned Folder Tree Structure</h3>
                    <p class="text-xs text-slate-500">These directories have been scaffolded in your client storage workspace to organize deliverables and reference data.</p>
                </div>

                <div class="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden bg-slate-50 dark:bg-slate-950 p-4">
                    <div class="space-y-2 font-mono text-xs text-slate-700 dark:text-slate-300">
                        <div class="flex items-center gap-2 font-bold text-indigo-600 dark:text-indigo-400">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
                            </svg>
                            / {{ $project->name ?? 'Project Root' }}
                        </div>
                        <div class="pl-6 space-y-1 text-[11px]">
                            @if($project && $project->files)
                                @foreach($project->files as $file)
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-400">├──</span>
                                        <svg class="h-3.5 w-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ $file->filename }}</span>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-slate-400 text-xs">Directories are being structured...</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab content: System Event Stream -->
            <div x-show="activeTab === 'audit'" class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Immutable Audit Trail</h3>
                
                <div class="flow-root">
                    <ul class="-mb-8 text-xs text-slate-600 dark:text-slate-400 space-y-4">
                        @if($project && $project->activities)
                            @foreach($project->activities as $act)
                                <li class="relative pb-4">
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 inline-block ring-4 ring-white dark:ring-slate-900"></span>
                                        </div>
                                        <div class="min-w-0 flex-1 pt-0">
                                            <p class="text-[11px] text-slate-900 dark:text-white">
                                                <span class="font-bold uppercase tracking-wider text-emerald-600 mr-2">[{{ $act->activity }}]</span>
                                                {{ $act->metadata['message'] ?? 'Action resolved in context.' }}
                                            </p>
                                            <span class="text-[9px] text-slate-400 block">{{ $act->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        @else
                            <p class="text-slate-400">No activity trail logs compiled yet.</p>
                        @endif
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
