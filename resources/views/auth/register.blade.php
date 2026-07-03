@extends('layouts.app')

@section('header', 'Sign Up')

@section('content')
<div class="flex min-h-full flex-col justify-center py-6 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-slate-900">Create an enterprise workspace</h2>
        <p class="mt-2 text-center text-sm text-slate-600">
            Already registered?
            <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Sign in to your account</a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white px-4 py-8 shadow sm:rounded-lg sm:px-10 border border-slate-200">
            <form class="space-y-6" action="{{ route('register') }}" method="POST">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium leading-6 text-slate-900">Full Name</label>
                    <div class="mt-2">
                        <input id="name" name="name" type="text" autocomplete="name" required value="{{ old('name') }}" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium leading-6 text-slate-900">Email Address</label>
                    <div class="mt-2">
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div>
                    <label for="organization_name" class="block text-sm font-medium leading-6 text-slate-900">Organization / Workspace Name</label>
                    <div class="mt-2">
                        <input id="organization_name" name="organization_name" type="text" required value="{{ old('organization_name') }}" placeholder="e.g. Acme Corp" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium leading-6 text-slate-900">Password</label>
                    <div class="mt-2">
                        <input id="password" name="password" type="password" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium leading-6 text-slate-900">Confirm Password</label>
                    <div class="mt-2">
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div>
                    <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Create Workspace
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
