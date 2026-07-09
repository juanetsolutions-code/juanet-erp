@extends('layouts.app')

@section('header', 'Enterprise Notification & Communication Center')

@section('content')
<div class="space-y-8" x-data="{
    notifications: [],
    history: [],
    preferences: {
        channels: { database: true, email: true, sms: false, whatsapp: false, push: false, webhook: false },
        categories: { system: true, billing: true, crm: true, security: true }
    },
    templates: @json($templates ?? []),
    loading: true,
    activeTab: 'inbox', // inbox, history, preferences, templates
    searchQuery: '',
    filterCategory: '',
    filterPriority: '',
    filterStatus: 'all', // all, unread, read
    groupMode: 'none', // none, category, priority
    testTitle: 'Database Replication Alert',
    testBody: 'Primary replica latency has spiked above 250ms. High availability failover is operational.',
    testType: 'warning',
    testCategory: 'system',
    testPriority: 'high',

    async init() {
        await this.loadNotifications();
        await this.loadPreferences();
        this.loading = false;
    },

    async loadNotifications() {
        this.loading = true;
        try {
            // Build query params
            let params = new URLSearchParams();
            if (this.filterStatus === 'unread') {
                params.append('unread_only', 'true');
            }
            if (this.filterCategory) {
                params.append('category', this.filterCategory);
            }
            if (this.filterPriority) {
                params.append('priority', this.filterPriority);
            }
            if (this.searchQuery) {
                params.append('search', this.searchQuery);
            }

            const res = await fetch('/api/notifications?' + params.toString(), {
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
        } finally {
            this.loading = false;
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
                if (window.updateHeaderNotifications) {
                    window.updateHeaderNotifications();
                }
                this.triggerToast('Notification marked as read.', 'success');
            }
        } catch (e) {
            console.error('Error marking notification as read', e);
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

    async archiveNotification(id) {
        try {
            const res = await fetch(`/api/notifications/${id}/archive`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.notifications = this.notifications.filter(n => n.id !== id);
                this.triggerToast('Notification archived.', 'success');
            }
        } catch (e) {
            console.error('Error archiving notification', e);
        }
    },

    async deleteNotification(id) {
        if(!confirm('Are you sure you want to permanently delete this notification?')) return;
        try {
            const res = await fetch(`/api/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.notifications = this.notifications.filter(n => n.id !== id);
                this.triggerToast('Notification permanently deleted.', 'success');
            }
        } catch (e) {
            console.error('Error deleting notification', e);
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
                this.triggerToast('Notification preferences updated successfully.', 'success');
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
                this.triggerToast('Test notification dispatched through system engines!', 'success');
            }
        } catch (e) {
            console.error('Error triggering test', e);
        }
    },

    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },

    getGroupedNotifications() {
        if (this.groupMode === 'none') {
            return { 'All notifications': this.notifications };
        }
        
        let groups = {};
        this.notifications.forEach(n => {
            let key = '';
            if (this.groupMode === 'category') {
                key = n.category ? n.category.toUpperCase() : 'SYSTEM';
            } else if (this.groupMode === 'priority') {
                key = n.priority ? n.priority.toUpperCase() : 'NORMAL';
            }
            if (!groups[key]) groups[key] = [];
            groups[key].push(n);
        });
        return groups;
    },

    getDeliveryStatusColor(status) {
        switch(status) {
            case 'delivered': return 'text-green-600 bg-green-50 dark:bg-green-950/30 dark:text-green-400';
            case 'sent': return 'text-blue-600 bg-blue-50 dark:bg-blue-950/30 dark:text-blue-400';
            case 'failed': return 'text-red-600 bg-red-50 dark:bg-red-950/30 dark:text-red-400';
            default: return 'text-slate-600 bg-slate-50 dark:bg-slate-850 dark:text-slate-400';
        }
    }
}">

    <!-- Page Header Hero Block -->
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 dark:text-white sm:truncate sm:text-3xl tracking-tight font-sans">Enterprise Notification Hub</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Centralized delivery system for CRM, Marketplace, Proposals, and System telemetry.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-x-3">
            <button type="button" @click="markAllAsRead()" class="inline-flex items-center rounded-xl bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">
                Mark All as Read
            </button>
            <button type="button" @click="activeTab = 'preferences'" class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500">
                Preferences
            </button>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-800">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button @click="activeTab = 'inbox'; loadNotifications();"
                :class="activeTab === 'inbox' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="whitespace-nowrap border-b-2 py-4 px-1 text-xs font-medium transition cursor-pointer">
                Inbox
                <span x-show="notifications.filter(n => !n.is_read).length > 0" class="ml-2 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400" x-text="notifications.filter(n => !n.is_read).length"></span>
            </button>
            <button @click="activeTab = 'templates'"
                :class="activeTab === 'templates' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="whitespace-nowrap border-b-2 py-4 px-1 text-xs font-medium transition cursor-pointer">
                Centralized Templates
            </button>
            <button @click="activeTab = 'preferences'"
                :class="activeTab === 'preferences' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="whitespace-nowrap border-b-2 py-4 px-1 text-xs font-medium transition cursor-pointer">
                Preferences & Routing
            </button>
            <button @click="activeTab = 'sandbox'"
                :class="activeTab === 'sandbox' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="whitespace-nowrap border-b-2 py-4 px-1 text-xs font-medium transition cursor-pointer">
                Testing Sandbox
            </button>
        </nav>
    </div>

    <!-- Tab Contents -->
    <div>
        <!-- TAB: INBOX -->
        <div x-show="activeTab === 'inbox'" class="space-y-6">
            
            <!-- Filters & Search Toolbar -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                <!-- Search -->
                <div class="md:col-span-2 relative">
                    <input type="text" x-model="searchQuery" @input.debounce.300ms="loadNotifications()" placeholder="Search notifications..." class="w-full pl-9 pr-4 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <svg class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <!-- Category Filter -->
                <div>
                    <select x-model="filterCategory" @change="loadNotifications()" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none">
                        <option value="">All Categories</option>
                        <option value="system">System</option>
                        <option value="billing">Billing</option>
                        <option value="crm">CRM</option>
                        <option value="security">Security</option>
                    </select>
                </div>
                <!-- Priority Filter -->
                <div>
                    <select x-model="filterPriority" @change="loadNotifications()" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <!-- Status Filter -->
                <div>
                    <select x-model="filterStatus" @change="loadNotifications()" class="w-full px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none">
                        <option value="all">All Status</option>
                        <option value="unread">Unread Only</option>
                    </select>
                </div>
            </div>

            <!-- Grouping Selector & Metrics Summary -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-x-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                    <button @click="groupMode = 'none'" :class="groupMode === 'none' ? 'bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500'" class="px-2.5 py-1 rounded-md text-[10px] cursor-pointer">List</button>
                    <button @click="groupMode = 'category'" :class="groupMode === 'category' ? 'bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500'" class="px-2.5 py-1 rounded-md text-[10px] cursor-pointer">Group by Category</button>
                    <button @click="groupMode = 'priority'" :class="groupMode === 'priority' ? 'bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500'" class="px-2.5 py-1 rounded-md text-[10px] cursor-pointer">Group by Priority</button>
                </div>
                <div class="text-[10px] text-slate-400 font-mono">
                    Showing <span x-text="notifications.length"></span> active notifications
                </div>
            </div>

            <!-- Notification List Rendering -->
            <div x-show="loading" class="flex flex-col items-center justify-center py-20">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent"></div>
                <p class="text-xs text-slate-500 mt-2 font-mono">Loading telemetry stream...</p>
            </div>

            <div x-show="!loading" class="space-y-6">
                <template x-for="(groupItems, groupName) in getGroupedNotifications()" :key="groupName">
                    <div class="space-y-3">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider font-mono px-1" x-text="groupName"></div>
                        
                        <div class="grid grid-cols-1 gap-3">
                            <template x-for="notif in groupItems" :key="notif.id">
                                <div class="p-4 rounded-xl border bg-white dark:bg-slate-900 transition flex flex-col md:flex-row md:items-center justify-between gap-4 hover:shadow-sm"
                                    :class="notif.is_read ? 'border-slate-100 dark:border-slate-800 opacity-75' : 'border-indigo-100 dark:border-indigo-950/50 bg-indigo-50/10 dark:bg-indigo-950/5'">
                                    
                                    <div class="flex items-start gap-x-4">
                                        <!-- Severity Indicator Icon -->
                                        <div class="p-2 rounded-lg flex-shrink-0"
                                            :class="{
                                                'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-400': notif.type === 'success',
                                                'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400': notif.type === 'info',
                                                'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400': notif.type === 'warning',
                                                'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-400': notif.type === 'error'
                                            }">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>

                                        <!-- Notification Meta Details -->
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-xs font-bold text-slate-900 dark:text-white" x-text="notif.title"></h4>
                                                <span class="rounded px-1.5 py-0.5 text-[8px] font-mono font-bold uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400" x-text="notif.category || 'system'"></span>
                                                <span class="rounded px-1.5 py-0.5 text-[8px] font-mono font-bold uppercase tracking-wider"
                                                    :class="{
                                                        'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-400': notif.priority === 'urgent' || notif.priority === 'high',
                                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400': notif.priority === 'normal' || notif.priority === 'low'
                                                    }" x-text="notif.priority || 'normal'"></span>
                                            </div>
                                            <p class="text-slate-600 dark:text-slate-400 mt-1 text-xs" x-text="notif.body"></p>
                                            
                                            <!-- Dynamic Delivery Channels Output Tracker -->
                                            <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-slate-400 border-t border-slate-50 dark:border-slate-850 pt-2 font-mono">
                                                <span class="text-[9px] uppercase font-bold text-slate-400">Channels Tracker:</span>
                                                <span class="flex items-center gap-1.5">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> In-App
                                                </span>
                                                <!-- Mock simulated email and SMS tracking based on preference settings -->
                                                <span class="flex items-center gap-1.5">
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="preferences.channels.email ? 'bg-green-500' : 'bg-slate-300'"></span> Email
                                                </span>
                                                <span class="flex items-center gap-1.5">
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="preferences.channels.sms ? 'bg-green-500' : 'bg-slate-300'"></span> SMS
                                                </span>
                                                <span class="flex items-center gap-1.5">
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="preferences.channels.whatsapp ? 'bg-green-500' : 'bg-slate-300'"></span> WhatsApp
                                                </span>
                                                <span class="flex items-center gap-1.5">
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="preferences.channels.push ? 'bg-green-500' : 'bg-slate-300'"></span> Push
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Bar -->
                                    <div class="flex items-center gap-x-2 self-end md:self-center">
                                        <button x-show="!notif.is_read" type="button" @click="toggleRead(notif.id)" class="inline-flex items-center rounded-lg bg-indigo-50 dark:bg-indigo-950/30 px-2 py-1 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100">
                                            Mark Read
                                        </button>
                                        <button type="button" @click="archiveNotification(notif.id)" class="inline-flex items-center rounded-lg bg-slate-50 dark:bg-slate-800 px-2 py-1 text-[10px] font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100">
                                            Archive
                                        </button>
                                        <button type="button" @click="deleteNotification(notif.id)" class="inline-flex items-center rounded-lg bg-red-50 dark:bg-red-950/20 px-2 py-1 text-[10px] font-bold text-red-600 hover:bg-red-100">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="notifications.length === 0">
                    <div class="border border-dashed border-slate-300 dark:border-slate-800 rounded-xl p-12 text-center text-slate-400 italic">
                        No active alerts match the filters. Try sending a mock alert from the Testing Sandbox!
                    </div>
                </template>
            </div>
        </div>

        <!-- TAB: TEMPLATES -->
        <div x-show="activeTab === 'templates'" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white font-sans">Centralized Template Catalog</h3>
                <p class="text-xs text-slate-500 mt-1">Multi-channel localized layouts rendered via Blade dynamic interpolation engines.</p>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <template x-for="tpl in templates" :key="tpl.id">
                        <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold font-mono text-indigo-600 dark:text-indigo-400" x-text="tpl.name"></span>
                                    <span class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.5 bg-slate-200 text-slate-600 rounded" x-text="tpl.channel"></span>
                                </div>
                                <div class="mt-3 text-xs font-bold text-slate-800 dark:text-white" x-text="tpl.subject_template"></div>
                                <div class="mt-2 text-[11px] text-slate-500 font-mono bg-white dark:bg-slate-950 p-2.5 rounded border border-slate-100 dark:border-slate-850 whitespace-pre-wrap" x-text="tpl.body_template"></div>
                            </div>
                            <div class="mt-4 flex items-center justify-between text-[10px] text-slate-400">
                                <span x-text="'Loc: ' + (tpl.locale || 'en')"></span>
                                <span x-text="'ID: ' + tpl.id"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="templates.length === 0">
                        <div class="col-span-2 border border-dashed border-slate-300 dark:border-slate-800 rounded-xl p-12 text-center text-slate-400 italic">
                            No active templates found in this tenant context.
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- TAB: PREFERENCES -->
        <div x-show="activeTab === 'preferences'" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Channel Subscriptions -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white font-sans">Multi-Channel Delivery Channels</h3>
                    <p class="text-xs text-slate-500 mt-1">Route critical enterprise telemetry into dedicated communication sinks.</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-db" type="checkbox" x-model="preferences.channels.database" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-db" class="font-bold text-slate-800 dark:text-white">In-App Notification Stream</label>
                            <p class="text-slate-400 text-[10px]">Deliver directly into your bell dropdown tray.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-email" type="checkbox" x-model="preferences.channels.email" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-email" class="font-bold text-slate-800 dark:text-white">Asynchronous Email Copy</label>
                            <p class="text-slate-400 text-[10px]">Email updates dispatched through tenant mail exchanges.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-sms" type="checkbox" x-model="preferences.channels.sms" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-sms" class="font-bold text-slate-800 dark:text-white">Direct SMS Alerts (Safaricom M-PESA & Twilio)</label>
                            <p class="text-slate-400 text-[10px]">Send instantaneous text messages to verified lines.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-whatsapp" type="checkbox" x-model="preferences.channels.whatsapp" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-whatsapp" class="font-bold text-slate-800 dark:text-white">WhatsApp Sandbox Integrations</label>
                            <p class="text-slate-400 text-[10px]">Automate customer support chats directly inside mobile threads.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-push" type="checkbox" x-model="preferences.channels.push" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-push" class="font-bold text-slate-800 dark:text-white">Web Push Subscriptions</label>
                            <p class="text-slate-400 text-[10px]">Deliver instant web notifications directly into browser trays.</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                    <button type="button" @click="savePreferences()" class="w-full rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 py-2.5 text-xs font-bold hover:opacity-90 transition">
                        Update Delivery Map Configuration
                    </button>
                </div>
            </div>

            <!-- Categories Filters -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white font-sans">Enterprise Category Routing</h3>
                    <p class="text-xs text-slate-500 mt-1">Silence or isolate specific business workflow streams entirely.</p>
                </div>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-sys" type="checkbox" x-model="preferences.categories.system" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-sys" class="font-bold text-slate-800 dark:text-white">System, IT & Core DevOps</label>
                            <p class="text-slate-400 text-[10px]">Automated database copies, failover events, server telemetry.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-bill" type="checkbox" x-model="preferences.categories.billing" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-bill" class="font-bold text-slate-800 dark:text-white">Billing, Subscriptions & Invoicing</label>
                            <p class="text-slate-400 text-[10px]">Payment captures, subscription changes, invoice drafts.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-crm" type="checkbox" x-model="preferences.categories.crm" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-crm" class="font-bold text-slate-800 dark:text-white">CRM Lead Ingestion Pipes</label>
                            <p class="text-slate-400 text-[10px]">Real-time leads created, assigned agents, and conversions.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex h-5 items-center">
                            <input id="p-sec" type="checkbox" x-model="preferences.categories.security" class="h-4 w-4 rounded border-slate-300 text-indigo-600 dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div class="ml-3 text-xs">
                            <label for="p-sec" class="font-bold text-slate-800 dark:text-white">Security Audits & Isolation Alarms</label>
                            <p class="text-slate-400 text-[10px]">Failed credentials, policy exceptions, boundary violations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: SANDBOX -->
        <div x-show="activeTab === 'sandbox'" class="space-y-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 space-y-6 max-w-3xl mx-auto">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white font-sans">Mock Incident Control panel</h3>
                    <p class="text-xs text-slate-500 mt-1">Directly trigger pipeline events to check localized templating and transactional logs.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold font-mono text-slate-400 uppercase">Alert Title / Subject Template</label>
                            <input type="text" x-model="testTitle" class="w-full mt-1.5 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold font-mono text-slate-400 uppercase">Telemetry Message Body</label>
                            <textarea x-model="testBody" rows="3" class="w-full mt-1.5 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"></textarea>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold font-mono text-slate-400 uppercase">Category</label>
                                <select x-model="testCategory" class="w-full mt-1.5 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none">
                                    <option value="system">System</option>
                                    <option value="billing">Billing</option>
                                    <option value="crm">CRM</option>
                                    <option value="security">Security</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold font-mono text-slate-400 uppercase">Type</label>
                                <select x-model="testType" class="w-full mt-1.5 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none">
                                    <option value="info">Info</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold font-mono text-slate-400 uppercase">Priority</label>
                                <select x-model="testPriority" class="w-full mt-1.5 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-xs text-slate-800 dark:text-white focus:outline-none">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="triggerTestNotification()" class="w-full rounded-xl bg-indigo-600 px-3 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 cursor-pointer">
                                    Dispatch Event
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
