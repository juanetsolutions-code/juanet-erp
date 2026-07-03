@extends('layouts.app')

@section('header', 'Verify Email')

@section('content')
<div class="flex min-h-full flex-col justify-center py-6 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-slate-900">Verify your email address</h2>
        <p class="mt-4 text-sm text-slate-600">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white px-4 py-8 shadow sm:rounded-lg sm:px-10 border border-slate-200 space-y-4">
            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500">
                    Resend Verification Email
                </button>
            </form>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="flex w-full justify-center text-sm font-semibold text-slate-600 hover:text-slate-900">
                    Log Out
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
