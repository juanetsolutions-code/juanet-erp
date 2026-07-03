@extends('layouts.app')

@section('content')
<div x-data="{ 
    activeTab: 'flags',
    showNewSettingModal: false,
    showNewFlagModal: false,
    showNewBetaModal: false,
    
    // Form fields for settings
    settingKey: '',
    settingValue: '',
    settingType: 'string',
    settingGroup: 'platform',
    settingEncrypted: false,
    
    // Form fields for feature flags
    flagKey: '',
    flagDescription: '',
    flagEnabled: true,
    flagBeta: false,
    flagRollout: '',
    flagUsers: '',
    flagOrgs: '',

    init() {
        @if (session('toast_message'))
            this.$nextTick(() => {
                if (typeof window.triggerGlobalToast === 'function') {
                    window.triggerGlobalToast('{{ session('toast_message') }}', '{{ session('toast_type', 'success') }}');
                } else {
                    alert('{{ session('toast_message') }}');
                }
            });
        @endif
    }
}" class="space-y-8">

    <!-- Header Block -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-y-4 border-b border-slate-100 dark:border-slate-800 pb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Enterprise Configuration Console</h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">
                Manage environment variables, settings inheritance hierarchy, and targeted feature flags across users and tenants.
            </p>
        </div>
        <div class="flex items-center gap-x-3">
            <button @click="showNewSettingModal = true" class="inline-flex items-center gap-x-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition px-4 py-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Setting
            </button>
            <button @click="showNewFlagModal = true" class="inline-flex items-center gap-x-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-2 text-xs font-bold text-white shadow-md shadow-indigo-100 dark:shadow-none">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Feature Flag
            </button>
        </div>
    </div>

    <!-- Bento-Grid Stats Widgets -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Widget 1: Active Feature Flags -->
        <div class="bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex items-center gap-x-4">
            <div class="p-3.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.24-2.03-.532a.75.75 0 1 1 .624-1.36c.478.22.994.35 1.516.395a1.125 1.125 0 0 0 1.22-1.12V9.75c0-.621-.504-1.125-1.125-1.125h-2.25a1.125 1.125 0 0 0-1.125 1.125v4.125c0 .621.504 1.125 1.125 1.125h.75a.75.75 0 0 1 0 1.5H9.75a2.25 2.25 0 0 1-2.25-2.25V9.75a2.25 2.25 0 0 1 2.25-2.25h2.25a2.25 2.25 0 0 1 2.25 2.25v3.515a2.625 2.625 0 0 1-2.625 2.625h-1.285ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <div>
                <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Total Feature Flags</span>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mt-1 block">{{ $featureFlags->count() }}</span>
            </div>
        </div>

        <!-- Widget 2: Beta Flags -->
        <div class="bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex items-center gap-x-4">
            <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.67 2.67 0 1113.5 17.25l-5.83-5.83m5.83 3.75l-5.83-5.83M13.5 3.75L21 11.25M18 3.75h3v3M6 16.5H3v-3M6 16.5l-3 3M6 16.5v3M3 13.5l3-3M3 13.5H0M3 13.5V10.5" />
                </svg>
            </div>
            <div>
                <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Beta Gated Features</span>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mt-1 block">{{ $featureFlags->where('is_beta', true)->count() }}</span>
            </div>
        </div>

        <!-- Widget 3: Platform Default Settings -->
        <div class="bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex items-center gap-x-4">
            <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l3 3m0 0l6-6M12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <div>
                <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Platform Settings</span>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mt-1 block">{{ $platformSettings->count() }}</span>
            </div>
        </div>

        <!-- Widget 4: Active Context -->
        <div class="bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex items-center gap-x-4">
            <div class="p-3.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15" />
                </svg>
            </div>
            <div>
                <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-wider block">Tenant Context</span>
                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mt-1 block truncate">
                    {{ $currentOrg ? $currentOrg->name : 'No Org Switch' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Navigation / Tabs Menu -->
    <div class="border-b border-slate-200 dark:border-slate-800">
        <nav class="flex space-x-6">
            <button @click="activeTab = 'flags'" :class="activeTab === 'flags' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold border-b-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'" class="pb-3 text-xs font-semibold tracking-wider uppercase focus:outline-none transition">
                Feature Flags & Targeting
            </button>
            <button @click="activeTab = 'platform'" :class="activeTab === 'platform' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold border-b-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'" class="pb-3 text-xs font-semibold tracking-wider uppercase focus:outline-none transition">
                Platform Configuration
            </button>
            <button @click="activeTab = 'org'" :class="activeTab === 'org' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold border-b-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'" class="pb-3 text-xs font-semibold tracking-wider uppercase focus:outline-none transition">
                Organization Overrides
            </button>
            <button @click="activeTab = 'user'" :class="activeTab === 'user' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold border-b-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'" class="pb-3 text-xs font-semibold tracking-wider uppercase focus:outline-none transition">
                User Settings
            </button>
        </nav>
    </div>

    <!-- TAB 1: FEATURE FLAGS PANEL -->
    <div x-show="activeTab === 'flags'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">Active Global and Beta Feature Toggles</h3>
                <button @click="showNewBetaModal = true" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-x-1">
                    Manage Beta Enrollment &rarr;
                </button>
            </div>
            
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($featureFlags as $flag)
                    <div class="p-6 flex flex-col md:flex-row md:items-start justify-between gap-6 hover:bg-slate-50/20 dark:hover:bg-slate-800/10 transition">
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-x-3">
                                <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded-md">
                                    {{ $flag->key }}
                                </span>
                                @if($flag->is_beta)
                                    <span class="text-[9px] font-bold text-amber-600 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-400 px-1.5 py-0.5 rounded uppercase tracking-wider">
                                        Beta Gated
                                    </span>
                                @endif
                                @if($flag->is_enabled)
                                    <span class="text-[9px] font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-400 px-1.5 py-0.5 rounded uppercase tracking-wider">
                                        Active
                                    </span>
                                @else
                                    <span class="text-[9px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded uppercase tracking-wider">
                                        Disabled
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400">
                                {{ $flag->description ?? 'No description registered for this feature.' }}
                            </p>

                            <!-- Target Rules Display -->
                            @if(!empty($flag->rules))
                                <div class="bg-slate-50 dark:bg-slate-950/40 rounded-xl p-3 border border-slate-100 dark:border-slate-850 space-y-1.5 text-[11px] max-w-xl">
                                    <span class="font-bold text-slate-500 dark:text-slate-400 block mb-1">Targeting Rules:</span>
                                    @if(isset($flag->rules['rollout']))
                                        <div class="flex items-center gap-x-2">
                                            <span class="text-slate-400">Deterministic Rollout:</span>
                                            <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $flag->rules['rollout'] }}%</span>
                                        </div>
                                    @endif
                                    @if(isset($flag->rules['users']))
                                        <div class="flex items-start gap-x-2">
                                            <span class="text-slate-400 whitespace-nowrap">Allowed Users:</span>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($flag->rules['users'] as $u)
                                                    <span class="bg-slate-200 dark:bg-slate-800 rounded px-1 font-mono text-[9px]">{{ substr($u, 0, 12) }}...</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    @if(isset($flag->rules['organizations']))
                                        <div class="flex items-start gap-x-2">
                                            <span class="text-slate-400 whitespace-nowrap">Allowed Tenants:</span>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($flag->rules['organizations'] as $o)
                                                    <span class="bg-slate-200 dark:bg-slate-800 rounded px-1 font-mono text-[9px]">{{ substr($o, 0, 12) }}...</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Update Rules / Delete Controls -->
                        <div class="flex items-center gap-x-3">
                            <button @click="
                                flagKey = '{{ $flag->key }}';
                                flagDescription = '{{ $flag->description }}';
                                flagEnabled = {{ $flag->is_enabled ? 'true' : 'false' }};
                                flagBeta = {{ $flag->is_beta ? 'true' : 'false' }};
                                flagRollout = '{{ $flag->rules['rollout'] ?? '' }}';
                                flagUsers = '{{ isset($flag->rules['users']) ? implode(',', $flag->rules['users']) : '' }}';
                                flagOrgs = '{{ isset($flag->rules['organizations']) ? implode(',', $flag->rules['organizations']) : '' }}';
                                showNewFlagModal = true;
                            " class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200/50 dark:hover:bg-slate-700/50 px-3 py-1.5 rounded-lg transition">
                                Edit Targeting
                            </button>
                            
                            <form method="POST" action="{{ route('settings.flags.delete') }}" onsubmit="return confirm('Are you sure you want to delete this feature flag? This action is irreversible.');">
                                @csrf
                                <input type="hidden" name="key" value="{{ $flag->key }}">
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/40 p-1.5 rounded-lg transition">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400 italic">
                        No feature flags registered yet. Create one above to get started.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- TAB 2: PLATFORM DEFAULT SETTINGS PANEL -->
    <div x-show="activeTab === 'platform'" class="space-y-6">
        @include('settings.partials._settings_table', ['settings' => $platformSettings, 'group' => 'platform'])
    </div>

    <!-- TAB 3: ORGANIZATION OVERRIDES PANEL -->
    <div x-show="activeTab === 'org'" class="space-y-6">
        @if($currentOrg)
            @include('settings.partials._settings_table', ['settings' => $orgSettings, 'group' => 'organization'])
        @else
            <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 rounded-2xl p-6 text-center">
                <span class="text-amber-600 dark:text-amber-400 font-bold block">No Active Organization Context</span>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 max-w-lg mx-auto">
                    You are currently in a platform-level or guest workspace context. Switch to an organization using the organization switch tool to apply tenant overrides.
                </p>
            </div>
        @endif
    </div>

    <!-- TAB 4: USER SETTINGS PANEL -->
    <div x-show="activeTab === 'user'" class="space-y-6">
        @include('settings.partials._settings_table', ['settings' => $userSettings, 'group' => 'user'])
    </div>

    <!-- MODAL 1: NEW/EDIT SETTING -->
    <div x-show="showNewSettingModal" class="relative z-50" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        <div class="fixed inset-0 overflow-y-auto p-4 flex items-center justify-center">
            <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl" @click.away="showNewSettingModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-base font-bold text-slate-850 dark:text-white">Configure Setting Key</h3>
                    <button @click="showNewSettingModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('settings.update') }}" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Setting Target Scope</label>
                        <select name="group" x-model="settingGroup" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                            <option value="platform">Platform Default (Global)</option>
                            @if($currentOrg)
                                <option value="organization">Organization Override (Tenant: {{ $currentOrg->name }})</option>
                            @endif
                            <option value="user">User Override (Scoped to your Profile)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Unique Config Key</label>
                        <input type="text" name="key" x-model="settingKey" required placeholder="e.g. system.max_retries" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Value Type Cast</label>
                            <select name="type" x-model="settingType" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                                <option value="string">String (Text)</option>
                                <option value="boolean">Boolean (True/False)</option>
                                <option value="integer">Integer (Whole Number)</option>
                                <option value="float">Float (Decimal)</option>
                                <option value="json">JSON (Structured Schema)</option>
                            </select>
                        </div>
                        <div class="flex items-center h-full pt-6 pl-2">
                            <label class="flex items-center gap-x-2.5 cursor-pointer">
                                <input type="checkbox" name="is_encrypted" value="1" x-model="settingEncrypted" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-bold text-slate-600 dark:text-slate-300">Encrypt Value in DB</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Configured Value</label>
                        <textarea name="value" x-model="settingValue" rows="4" placeholder="Enter configuration value" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white font-mono text-[11px]"></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-x-3">
                        <button type="button" @click="showNewSettingModal = false" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</button>
                        <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-2 text-xs font-bold text-white shadow-md shadow-indigo-100 dark:shadow-none">Save Setting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: NEW/EDIT FEATURE FLAG -->
    <div x-show="showNewFlagModal" class="relative z-50" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        <div class="fixed inset-0 overflow-y-auto p-4 flex items-center justify-center">
            <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl" @click.away="showNewFlagModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-base font-bold text-slate-850 dark:text-white">Configure Feature Flag Gating</h3>
                    <button @click="showNewFlagModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('settings.flags.update') }}" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Feature Key Name</label>
                        <input type="text" name="key" x-model="flagKey" required placeholder="e.g. billing.lipa_na_mpesa" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white font-mono">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Description</label>
                        <input type="text" name="description" x-model="flagDescription" placeholder="Explain the feature gated by this flag" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center pl-2">
                            <label class="flex items-center gap-x-2.5 cursor-pointer">
                                <input type="checkbox" name="is_enabled" value="1" x-model="flagEnabled" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-bold text-slate-600 dark:text-slate-300">Globally Enabled</span>
                            </label>
                        </div>
                        <div class="flex items-center pl-2">
                            <label class="flex items-center gap-x-2.5 cursor-pointer">
                                <input type="checkbox" name="is_beta" value="1" x-model="flagBeta" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-bold text-slate-600 dark:text-slate-300">Beta Enrollment Required</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-4">
                        <span class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block">Targeting Rules</span>
                        
                        <div>
                            <label class="block font-bold text-slate-500 mb-1.5">Rollout Percentage (0-100)</label>
                            <input type="number" name="rollout" x-model="flagRollout" min="0" max="100" placeholder="e.g. 50" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 mb-1.5">Target Users (Comma Separated IDs)</label>
                            <input type="text" name="users_list[]" x-model="flagUsers" placeholder="UUIDs separated by comma" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 mb-1.5">Target Organizations/Tenants (Comma Separated IDs)</label>
                            <input type="text" name="orgs_list[]" x-model="flagOrgs" placeholder="UUIDs separated by comma" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-x-3">
                        <button type="button" @click="showNewFlagModal = false" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</button>
                        <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-2 text-xs font-bold text-white shadow-md shadow-indigo-100 dark:shadow-none">Save Flag</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: BETA ENROLLMENT MANAGEMENT -->
    <div x-show="showNewBetaModal" class="relative z-50" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        <div class="fixed inset-0 overflow-y-auto p-4 flex items-center justify-center">
            <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl" @click.away="showNewBetaModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
                    <h3 class="text-base font-bold text-slate-850 dark:text-white">Enrollment in Beta Features</h3>
                    <button @click="showNewBetaModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Enroll form -->
                <form method="POST" action="{{ route('settings.flags.beta.enroll') }}" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Select Beta Gated Feature</label>
                        <select name="feature_flag_key" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                            @foreach($featureFlags->where('is_beta', true) as $bf)
                                <option value="{{ $bf->key }}">{{ $bf->key }} ({{ $bf->description ?? 'No Description' }})</option>
                            @endforeach
                            @if($featureFlags->where('is_beta', true)->count() == 0)
                                <option disabled>No Beta Gated Feature Flags Registered</option>
                            @endif
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4" x-data="{ enrollType: 'organization' }">
                        <div>
                            <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Participant Type</label>
                            <select name="target_type" x-model="enrollType" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                                <option value="organization">Organization (Tenant)</option>
                                <option value="user">User (Individual)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Participant Record</label>
                            <select name="target_id" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                                <template x-if="enrollType === 'organization'">
                                    @foreach($organizations as $o)
                                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                                    @endforeach
                                </template>
                                <template x-if="enrollType === 'user'">
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end gap-x-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="submit" name="action" value="enroll" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 transition px-4 py-2 text-xs font-bold text-white shadow-md shadow-indigo-100 dark:shadow-none">
                            Enroll Participant
                        </button>
                    </div>
                </form>

                <!-- Unenroll form -->
                <form method="POST" action="{{ route('settings.flags.beta.unenroll') }}" class="space-y-4 text-xs pt-6 mt-6 border-t border-slate-100 dark:border-slate-800">
                    @csrf
                    <div class="bg-slate-50 dark:bg-slate-950/40 rounded-xl p-3 border border-slate-100 dark:border-slate-850">
                        <span class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block mb-2">Unenroll Participant</span>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4" x-data="{ unenrollType: 'organization' }">
                            <div>
                                <select name="feature_flag_key" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                                    @foreach($featureFlags->where('is_beta', true) as $bf)
                                        <option value="{{ $bf->key }}">{{ $bf->key }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select name="target_type" x-model="unenrollType" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                                    <option value="organization">Organization</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <div>
                                <select name="target_id" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-transparent py-2.5 px-3 dark:text-white">
                                    <template x-if="unenrollType === 'organization'">
                                        @foreach($organizations as $o)
                                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                                        @endforeach
                                    </template>
                                    <template x-if="unenrollType === 'user'">
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                        @endforeach
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end mt-3">
                            <button type="submit" class="rounded-xl bg-red-600 hover:bg-red-500 text-white transition px-4 py-2 text-xs font-bold shadow-md shadow-red-100 dark:shadow-none">
                                Revoke Beta Access
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

</div>
@endsection
