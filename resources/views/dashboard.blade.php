@extends('layouts.app')

@section('header', 'Workspace Central Command')

@section('content')
<div class="space-y-6" x-data="{ 
    selectedTab: 'analytics',
    systemStatus: 'Optimal',
    selectedQuickAction: null,
    onlineCount: 4
}">
    <!-- Top Stats Row (Stat Cards Component) -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card 
            title="Total Monthly Revenue" 
            value="$184,204.00" 
            change="+14.2% from last month" 
            changeType="increase"
            icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>'
            iconBg="bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
        />

        <x-stat-card 
            title="Active CRM Leads" 
            value="1,482" 
            change="+8.4% since Monday" 
            changeType="increase"
            icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94-3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>'
            iconBg="bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
        />

        <x-stat-card 
            title="MinIO Object Storage" 
            value="42.8 GB / 100 GB" 
            change="42.8% used" 
            changeType="neutral"
            icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15z" /></svg>'
            iconBg="bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
        />

        <x-stat-card 
            title="Active Projects" 
            value="18 Tasks" 
            change="3 tasks overdue" 
            changeType="decrease"
            icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m-15 0a2.247 2.247 0 0 0-.75-.128H3.75a1.125 1.125 0 0 0-1.125 1.125v6.563c0 .621.504 1.125 1.125 1.125h16.5a1.125 1.125 0 0 0 1.125-1.125v-6.563a1.125 1.125 0 0 0-1.125-1.125h-.75c-.263 0-.515.045-.75.128m-15 0V18a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 18 18V9.75" /></svg>'
            iconBg="bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
        />
    </div>

    <!-- Active Workspace Identity Hero & Summary -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Organization & Identity Details -->
        <x-card title="Workspace Organization Context" subtitle="Active tenant registration information" class="lg:col-span-2">
            <div class="space-y-6">
                <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">
                            @if($activeOrganization)
                                {{ $activeOrganization->name }}
                            @else
                                Standard Development Sandbox
                            @endif
                        </h2>
                        <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">
                            Domain Registry: 
                            @if($activeOrganization)
                                <code class="text-xs text-indigo-600 bg-indigo-50/50 px-1.5 py-0.5 rounded dark:bg-indigo-950/50 dark:text-indigo-400">{{ $activeOrganization->domain ?: 'sandbox.juanet.io' }}</code>
                            @else
                                <code class="text-xs text-indigo-600 bg-indigo-50/50 px-1.5 py-0.5 rounded">localhost</code>
                            @endif
                        </p>
                    </div>
                    <x-badge variant="indigo">SaaS Platform</x-badge>
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">Identity Platform Log</h4>
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-900 dark:text-white">{{ $user->name }}</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                        </div>
                        <x-badge variant="emerald">Authenticated</x-badge>
                    </div>
                </div>

                @if($activeOrganization)
                    <div>
                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">Assigned Roles & Privileges</h4>
                        <div class="flex flex-wrap gap-2">
                            @php
                                $roles = $user->rolesInOrganization($activeOrganization->id)->get();
                            @endphp
                            @forelse($roles as $role)
                                <span class="inline-flex items-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 px-2.5 py-1 text-xs font-medium ring-1 ring-inset ring-indigo-600/10">
                                    {{ $role->name }}
                                </span>
                            @empty
                                <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2.5 py-1 text-xs font-medium">
                                    Standard Employee
                                </span>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </x-card>

        <!-- Right Side Swapper and Actions -->
        <div class="space-y-6">
            <x-card title="Migration Hub" subtitle="Switch organization instances instantly.">
                <div class="space-y-2">
                    @forelse($memberships as $membership)
                        @if($membership->organization_id !== ($activeOrganization ? $activeOrganization->id : null) && $membership->status === 'active')
                            <form action="{{ route('organization.switch', $membership->organization_id) }}" method="POST">
                                @csrf
                                <button type="submit" class="flex w-full items-center justify-between rounded-lg border border-slate-200 dark:border-slate-800 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-left transition duration-150">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ $membership->organization->name }}</span>
                                    <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase">Switch &rarr;</span>
                                </button>
                            </form>
                        @endif
                    @empty
                        <p class="text-xs text-slate-400 italic">No alternate active tenant memberships provisioned.</p>
                    @endforelse
                    
                    <a href="{{ route('organization.create') }}" class="block text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-3 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:border-indigo-400">
                        + Launch New Tenant Instance
                    </a>
                </div>
            </x-card>

            <!-- Quick operations panel -->
            <x-card title="System Shortcuts" subtitle="Fast administrative actions">
                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('profile') }}" class="flex flex-col items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 text-center hover:bg-indigo-50 dark:hover:bg-indigo-950/20 border border-slate-100 dark:border-slate-800 transition">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Security Details</span>
                    </a>
                    @if($activeOrganization)
                        <a href="{{ route('organization.settings', $activeOrganization->id) }}" class="flex flex-col items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 text-center hover:bg-indigo-50 dark:hover:bg-indigo-950/20 border border-slate-100 dark:border-slate-800 transition">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Admin Panel</span>
                        </a>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Interactive Workspace Dashboard Modules Grid (CRM, Orders, Health etc) -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Latest Orders (Placeholder Widget with live table look) -->
        <x-card title="Latest Marketplace Orders" subtitle="Enterprise checkout activity logs" class="lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800">
                    <thead>
                        <tr class="text-[10px] uppercase font-bold text-slate-400">
                            <th class="py-3 text-left">Order ID</th>
                            <th class="py-3 text-left">Enterprise Client</th>
                            <th class="py-3 text-left">Gateway</th>
                            <th class="py-3 text-left">Status</th>
                            <th class="py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                        <tr>
                            <td class="py-3 font-mono text-indigo-600 dark:text-indigo-400">#ORD-2094</td>
                            <td class="py-3 font-semibold">Acme Industrial Ltd</td>
                            <td class="py-3">M-PESA Bulk Pay</td>
                            <td class="py-3"><x-badge variant="emerald">Completed</x-badge></td>
                            <td class="py-3 text-right font-semibold">$12,450.00</td>
                        </tr>
                        <tr>
                            <td class="py-3 font-mono text-indigo-600 dark:text-indigo-400">#ORD-2095</td>
                            <td class="py-3 font-semibold">Safari Telecom Hub</td>
                            <td class="py-3">Stripe Transfer</td>
                            <td class="py-3"><x-badge variant="amber">Processing</x-badge></td>
                            <td class="py-3 text-right font-semibold">$8,900.00</td>
                        </tr>
                        <tr>
                            <td class="py-3 font-mono text-indigo-600 dark:text-indigo-400">#ORD-2096</td>
                            <td class="py-3 font-semibold">Global Logistics Corp</td>
                            <td class="py-3">Direct Transfer</td>
                            <td class="py-3"><x-badge variant="emerald">Completed</x-badge></td>
                            <td class="py-3 text-right font-semibold">$45,000.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>

        <!-- System Health Audit Widget -->
        <x-card title="Infrastructure Topology" subtitle="Vitals and cluster status">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">PostgreSQL Core DB</span>
                    <span class="inline-flex items-center gap-x-1.5 text-xs text-green-600 dark:text-green-400 font-bold">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Online
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">Redis In-Memory Cache</span>
                    <span class="inline-flex items-center gap-x-1.5 text-xs text-green-600 dark:text-green-400 font-bold">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Online
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">MinIO Storage Gateway</span>
                    <span class="inline-flex items-center gap-x-1.5 text-xs text-green-600 dark:text-green-400 font-bold">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Online
                    </span>
                </div>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Users Online Currently</span>
                    <div class="flex items-center gap-x-2">
                        <div class="flex -space-x-1 overflow-hidden">
                            <span class="inline-block h-6 w-6 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-[8px] border border-white">U1</span>
                            <span class="inline-block h-6 w-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-[8px] border border-white">U2</span>
                            <span class="inline-block h-6 w-6 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-[8px] border border-white">U3</span>
                        </div>
                        <span class="text-xs text-slate-500 dark:text-slate-400" x-text="onlineCount + ' active sessions' "></span>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    <!-- Recent Support Tickets and Project Tasks Section -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Workspace Task List" subtitle="Assigned deliverables and milestones">
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-x-3">
                        <input type="checkbox" checked disabled class="rounded border-slate-300 text-indigo-600">
                        <span class="text-xs font-medium text-slate-800 dark:text-slate-300 line-through">Bootstrap database tables & migration rules</span>
                    </div>
                    <x-badge variant="emerald">Done</x-badge>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-x-3">
                        <input type="checkbox" checked disabled class="rounded border-slate-300 text-indigo-600">
                        <span class="text-xs font-medium text-slate-800 dark:text-slate-300 line-through">Deploy Auth & Multi-tenant isolation features</span>
                    </div>
                    <x-badge variant="emerald">Done</x-badge>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                    <div class="flex items-center gap-x-3">
                        <input type="checkbox" disabled class="rounded border-slate-300 text-indigo-600">
                        <span class="text-xs font-medium text-slate-800 dark:text-slate-300">Test dashboard layouts & responsive drawers</span>
                    </div>
                    <x-badge variant="indigo">In Progress</x-badge>
                </div>
            </div>
        </x-card>

        <x-card title="Recent Enterprise Support Tickets" subtitle="Active customer inquiries">
            <div class="space-y-4 text-xs">
                <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <span class="font-bold text-slate-800 dark:text-white">API access token generation fails on client endpoints</span>
                        <p class="text-slate-400 mt-0.5">Assigned to: Support Lead</p>
                    </div>
                    <x-badge variant="red">High Priority</x-badge>
                </div>
                <div class="flex items-start justify-between">
                    <div>
                        <span class="font-bold text-slate-800 dark:text-white">Request to upgrade Storage Limits on MinIO cluster</span>
                        <p class="text-slate-400 mt-0.5">Assigned to: DevOps Admin</p>
                    </div>
                    <x-badge variant="slate">General</x-badge>
                </div>
            </div>
        </x-card>
    </div>

    <!-- Active Tenant Membership Roll Directory -->
    @if($activeOrganization)
        <div class="bg-white border border-slate-200 dark:bg-slate-900 dark:border-slate-800 shadow-sm rounded-xl overflow-hidden">
            <div class="border-b border-slate-100 dark:border-slate-800 py-5 px-6">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Active Tenant Membership Roll Directory</h3>
                <p class="text-xs text-slate-500 mt-1">Full registry of active user accounts verified for this organization instance.</p>
            </div>
            <ul role="list" class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($members as $member)
                    <li class="flex items-center justify-between gap-x-6 py-4 px-6">
                        <div class="flex min-w-0 gap-x-4">
                            <div class="h-9 w-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-indigo-600 font-bold text-xs uppercase">
                                {{ substr($member->user->name, 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-auto">
                                <p class="text-xs font-semibold leading-6 text-slate-900 dark:text-white">{{ $member->user->name }}</p>
                                <p class="mt-1 truncate text-[11px] leading-5 text-slate-500 dark:text-slate-400">{{ $member->user->email }}</p>
                            </div>
                        </div>
                        <div class="flex flex-none items-center gap-x-4">
                            @if($member->is_owner)
                                <x-badge variant="indigo">Owner</x-badge>
                            @endif
                            <x-badge variant="emerald">{{ $member->status }}</x-badge>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
