<div class="bg-white dark:bg-slate-900 border border-slate-150 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
        <h3 class="text-sm font-bold text-slate-800 dark:text-white capitalize">{{ $group }} Settings Panel</h3>
        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded-md">
            Context: {{ $group }}
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs divide-y divide-slate-100 dark:divide-slate-800">
            <thead>
                <tr class="bg-slate-50/30 dark:bg-slate-950/20 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <th class="px-6 py-3.5">Config Key</th>
                    <th class="px-6 py-3.5">Type Cast</th>
                    <th class="px-6 py-3.5">Resolved Value</th>
                    <th class="px-6 py-3.5">Security status</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($settings as $setting)
                    <tr class="hover:bg-slate-50/20 dark:hover:bg-slate-800/10 transition">
                        <!-- Key -->
                        <td class="px-6 py-4 font-mono text-slate-800 dark:text-slate-300 font-semibold select-all">
                            {{ $setting->key }}
                        </td>
                        <!-- Type -->
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-1 text-[10px] font-bold text-slate-600 dark:text-slate-400">
                                {{ $setting->type }}
                            </span>
                        </td>
                        <!-- Cast Value -->
                        <td class="px-6 py-4 max-w-xs truncate font-mono text-slate-500 dark:text-slate-400">
                            @if($setting->type === 'json')
                                <pre class="text-[10px] bg-slate-50 dark:bg-slate-950 p-2 rounded-lg max-h-24 overflow-y-auto">{{ json_encode($setting->getCastValue(), JSON_PRETTY_PRINT) }}</pre>
                            @elseif($setting->type === 'boolean')
                                <span class="font-bold {{ $setting->getCastValue() ? 'text-emerald-600' : 'text-red-500' }}">
                                    {{ $setting->getCastValue() ? 'True' : 'False' }}
                                </span>
                            @else
                                {{ (string)$setting->getCastValue() }}
                            @endif
                        </td>
                        <!-- Security -->
                        <td class="px-6 py-4">
                            @if($setting->is_encrypted)
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-green-50 dark:bg-green-950/30 px-2 py-1 text-[10px] font-bold text-green-700 dark:text-green-400">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                    Encrypted
                                </span>
                            @else
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-slate-100 dark:bg-slate-800/80 px-2 py-1 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                                    Plaintext
                                </span>
                            @endif
                        </td>
                        <!-- Actions -->
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-x-2">
                                <button @click="
                                    settingKey = '{{ $setting->key }}';
                                    settingValue = '{{ $setting->type === 'json' ? json_encode($setting->getCastValue()) : (string)$setting->getCastValue() }}';
                                    settingType = '{{ $setting->type }}';
                                    settingGroup = '{{ $group }}';
                                    settingEncrypted = {{ $setting->is_encrypted ? 'true' : 'false' }};
                                    showNewSettingModal = true;
                                " class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </button>

                                <form method="POST" action="{{ route('settings.delete') }}" onsubmit="return confirm('Are you sure you want to delete this setting record?');">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $setting->key }}">
                                    <input type="hidden" name="group" value="{{ $group }}">
                                    <button type="submit" class="text-slate-400 hover:text-red-500 dark:hover:text-red-400 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">
                            No settings defined for this group context. Use the 'New Setting' builder to set one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
