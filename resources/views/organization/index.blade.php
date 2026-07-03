@extends('layouts.app')

@section('header', 'My Enterprise Organizations')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-base font-semibold text-slate-900">Manage Your Workspace Accounts</h2>
            <p class="text-sm text-slate-600">Switch between different tenant workspaces or launch a brand new organization context.</p>
        </div>
        <a href="{{ route('organization.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            Create Organization
        </a>
    </div>

    <!-- Active Indicator -->
    <div class="overflow-hidden bg-white shadow sm:rounded-md border border-slate-200">
        <ul role="list" class="divide-y divide-slate-200">
            @forelse($memberships as $membership)
                <li class="flex items-center justify-between gap-x-6 py-5 px-6">
                    <div class="min-w-0">
                        <div class="flex items-start gap-x-3">
                            <p class="text-sm font-semibold leading-6 text-slate-900">{{ $membership->organization->name }}</p>
                            @if($membership->organization_id === $activeOrgId)
                                <p class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Active Session</p>
                            @endif
                            @if($membership->is_owner)
                                <p class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Owner</p>
                            @endif
                            @if($membership->status === 'pending')
                                <p class="rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20">Pending Invite</p>
                            @endif
                        </div>
                        <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-slate-500">
                            <p class="truncate">Domain: {{ $membership->organization->domain ?: 'Not Configured' }}</p>
                            <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                                <circle cx="1" cy="1" r="1" />
                            </svg>
                            <p class="truncate">Status: <span class="capitalize">{{ $membership->status }}</span></p>
                        </div>
                    </div>
                    <div class="flex flex-none items-center gap-x-4">
                        @if($membership->status === 'pending')
                            <form action="{{ route('organization.accept', $membership->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-green-500">
                                    Accept Invitation
                                </button>
                            </form>
                        @elseif($membership->organization_id !== $activeOrgId)
                            <form action="{{ route('organization.switch', $membership->organization_id) }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                                    Switch Workspace
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('organization.settings', $membership->organization_id) }}" class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                            Settings
                        </a>

                        <form action="{{ route('organization.leave', $membership->organization_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to leave this organization?');">
                            @csrf
                            <button type="submit" class="rounded-md bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 shadow-sm hover:bg-red-100">
                                Leave
                            </button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="py-12 px-6 text-center text-sm text-slate-500">
                    You do not currently belong to any enterprise organization. Get started by creating one above!
                </li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
