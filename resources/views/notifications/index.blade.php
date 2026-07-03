@extends('layouts.app')

@section('header', 'Notification Center')

@section('content')
<div class="space-y-8" x-data="{
    notifications: [],
    preferences: {
        channels: { database: true, email: true, toast: true },
        categories: { system: true, billing: true, crm: true, security: true }
    },
    loading: true,
    activeTab: 'all', // all, unread
    testTitle: 'Database Backup Completed',
    testBody: 'The scheduled nightly database copy has been verified and stored in the secure vault.',
    testType: 'success',
    testCategory: 'system',
    testPriority: 'normal',

    async init() {
        await this.loadNotifications();
        await this.loadPreferences();
        this.loading = false;
    },

    async loadNotifications() {
        try {
            let url = '/api/notifications';
            if (this.activeTab === 'unread') {
                url += '?unread_only=true';
            }
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.notifications = json.data;
            }
        } catch (e) {
            console.error('Failed to load notifications', e);
        }
    },

    async loadPreferences() {
        try {
            const res = await fetch('/api/notifications/preferences', {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.status === 'success' && json.data) {
                this.preferences = json.data;
            }
        } catch (e) {
            console.error('Failed to load preferences', e);
        }
    },

    async toggleRead(id) {
        try {
            const res = await fetch(`/api/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.notifications = this.notifications.map(n => n.id === id ? { ...n, is_read: true } : n);
                // Update global header notifications if needed
                if (window.updateHeaderNotifications) {
                    window.updateHeaderNotifications();
                }
                this.triggerToast('Notification marked as read.', 'success');
            }
        } catch (e) {
            console.error('Error toggling read status', e);
        }
    },

    async markAllAsRead() {
        try {
            const res = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.notifications = this.notifications.map(n => ({ ...n, is_read: true }));
                if (window.updateHeaderNotifications) {
                    window.updateHeaderNotifications();
                }
                this.triggerToast('All notifications marked as read.', 'success');
            }
        } catch (e) {
            console.error('Error marking all as read', e);
        }
    },

    async savePreferences() {
        try {
            const res = await fetch('/api/notifications/preferences', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                },
                body: JSON.stringify(this.preferences)
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.triggerToast('Preferences saved successfully.', 'success');
            }
        } catch (e) {
            console.error('Error saving preferences', e);
            this.triggerToast('Failed to save preferences.', 'danger');
        }
    },

    async triggerTestNotification() {
        try {
            const res = await fetch('/api/notifications/trigger-test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                },
                body: JSON.stringify({
                    title: this.testTitle,
                    body: this.testBody,
                    type: this.testType,
                    category: this.testCategory,
                    priority: this.testPriority
                })
            });
            const json = await res.json();
            if (json.status === 'success') {
                await this.loadNotifications();
                if (window.updateHeaderNotifications) {
                    window.updateHeaderNotifications();
                }
                this.triggerToast('Test notification dispatched!', 'success');
            }
        } catch (e) {
            console.error('Error triggering test', e);
        }
    },

    formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
}">

    <!-- Page Header Hero Block -->
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 dark:text-white sm:truncate sm:text-3xl tracking-tight">Notification Center</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Manage your real-time alerts, email subscriptions, and channel notification routing.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-x-3">
            <button type="button" @click="markAllAsRead()" class="inline-flex items-center rounded-xl bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">
                Mark All as Read
            </button>
        </div>
    </div>

    <!-- Main Content Split layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left 2 Cols: Notification Logs -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Filters & Tabs bar -->
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2">
                <div class="flex gap-x-4">
                    <button type="button" 
                        @click="activeTab = 'all'; loadNotifications();"
                        class="text-xs font-medium pb-2 border-b-2 px-1 transition"
                        :class="activeTab === 'all' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'">
                        All Notifications
                    </button>
                    <button type="button" 
                        @click="activeTab = 'unread'; loadNotifications();"
                        class="text-xs font-medium pb-2 border-b-2 px-1 transition relative"
                        :class="activeTab === 'unread' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'">
                        Unread Only
                        <span x-show="notifications.filter(n => !n.is_read).length > 0" class="absolute -top-1 -right-2 h-2.5 w-2.5 rounded-full bg-indigo-600"></span>
                    </button>
                </div>
                
                <button type="button" @click="loadNotifications()" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-x-1 hover:underline">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Refresh
                </button>
            </div>

            <!-- Loader -->
            <div x-show="loading" class="flex flex-col items-center justify-center py-20">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent"></div>
                <p class="text-xs text-slate-500 mt-2">Loading logs...</p>
            </div>

            <!-- Notification Logs List -->
            <div x-show="!loading" class="space-y-4">
                
                <template x-for="notif in notifications" :key="notif.id">
                    <div class="p-4 rounded-xl border bg-white dark:bg-slate-900 transition flex items-start gap-x-4 hover:shadow-sm"
                        :class="notif.is_read ? 'border-slate-100 dark:border-slate-800 opacity-75' : 'border-indigo-100 dark:border-indigo-950/50 bg-indigo-50/10 dark:bg-indigo-950/5'">
                        
                        <!-- Icon representation depending on type/category -->
                        <div class="p-2 rounded-lg flex-shrink-0"
                            :class="{
                                'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-400': notif.type === 'success',
                                'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400': notif.type === 'info',
                                'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400': notif.type === 'warning',
                                'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-400': notif.type === 'error'
                            }">
                            <!-- success -->
                            <template x-if="notif.type === 'success'">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <!-- warning / error -->
                            <template x-if="notif.type === 'warning' || notif.type === 'error'">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            </template>
                            <!-- info -->
                            <template x-if="notif.type === 'info'">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </template>
                        </div>

                        <!-- Text Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-x-2">
                                <h3 class="text-xs font-bold text-slate-900 dark:text-white" x-text="notif.title"></h3>
                                <span class="rounded px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400" x-text="notif.category"></span>
                                <span x-show="notif.priority === 'urgent' || notif.priority === 'high'" class="rounded px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-400" x-text="notif.priority"></span>
                            </div>
                            <p class="text-slate-600 dark:text-slate-400 mt-1 text-xs" x-text="notif.body"></p>
                            <span class="text-[10px] text-slate-400 mt-2 block" x-text="formatDate(notif.created_at)"></span>
                        </div>

                        <!-- Read Action trigger -->
                        <div class="flex-shrink-0" x-show="!notif.is_read">
                            <button type="button" @click="toggleRead(notif.id)" class="text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 hover:underline">
                                Mark Read
                            </button>
                        </div>
                    </div>
                </template>

                <template x-if="notifications.length === 0">
                    <div class="border border-dashed border-slate-300 dark:border-slate-800 rounded-xl p-12 text-center text-slate-400 italic">
                        No active notifications found in this stream. Use the Demo Trigger below to instantly dispatch a live alert!
                    </div>
                </template>
            </div>

            <!-- Demo triggering sandbox center -->
            <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-950 dark:text-white flex items-center gap-x-2">
                        <span class="h-2 w-2 rounded-full bg-indigo-600 animate-ping"></span>
                        Demo Alert Dispatch Sandbox
                    </h3>
                    <p class="text-[10px] text-slate-500 mt-1">Simulate instant pipeline notifications to verify database writing, real-time toast alerts, and system-configured delivery.</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase">Alert Title</label>
                            <input type="text" x-model="testTitle" class="w-full mt-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase">Message Body</label>
                            <textarea x-model="testBody" rows="2" class="w-full mt-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Category</label>
                                <select x-model="testCategory" class="w-full mt-1 px-2 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option value="system">System</option>
                                    <option value="billing">Billing</option>
                                    <option value="crm">CRM</option>
                                    <option value="security">Security</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Severity Type</label>
                                <select x-model="testType" class="w-full mt-1 px-2 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Orange)</option>
                                    <option value="error">Critical Error (Red)</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Priority</label>
                                <select x-model="testPriority" class="w-full mt-1 px-2 py-1.5 rounded-lg border border-slate-200 bg-white text-xs text-slate-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="triggerTestNotification()" class="w-full rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Dispatch Alert
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Col: Preferences panel -->
        <div class="space-y-6">
            
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 space-y-6">
                
                <div>
                    <h3 class="text-xs font-bold text-slate-950 dark:text-white">Channels Routing</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Toggle active delivery methods for incoming notifications.</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="chan-db" type="checkbox" x-model="preferences.channels.database" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="chan-db" class="font-semibold text-slate-800 dark:text-white">In-App Notification Center</label>
                            <p class="text-slate-400 text-[10px]">Store in the database and display inside the bell dropdown.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="chan-email" type="checkbox" x-model="preferences.channels.email" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="chan-email" class="font-semibold text-slate-800 dark:text-white">Email Subscriptions</label>
                            <p class="text-slate-400 text-[10px]">Dispatch an email copy of each notification asynchronously.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="chan-toast" type="checkbox" x-model="preferences.channels.toast" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="chan-toast" class="font-semibold text-slate-800 dark:text-white">Real-time Popups (Toasts)</label>
                            <p class="text-slate-400 text-[10px]">Show instant sliding toast popups while browsing the app.</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 pt-6">
                    <h3 class="text-xs font-bold text-slate-950 dark:text-white">Category Filters</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Silence or allow alert categories entirely.</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="cat-system" type="checkbox" x-model="preferences.categories.system" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="cat-system" class="font-semibold text-slate-800 dark:text-white">System & DevOps</label>
                            <p class="text-slate-400 text-[10px]">Database backups, container logs, system upgrades.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="cat-billing" type="checkbox" x-model="preferences.categories.billing" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="cat-billing" class="font-semibold text-slate-800 dark:text-white">Billing & Invoices</label>
                            <p class="text-slate-400 text-[10px]">SaaS subscription, payment success, Safaricom integrations.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="cat-crm" type="checkbox" x-model="preferences.categories.crm" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="cat-crm" class="font-semibold text-slate-800 dark:text-white">CRM & Lead Pipes</label>
                            <p class="text-slate-400 text-[10px]">New customer leads captured, customer support updates.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="cat-security" type="checkbox" x-model="preferences.categories.security" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="cat-security" class="font-semibold text-slate-800 dark:text-white">Security Audits</label>
                            <p class="text-slate-400 text-[10px]">Failed logins, password resets, critical policy warnings.</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 pt-4 flex">
                    <button type="button" @click="savePreferences()" class="w-full rounded-xl bg-slate-900 text-white py-2 text-xs font-bold hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 shadow-sm">
                        Save Preference Configuration
                    </button>
                </div>

            </div>

        </div>

    </div>
</div>
@endsection
