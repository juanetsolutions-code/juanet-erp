@extends('layouts.app')

@section('header', 'My Account Profile')

@section('content')
<div class="space-y-10 divide-y divide-slate-200">
    <!-- Profile Info Form -->
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 md:grid-cols-3">
        <div class="px-4 sm:px-0">
            <h2 class="text-base font-semibold leading-7 text-slate-900">Personal Information</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Update your account details and contact coordinates.</p>
        </div>

        <form action="{{ route('profile.update') }}" method="POST" class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl md:col-span-2">
            @csrf
            @method('PUT')
            <div class="px-4 py-6 sm:p-8">
                <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-slate-900">Full Name</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="email" class="block text-sm font-medium leading-6 text-slate-900">Email Address</label>
                        <div class="mt-2">
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="phone" class="block text-sm font-medium leading-6 text-slate-900">Phone Number</label>
                        <div class="mt-2">
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-slate-900/10 px-4 py-4 sm:px-8">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Password Form -->
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 pt-10 md:grid-cols-3">
        <div class="px-4 sm:px-0">
            <h2 class="text-base font-semibold leading-7 text-slate-900">Change Password</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Ensure your workspace credentials remain robust.</p>
        </div>

        <form action="{{ route('profile.password') }}" method="POST" class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl md:col-span-2">
            @csrf
            @method('PUT')
            <div class="px-4 py-6 sm:p-8">
                <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-4">
                        <label for="current_password" class="block text-sm font-medium leading-6 text-slate-900">Current Password</label>
                        <div class="mt-2">
                            <input type="password" name="current_password" id="current_password" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="password" class="block text-sm font-medium leading-6 text-slate-900">New Password</label>
                        <div class="mt-2">
                            <input type="password" name="password" id="password" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="password_confirmation" class="block text-sm font-medium leading-6 text-slate-900">Confirm New Password</label>
                        <div class="mt-2">
                            <input type="password" name="password_confirmation" id="password_confirmation" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-slate-900/10 px-4 py-4 sm:px-8">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Update Password</button>
            </div>
        </form>
    </div>

    <!-- concurrent session logout -->
    <div class="grid grid-cols-1 gap-x-8 gap-y-8 pt-10 md:grid-cols-3">
        <div class="px-4 sm:px-0">
            <h2 class="text-base font-semibold leading-7 text-slate-900">Device Session Management</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">Terminate concurrent sessions active on other devices.</p>
        </div>

        <form action="{{ route('profile.logout-devices') }}" method="POST" class="bg-white shadow-sm ring-1 ring-slate-900/5 sm:rounded-xl md:col-span-2">
            @csrf
            <div class="px-4 py-6 sm:p-8">
                <div class="max-w-xl text-sm text-slate-600">
                    <p>If you suspect someone is accessing your workspace unauthorized, you can force log out of all sessions across all devices.</p>
                </div>
                <div class="mt-6 grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-4">
                        <label for="logout_devices_password" class="block text-sm font-medium leading-6 text-slate-900">Your Current Password</label>
                        <div class="mt-2">
                            <input type="password" name="password" id="logout_devices_password" required class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-slate-900/10 px-4 py-4 sm:px-8">
                <button type="submit" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Log Out Other Devices</button>
            </div>
        </form>
    </div>
</div>
@endsection
