@extends('layouts.app')

@section('header')
    Settings: {{ $organization->name }}
@endsection

@section('content')
<div class="space-y-10 divide-y divide-slate-200">
    <!-- Organization Details Settings Form -->
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 md:grid-cols-3">
        <div class="px-4 sm:px-0">
            <h2 class="text-base font-semibold leading-7 text-slate-900">Workspace Settings</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Update company profile details, names, and customized tenant domain properties.</p>
        </div>

        <form action="{{ route('organization.settings.update', $organization->id) }}" method="POST" class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl md:col-span-2">
            @csrf
            <div class="px-4 py-6 sm:p-8">
                <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-slate-900">Organization Name</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" value="{{ old('name', $organization->name) }}" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3" @if(!$isOwner) disabled @endif>
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="domain" class="block text-sm font-medium leading-6 text-slate-900">Custom Domain</label>
                        <div class="mt-2">
                            <input type="text" name="domain" id="domain" value="{{ old('domain', $organization->domain) }}" placeholder="e.g. workspace.domain.com" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3" @if(!$isOwner) disabled @endif>
                        </div>
                    </div>
                </div>
            </div>
            @if($isOwner)
                <div class="flex items-center justify-end gap-x-6 border-t border-slate-900/10 px-4 py-4 sm:px-8">
                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save Changes</button>
                </div>
            @endif
        </form>
    </div>

    <!-- Active Membership Administration List -->
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 pt-10 md:grid-cols-3">
        <div class="px-4 sm:px-0">
            <h2 class="text-base font-semibold leading-7 text-slate-900">Members & Access Controls</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">List active employees, managers, and administrative roles attached to this tenant.</p>
        </div>

        <div class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl md:col-span-2 overflow-hidden border border-slate-200">
            <ul role="list" class="divide-y divide-slate-200">
                @foreach($members as $member)
                    <li class="flex items-center justify-between gap-x-6 py-5 px-6">
                        <div class="min-w-0">
                            <div class="flex items-start gap-x-3">
                                <p class="text-sm font-semibold leading-6 text-slate-900">{{ $member->user->name }}</p>
                                @if($member->is_owner)
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Owner</span>
                                @endif
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset capitalize {{ $member->status === 'active' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-yellow-50 text-yellow-700 ring-yellow-600/20' }}">
                                    {{ $member->status }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-slate-500">
                                <p class="truncate">{{ $member->user->email }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Invite Employee Form -->
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 pt-10 md:grid-cols-3">
        <div class="px-4 sm:px-0">
            <h2 class="text-base font-semibold leading-7 text-slate-900">Invite Members</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Extend invitations to colleagues or employees to participate in this workspace.</p>
        </div>

        <form action="{{ route('organization.invite', $organization->id) }}" method="POST" class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl md:col-span-2">
            @csrf
            <div class="px-4 py-6 sm:p-8">
                <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-4">
                        <label for="invite_email" class="block text-sm font-medium leading-6 text-slate-900">Colleague Email Address</label>
                        <div class="mt-2 flex rounded-md shadow-sm">
                            <input type="email" name="email" id="invite_email" required placeholder="name@company.com" class="block w-full rounded-l-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                            <button type="submit" class="relative -ml-px inline-flex items-center gap-x-1.5 rounded-r-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Send Invite
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
