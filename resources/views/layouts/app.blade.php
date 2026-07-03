<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ 
    darkMode: localStorage.getItem('darkMode') === 'true',
    sidebarOpen: false,
    commandPaletteOpen: false,
    notificationsOpen: false,
    searchQuery: '',
    notifications: [],
    toastMessage: '',
    toastType: 'success',
    showToast: false,
    
    async init() {
        @auth
            await this.fetchHeaderNotifications();
            window.updateHeaderNotifications = () => this.fetchHeaderNotifications();
            window.triggerGlobalToast = (msg, type) => this.triggerToast(msg, type);
        @endauth
    },
    async fetchHeaderNotifications() {
        try {
            const res = await fetch('/api/notifications?unread_only=true', {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.notifications = json.data;
            }
        } catch (e) {
            console.error(e);
        }
    },
    async markHeaderAsRead(id) {
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
                this.notifications = this.notifications.filter(n => n.id !== id);
                this.triggerToast('Notification marked as read.', 'success');
            }
        } catch (e) {
            console.error(e);
        }
    },
    async clearAllHeader() {
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
                this.notifications = [];
                this.triggerToast('All notifications cleared.', 'success');
            }
        } catch (e) {
            console.error(e);
        }
    },
    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode);
    },
    triggerToast(message, type = 'success') {
        this.toastMessage = message;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => this.showToast = false, 4000);
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'JUANET SaaS Platform') }}</title>

    <!-- Tailwind CSS Play CDN & Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                    },
                    boxShadow: {
                        'premium': '0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 2px 8px -1px rgba(0, 0, 0, 0.03)',
                        'premium-dark': '0 4px 20px -2px rgba(0, 0, 0, 0.3), 0 2px 8px -1px rgba(0, 0, 0, 0.2)',
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Inter', sans-serif;
        }
        code, pre {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 transition-colors duration-200 dark:bg-slate-950 dark:text-slate-100" @keydown.window.ctrl.k.prevent="commandPaletteOpen = true">

    <div class="flex h-full overflow-hidden">
        
        <!-- SIDEBAR FOR DESKTOP -->
        <aside class="hidden lg:flex lg:flex-shrink-0 lg:flex-col lg:w-64 lg:border-r lg:border-slate-200 lg:bg-white dark:lg:bg-slate-900 dark:lg:border-slate-800 transition-colors duration-200">
            <!-- Sidebar Header -->
            <div class="flex h-16 items-center px-6 border-b border-slate-100 dark:border-slate-800">
                <span class="text-lg font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-x-2">
                    <span class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-extrabold text-sm shadow-sm">J</span>
                    JUANET <span class="text-[10px] font-bold text-indigo-600 px-1.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 dark:text-indigo-400">SAAS</span>
                </span>
            </div>

            <!-- Sidebar Workspace Context Selector -->
            @auth
            @if(isset($currentTenant))
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                <div class="flex items-center gap-x-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer" onclick="window.location='{{ route('organization.index') }}'">
                    <div class="h-9 w-9 rounded-md bg-indigo-100 dark:bg-indigo-950 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-sm uppercase">
                        {{ substr($currentTenant->name, 0, 2) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">{{ $currentTenant->name }}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ $currentTenant->domain ?: 'sandbox.juanet.io' }}</p>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
            @endif
            @endauth

            <!-- Sidebar Menu Items -->
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
                
                <!-- Core Navigation Section -->
                <div>
                    <span class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-2">Core Application</span>
                    <nav class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('dashboard') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <!-- Dashboard Icon -->
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                        
                        <a href="{{ route('crm.leads.index') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('crm.*') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94-3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            CRM
                            <span class="ml-auto rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-semibold text-indigo-600 dark:bg-indigo-950/80 dark:text-indigo-400">Active</span>
                        </a>

                        <a href="#" @click.prevent="triggerToast('Marketplace module will be loaded dynamically.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.5a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75h-3.5a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                            </svg>
                            Marketplace
                        </a>

                        <a href="#" @click.prevent="triggerToast('CMS core module loaded.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
                            </svg>
                            CMS
                        </a>
                    </nav>
                </div>

                <!-- Shared Module Workspaces -->
                <div>
                    <span class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-2">Workspace Modules</span>
                    <nav class="space-y-1">
                        <a href="#" @click.prevent="triggerToast('Project tracking dashboards are loaded automatically.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m-15 0a2.247 2.247 0 0 0-.75-.128H3.75a1.125 1.125 0 0 0-1.125 1.125v6.563c0 .621.504 1.125 1.125 1.125h16.5a1.125 1.125 0 0 0 1.125-1.125v-6.563a1.125 1.125 0 0 0-1.125-1.125h-.75c-.263 0-.515.045-.75.128m-15 0V18a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 18 18V9.75" />
                            </svg>
                            Projects
                        </a>

                        <a href="#" @click.prevent="triggerToast('Finance Hub & Billing Ledger loaded.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Finance
                        </a>

                        <a href="#" @click.prevent="triggerToast('Enterprise Support tickets desk loaded.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.598.598 0 0 1-.655-.005.602.602 0 0 1-.225-.436 9.07 9.07 0 0 1 .428-3.931C3.793 15.022 3 13.583 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                            Support
                        </a>

                        <a href="{{ route('storage.index') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('storage.index') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.008 1.24l.885 1.77a2.25 2.25 0 0 0 2.007 1.24h1.98a2.25 2.25 0 0 0 2.007-1.24l.885-1.77a2.25 2.25 0 0 1 2.007-1.24h3.86m-18 0h18M2.25 13.5l1.125-11.25a2.25 2.25 0 0 1 2.24-2.02h12.77a2.25 2.25 0 0 1 2.24 2.02L21.75 13.5m-19.5 0v5.625A2.25 2.25 0 0 0 4.5 21.375h15a2.25 2.25 0 0 0 2.25-2.25V13.5" />
                            </svg>
                            Storage Vault
                        </a>

                        <a href="{{ route('search.index') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('search.index') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.637 10.537Z" />
                            </svg>
                            Global Search
                        </a>
                    </nav>
                </div>

                <!-- Automation & Intelligence Section -->
                <div>
                    <span class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-2">Intelligence & Workflows</span>
                    <nav class="space-y-1">
                        <a href="#" @click.prevent="triggerToast('Workflow orchestrations & Lipa Na M-PESA hooks resolved.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                            Automation
                            <span class="ml-auto rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-600 dark:bg-emerald-950/80 dark:text-emerald-400">Live</span>
                        </a>

                        <a href="#" @click.prevent="triggerToast('Gemini API Integration active inside JUANET SaaS pipeline.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l-1.81-5.096L2.094 15 7.19 13.19 9 8l1.81 5.19 5.096 1.81-5.096 1.904zM18.094 5.096L17 9l-1.096-3.904L12 4l3.904-1.096L17 0l1.096 2.904L22 4l-3.904 1.096z" />
                            </svg>
                            AI Agent
                            <span class="ml-auto rounded bg-blue-50 px-1.5 py-0.5 text-[9px] font-semibold text-blue-600 dark:bg-blue-950/80 dark:text-blue-400">Gemini</span>
                        </a>
                    </nav>
                </div>

                <!-- Account Settings & Control Panel -->
                <div>
                    <span class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-2">System Controls</span>
                    <nav class="space-y-1">
                        <a href="{{ route('organization.index') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('organization.index') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.905 0-5.62-.515-8.127-1.458m16.254 0a11.02 11.02 0 0 0-1.611-3.418m-13.032 3.418a11.02 11.02 0 0 1 1.611-3.418" />
                            </svg>
                            Administration
                        </a>

                        <a href="{{ route('settings.index') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('settings.index') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.24-2.03-.532a.75.75 0 1 1 .624-1.36c.478.22.994.35 1.516.395a1.125 1.125 0 0 0 1.22-1.12V9.75c0-.621-.504-1.125-1.125-1.125h-2.25a1.125 1.125 0 0 0-1.125 1.125v4.125c0 .621.504 1.125 1.125 1.125h.75a.75.75 0 0 1 0 1.5H9.75a2.25 2.25 0 0 1-2.25-2.25V9.75a2.25 2.25 0 0 1 2.25-2.25h2.25a2.25 2.25 0 0 1 2.25 2.25v3.515a2.625 2.625 0 0 1-2.625 2.625h-1.285ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Enterprise Config
                        </a>

                        <a href="{{ route('profile') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('profile') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a6.723 6.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            Profile Settings
                        </a>
                    </nav>
                </div>

            </div>

            <!-- Sidebar Footer User Profile -->
            @auth
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-900/20">
                <div class="flex items-center gap-x-3">
                    <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-xs uppercase shadow-sm">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">{{ Auth::user()->name }}</p>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ Auth::user()->email }}</p>
                    </div>
                    
                    <form method="POST" action="{{ route('logout') }}" class="block">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-red-500 dark:text-slate-500 dark:hover:text-red-400 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </aside>

        <!-- MOBILE SIDEBAR DRAWER -->
        <div x-show="sidebarOpen" class="relative z-50 lg:hidden" x-cloak>
            <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm"></div>

            <div class="fixed inset-0 flex">
                <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative flex w-full max-w-xs flex-1 flex-col bg-white dark:bg-slate-900 pt-5 pb-4" @click.away="sidebarOpen = false">
                    <!-- Close button -->
                    <div class="absolute top-0 right-0 -mr-12 pt-2">
                        <button type="button" class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" @click="sidebarOpen = false">
                            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-shrink-0 items-center px-6 mb-4">
                        <span class="text-lg font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-x-2">
                            <span class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-extrabold text-sm shadow-sm">J</span>
                            JUANET <span class="text-[10px] font-bold text-indigo-600 px-1.5 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 dark:text-indigo-400">SAAS</span>
                        </span>
                    </div>

                    <!-- Mobile navigation items -->
                    <div class="flex-1 overflow-y-auto px-4 py-2 space-y-6">
                        <div>
                            <span class="px-3 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-2">Core Application</span>
                            <nav class="space-y-1">
                                <a href="{{ route('dashboard') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('dashboard') ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                    Dashboard
                                </a>
                                <a href="#" @click.prevent="triggerToast('CRM unlocked in Phase 3.5.', 'info')" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                                    CRM
                                </a>
                                <a href="{{ route('organization.index') }}" class="group flex items-center gap-x-3 rounded-lg px-3 py-2 text-xs font-medium {{ Request::routeIs('organization.index') ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-50' }}">
                                    Administration
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN MAIN PANEL -->
        <div class="flex flex-1 flex-col overflow-hidden">
            
            <!-- TOP HEADER / NAV BAR -->
            <header class="flex h-16 flex-shrink-0 items-center justify-between border-b border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-800 px-6 z-10 transition-colors duration-200">
                
                <div class="flex items-center gap-x-4">
                    <!-- Mobile sidebar toggle -->
                    <button type="button" class="lg:hidden p-1 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white" @click="sidebarOpen = true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <!-- Breadcrumbs / Header text -->
                    <div class="hidden sm:flex items-center gap-x-2 text-xs text-slate-500 dark:text-slate-400 font-medium">
                        <span>JUANET</span>
                        <svg class="h-4 w-4 text-slate-300" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-slate-800 dark:text-white font-semibold">@yield('header', 'Dashboard')</span>
                    </div>
                </div>

                <!-- TOP ACTIONS -->
                <div class="flex items-center gap-x-4">
                    
                    <!-- Global Search Bar / Command Palette trigger -->
                    <button type="button" class="flex items-center gap-x-2 rounded-xl bg-slate-50 px-3 py-1.5 text-xs text-slate-400 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700/50 border border-slate-200 dark:border-slate-700" @click="commandPaletteOpen = true">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <span class="hidden md:inline">Global Search...</span>
                        <kbd class="hidden md:inline-flex items-center gap-x-0.5 rounded border border-slate-200 bg-white px-1 font-sans text-[10px] text-slate-400 dark:bg-slate-900 dark:border-slate-800">ctrl k</kbd>
                    </button>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition" @click="toggleDarkMode()">
                        <!-- Sun (for dark mode active) -->
                        <svg x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m0 13.5V21m9.75-9h-2.25m-13.5 0H3m14.03-7.03l-1.59 1.59M8.28 17.03l-1.59 1.59m10.12 0l1.59-1.59M8.28 6.97l1.59-1.59M12 18.75a6.75 6.75 0 100-13.5 6.75 6.75 0 000 13.5z" />
                        </svg>
                        <!-- Moon (for light mode active) -->
                        <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>

                    <!-- Interactive Notification Center -->
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition relative" @click="open = !open">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <!-- Badge -->
                            <template x-if="notifications.length > 0">
                                <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-indigo-600 animate-pulse"></span>
                            </template>
                        </button>

                        <!-- Notifications Drawer Overlay -->
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 mt-2 w-80 rounded-xl bg-white border border-slate-200 shadow-xl dark:bg-slate-900 dark:border-slate-800 p-4 z-50 text-xs" x-cloak>
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 mb-2">
                                <span class="font-bold text-slate-800 dark:text-white">Workspace Notifications</span>
                                <button type="button" class="text-[10px] text-indigo-600 font-semibold hover:underline" @click="clearAllHeader()">Clear All</button>
                            </div>
                            
                            <div class="space-y-3 max-h-60 overflow-y-auto">
                                <template x-for="notif in notifications" :key="notif.id">
                                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950/40 hover:bg-slate-100 dark:hover:bg-slate-950 transition relative group cursor-pointer" @click="markHeaderAsRead(notif.id)">
                                        <div class="flex items-center justify-between">
                                            <span class="font-semibold text-slate-800 dark:text-white" x-text="notif.title"></span>
                                            <span class="text-[8px] text-indigo-600 dark:text-indigo-400 font-bold opacity-0 group-hover:opacity-100 transition">Mark Read</span>
                                        </div>
                                        <p class="text-slate-500 dark:text-slate-400 mt-0.5 text-[11px]" x-text="notif.body"></p>
                                    </div>
                                </template>
                                <template x-if="notifications.length === 0">
                                    <p class="text-slate-400 italic text-center py-4">No active notifications received.</p>
                                </template>
                            </div>

                            <div class="border-t border-slate-100 dark:border-slate-800 pt-2 mt-2 flex justify-center">
                                <a href="{{ route('notifications.index') }}" class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline">
                                    Go to Notification Center &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User drop-down panel -->
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" class="flex items-center gap-x-2" @click="open = !open">
                            <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-indigo-600 font-bold text-xs uppercase border border-slate-200 dark:border-slate-700">
                                @auth
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                @endauth
                            </div>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 mt-2 w-48 rounded-xl bg-white border border-slate-200 shadow-xl dark:bg-slate-900 dark:border-slate-800 p-2 z-50" x-cloak>
                            <a href="{{ route('profile') }}" class="block px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg">My Account Profile</a>
                            <a href="{{ route('organization.index') }}" class="block px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg">Workspace Admins</a>
                            <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-lg font-medium">Log Out</button>
                            </form>
                        </div>
                    </div>

                </div>
            </header>

            <!-- MAIN WORKING WORKSPACE CONTENT -->
            <main class="flex-1 overflow-y-auto px-6 py-8">
                
                <!-- Toast Alert Banner system -->
                @if (session('status'))
                    <div class="mb-6 rounded-xl bg-green-50/50 p-4 border border-green-200 dark:bg-green-950/10 dark:border-green-900">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-semibold text-green-800 dark:text-green-300">{{ session('status') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-xl bg-red-50/50 p-4 border border-red-200 dark:bg-red-950/10 dark:border-red-900">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-xs font-bold text-red-800 dark:text-red-300">Errors occurred during processing:</h3>
                                <div class="mt-1 text-xs text-red-700 dark:text-red-400">
                                    <ul role="list" class="list-disc pl-5 space-y-0.5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- FOOTER -->
            <footer class="h-10 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between px-6 text-[10px] text-slate-400">
                <div>JUANET SaaS Enterprise Engine v12.0.4 &copy; 2026</div>
                <div class="flex items-center gap-x-2">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-green-500"></span>
                    <span>Database Connection: Live</span>
                </div>
            </footer>

        </div>
    </div>

    <!-- COMMAND PALETTE MODAL (Ctrl + K) -->
    <div x-show="commandPaletteOpen" class="relative z-50" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="commandPaletteOpen = false"></div>
        <div class="fixed inset-0 overflow-y-auto p-4 sm:p-6 md:p-20 flex justify-center items-start">
            <div class="relative w-full max-w-xl transform rounded-xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 transition-all dark:bg-slate-900 border border-slate-200 dark:border-slate-800" @click.away="commandPaletteOpen = false">
                <div class="flex items-center px-4 py-3 border-b border-slate-100 dark:border-slate-800">
                    <svg class="h-5 w-5 text-slate-400 mr-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input type="text" x-model="searchQuery" class="w-full text-slate-800 dark:text-white bg-transparent border-0 placeholder-slate-400 focus:outline-none sm:text-xs" placeholder="Search menus, workflows, CRM profiles, support tickets..." @keydown.escape="commandPaletteOpen = false">
                </div>
                
                <div class="p-4 space-y-4 max-h-80 overflow-y-auto">
                    <!-- Quick shortcuts -->
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block mb-2">Workspace Actions</span>
                        <div class="space-y-1">
                            <a href="{{ route('dashboard') }}" class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-xs text-slate-700 dark:text-slate-300">
                                <span>Go to central Dashboard</span>
                                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-900 border text-[9px] text-slate-400">G D</kbd>
                            </a>
                            <a href="{{ route('organization.index') }}" class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-xs text-slate-700 dark:text-slate-300">
                                <span>Manage Enterprise Organizations</span>
                                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-900 border text-[9px] text-slate-400">G O</kbd>
                            </a>
                            <a href="{{ route('profile') }}" class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-xs text-slate-700 dark:text-slate-300">
                                <span>Manage Account Settings & Security</span>
                                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-900 border text-[9px] text-slate-400">G P</kbd>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GLOBAL TOAST NOTIFICATION CONTAINER -->
    <div
        x-show="showToast"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed bottom-4 right-4 z-50 max-w-sm rounded-xl border p-4 bg-white dark:bg-slate-900 shadow-2xl transition"
        :class="{
            'border-green-200 dark:border-green-900': toastType === 'success',
            'border-blue-200 dark:border-blue-900': toastType === 'info',
            'border-amber-200 dark:border-amber-900': toastType === 'warning',
            'border-red-200 dark:border-red-900': toastType === 'danger',
        }"
        x-cloak
    >
        <div class="flex items-center gap-x-3">
            <template x-if="toastType === 'success'">
                <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
            </template>
            <template x-if="toastType === 'info'">
                <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
            </template>
            <template x-if="toastType === 'warning'">
                <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
            </template>
            <template x-if="toastType === 'danger'">
                <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
            </template>
            <p class="text-xs font-semibold text-slate-800 dark:text-white" x-text="toastMessage"></p>
        </div>
    </div>

</body>
</html>
