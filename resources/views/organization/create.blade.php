@extends('layouts.app')

@section('header', 'New Organization')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl border border-slate-200">
    <div class="px-4 py-6 sm:p-8">
        <h2 class="text-base font-semibold leading-7 text-slate-900">Launch a New Tenant Workspace</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">Register a separate organization context within JUANET's multi-tenant cloud platform.</p>

        <form action="{{ route('organization.store') }}" method="POST" class="mt-8 space-y-6">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium leading-6 text-slate-900">Organization Name</label>
                <div class="mt-2">
                    <input type="text" name="name" id="name" required placeholder="e.g. Acme Corporation" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                </div>
            </div>

            <div>
                <label for="domain" class="block text-sm font-medium leading-6 text-slate-900">Enterprise Domain (Optional)</label>
                <div class="mt-2">
                    <input type="text" name="domain" id="domain" placeholder="e.g. acme.com" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                </div>
                <p class="mt-2 text-xs text-slate-500">Configure a custom routing domain for single-sign-on or direct domain entry.</p>
            </div>

            <div class="flex items-center justify-end gap-x-6 border-t border-slate-900/10 pt-6">
                <a href="{{ route('organization.index') }}" class="text-sm font-semibold leading-6 text-slate-900">Cancel</a>
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Create Workspace</button>
            </div>
        </form>
    </div>
</div>
@endsection
