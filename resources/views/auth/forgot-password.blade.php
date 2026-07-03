@extends('layouts.app')

@section('header', 'Forgot Password')

@section('content')
<div class="flex min-h-full flex-col justify-center py-6 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-slate-900">Reset your password</h2>
        <p class="mt-2 text-center text-sm text-slate-600">
            Provide your registered email address and we'll dispatch a safe link to recover your credentials.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white px-4 py-8 shadow sm:rounded-lg sm:px-10 border border-slate-200">
            <form class="space-y-6" action="{{ route('password.email') }}" method="POST">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium leading-6 text-slate-900">Email address</label>
                    <div class="mt-2">
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div>
                    <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">
                        Email Password Reset Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
