@extends('layouts.app')

@section('header', 'Enterprise File Storage')

@section('content')
<div class="space-y-8" x-data="{
    files: [],
    loading: true,
    activeCategory: 'all',
    searchQuery: '',
    dragover: false,
    
    // Upload state tracking
    isUploading: false,
    uploadProgress: 0,
    uploadSpeed: '',
    uploadFilename: '',
    
    // Signed url popup state
    signedUrlPopupOpen: false,
    generatedSignedUrl: '',
    signedUrlExpiry: 60,
    activeFileId: '',

    async init() {
        await this.loadFiles();
        this.loading = false;
    },

    async loadFiles() {
        try {
            let url = '/api/files';
            const params = [];
            if (this.activeCategory !== 'all') {
                params.push(`category=${this.activeCategory}`);
            }
            if (this.searchQuery) {
                params.push(`query=${encodeURIComponent(this.searchQuery)}`);
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
            }

            const res = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.files = json.data;
            }
        } catch (e) {
            console.error('Failed to load files from storage', e);
        }
    },

    async deleteFile(id) {
        if (!confirm('Are you sure you want to permanently delete this file and any of its associated thumbnails?')) {
            return;
        }

        try {
            const res = await fetch(`/api/files/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.files = this.files.filter(f => f.id !== id);
                this.triggerToast('File successfully deleted from storage.', 'success');
            }
        } catch (e) {
            console.error(e);
            this.triggerToast('Failed to delete file.', 'danger');
        }
    },

    async runVirusScan(id) {
        try {
            const res = await fetch(`/api/files/${id}/scan`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                }
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.files = this.files.map(f => f.id === id ? { ...f, virus_scan_status: json.data.status, virus_scan_result: json.data.result } : f);
                
                if (json.data.status === 'clean') {
                    this.triggerToast('File scanned. Status: CLEAN.', 'success');
                } else if (json.data.status === 'infected') {
                    this.triggerToast('ALERT: Infected file signature found! Quarantined.', 'danger');
                } else {
                    this.triggerToast('Scan completed. Status: ' + json.data.status, 'warning');
                }
            }
        } catch (e) {
            console.error(e);
            this.triggerToast('Scan engine call failed.', 'danger');
        }
    },

    async openSignedUrlModal(id) {
        this.activeFileId = id;
        this.generatedSignedUrl = '';
        this.signedUrlPopupOpen = true;
        await this.generateSignedUrl();
    },

    async generateSignedUrl() {
        try {
            const res = await fetch(`/api/files/${this.activeFileId}/signed-url`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                },
                body: JSON.stringify({ expires: this.signedUrlExpiry })
            });
            const json = await res.json();
            if (json.status === 'success') {
                this.generatedSignedUrl = json.signed_url;
            }
        } catch (e) {
            console.error(e);
        }
    },

    copySignedUrl() {
        navigator.clipboard.writeText(this.generatedSignedUrl);
        this.triggerToast('Signed temporary URL copied to clipboard.', 'success');
    },

    // Upload handling
    async handleFileSelect(e) {
        const selectedFiles = e.target.files || e.dataTransfer.files;
        if (!selectedFiles || selectedFiles.length === 0) return;

        const file = selectedFiles[0];
        
        // If file is > 10MB, let's use the Chunked Upload pipeline for demonstration and efficiency!
        if (file.size > 10 * 1024 * 1024) {
            await this.uploadChunked(file);
        } else {
            await this.uploadStandard(file);
        }
    },

    async uploadStandard(file) {
        this.isUploading = true;
        this.uploadProgress = 0;
        this.uploadFilename = file.name;
        this.uploadSpeed = 'Calculating...';

        const startTime = Date.now();
        const formData = new FormData();
        formData.append('file', file);
        formData.append('visibility', 'private');

        try {
            // We use XMLHttpRequest to get accurate progress events for standard upload
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/files');
                
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content'));

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        this.uploadProgress = percent;
                        
                        // Calculate Speed
                        const elapsed = (Date.now() - startTime) / 1000;
                        if (elapsed > 0) {
                            const speedBytes = event.loaded / elapsed;
                            this.uploadSpeed = this.formatSpeed(speedBytes);
                        }
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(JSON.parse(xhr.responseText));
                    } else {
                        reject(new Error(xhr.responseText || 'Upload failed'));
                    }
                };

                xhr.onerror = () => reject(new Error('Network error'));
                xhr.send(formData);
            });

            this.triggerToast('File uploaded and secured successfully.', 'success');
            await this.loadFiles();
        } catch (error) {
            console.error(error);
            this.triggerToast('Upload failed. Check format size limits.', 'danger');
        } finally {
            this.isUploading = false;
        }
    },

    async uploadChunked(file) {
        this.isUploading = true;
        this.uploadProgress = 0;
        this.uploadFilename = file.name;
        this.uploadSpeed = 'Initializing chunk pipeline...';

        const chunkSize = 2 * 1024 * 1024; // 2MB chunk sizes
        const totalChunks = Math.ceil(file.size / chunkSize);
        const uploadId = 'up_' + Math.random().toString(36).substring(2, 15);
        const startTime = Date.now();

        try {
            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('file', chunk);
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', i);
                formData.append('total_chunks', totalChunks);
                formData.append('filename', file.name);

                const res = await fetch('/api/files', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
                    },
                    body: formData
                });

                const json = await res.json();
                
                // Update Progress
                const totalUploaded = end;
                this.uploadProgress = Math.round((totalUploaded / file.size) * 100);
                
                const elapsed = (Date.now() - startTime) / 1000;
                if (elapsed > 0) {
                    const speedBytes = totalUploaded / elapsed;
                    this.uploadSpeed = this.formatSpeed(speedBytes) + ' (Chunk ' + (i+1) + '/' + totalChunks + ')';
                }

                if (json.status === 'success') {
                    this.triggerToast('Assembled final chunk. File secured!', 'success');
                    await this.loadFiles();
                    break;
                }
            }
        } catch (error) {
            console.error(error);
            this.triggerToast('Chunked upload pipeline disrupted.', 'danger');
        } finally {
            this.isUploading = false;
        }
    },

    formatSpeed(bytesPerSec) {
        if (bytesPerSec > 1024 * 1024) {
            return (bytesPerSec / (1024 * 1024)).toFixed(1) + ' MB/s';
        }
        return (bytesPerSec / 1024).toFixed(1) + ' KB/s';
    }
}">

    <!-- Page Header Hero Block -->
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 dark:text-white sm:truncate sm:text-3xl tracking-tight">Enterprise Storage</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Secure, tenant-isolated S3/MinIO cloud storage infrastructure with on-upload malware sanitation and image optimization.</p>
        </div>
    </div>

    <!-- Drag & Drop Uploader Component card -->
    <div class="border-2 border-dashed rounded-2xl p-8 text-center transition cursor-pointer relative"
        :class="dragover ? 'border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/20' : 'border-slate-300 hover:border-slate-400 dark:border-slate-800 dark:hover:border-slate-700'"
        @dragover.prevent="dragover = true"
        @dragleave.prevent="dragover = false"
        @drop.prevent="dragover = false; handleFileSelect($event)">
        
        <input type="file" @change="handleFileSelect($event)" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
        
        <div class="flex flex-col items-center justify-center space-y-2">
            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-2xl">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Drag & drop your files here, or <span class="text-indigo-600 dark:text-indigo-400">browse</span></p>
                <p class="text-[10px] text-slate-400 mt-1">Supports Images, Documents, Audio, Videos and Zip Archives. Files >10MB automatically use multi-chunk streaming pipelines.</p>
            </div>
        </div>
    </div>

    <!-- File Upload Progress Bar Overlay -->
    <div x-show="isUploading" class="p-4 rounded-xl border border-indigo-100 bg-indigo-50/30 dark:border-indigo-950/50 dark:bg-indigo-950/10 space-y-2" x-cloak>
        <div class="flex items-center justify-between text-xs font-semibold">
            <span class="text-indigo-600 dark:text-indigo-400 flex items-center gap-x-2">
                <div class="h-3 w-3 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"></div>
                Uploading "<span x-text="uploadFilename"></span>"
            </span>
            <span class="text-slate-500" x-text="uploadProgress + '%'"></span>
        </div>
        
        <!-- Progress Bar -->
        <div class="w-full bg-slate-200 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
            <div class="bg-indigo-600 h-full rounded-full transition-all duration-300" :style="`width: ${uploadProgress}%`"></div>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400">
            <span x-text="uploadSpeed"></span>
            <span>Secured Tenant Transfer Channel</span>
        </div>
    </div>

    <!-- Filters, Search and Listing -->
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row gap-4 justify-between items-center border-b border-slate-200 dark:border-slate-800 pb-4">
            
            <!-- Category Tabs -->
            <div class="flex flex-wrap gap-2">
                <template x-for="cat in ['all', 'image', 'document', 'video', 'audio', 'archive']">
                    <button type="button" 
                        @click="activeCategory = cat; loadFiles();"
                        class="px-3 py-1.5 rounded-xl text-xs font-medium uppercase tracking-wider transition"
                        :class="activeCategory === cat ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800'"
                        x-text="cat === 'all' ? 'All Files' : cat">
                    </button>
                </template>
            </div>

            <!-- Search field -->
            <div class="w-full sm:w-64 relative">
                <input type="text" x-model="searchQuery" @keyup.enter="loadFiles()" placeholder="Search secured files..." 
                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <svg class="h-3.5 w-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.537z" />
                </svg>
            </div>
        </div>

        <!-- Files List Grid -->
        <div x-show="loading" class="flex flex-col items-center justify-center py-20">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent"></div>
            <p class="text-xs text-slate-500 mt-2">Loading secured vault file list...</p>
        </div>

        <div x-show="!loading" class="space-y-4">
            
            <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 text-left">Filename & Format</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Category</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Size</th>
                            <th scope="col" class="px-6 py-3.5 class text-left">Security Scan</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Uploaded</th>
                            <th scope="col" class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
                        <template x-for="file in files" :key="file.id">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30 transition">
                                
                                <!-- File Name and Thumbnail Preview -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-x-3">
                                        <!-- Image Thumbnail -->
                                        <template x-if="file.is_image && file.thumbnail_url">
                                            <img :src="file.thumbnail_url" class="h-8 w-8 rounded-lg object-cover bg-slate-100 border border-slate-200 dark:border-slate-800" referrerPolicy="no-referrer">
                                        </template>
                                        
                                        <!-- Other Formats Placeholder Icons -->
                                        <template x-if="!file.is_image || !file.thumbnail_url">
                                            <div class="p-2 rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400">
                                                <template x-if="file.category === 'document'">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                    </svg>
                                                </template>
                                                <template x-if="file.category === 'video'">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l-4.5-3v6l4.5-3z" />
                                                    </svg>
                                                </template>
                                                <template x-if="file.category !== 'document' && file.category !== 'video'">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                    </svg>
                                                </template>
                                            </div>
                                        </template>

                                        <div>
                                            <span class="font-bold text-slate-900 dark:text-white" x-text="file.name"></span>
                                            <div class="flex items-center gap-x-2 mt-0.5">
                                                <span class="text-[9px] uppercase tracking-wider text-slate-400" x-text="file.mime_type"></span>
                                                <span class="h-1 w-1 bg-slate-300 dark:bg-slate-700 rounded-full"></span>
                                                <span class="rounded px-1 text-[8px] font-semibold"
                                                    :class="file.visibility === 'public' ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'"
                                                    x-text="file.visibility"></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px]" x-text="file.category"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-600 dark:text-slate-400 font-medium" x-text="file.formatted_size"></td>
                                
                                <!-- Virus scanner outcomes -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-x-1.5">
                                        <span class="h-2 w-2 rounded-full"
                                            :class="{
                                                'bg-green-500 animate-pulse': file.virus_scan_status === 'clean',
                                                'bg-amber-500 animate-bounce': file.virus_scan_status === 'pending',
                                                'bg-red-500 animate-ping': file.virus_scan_status === 'infected',
                                                'bg-slate-400': file.virus_scan_status === 'skipped'
                                            }"></span>
                                        <span class="font-bold capitalize"
                                            :class="{
                                                'text-green-600 dark:text-green-400': file.virus_scan_status === 'clean',
                                                'text-amber-600 dark:text-amber-400': file.virus_scan_status === 'pending',
                                                'text-red-600 dark:text-red-400': file.virus_scan_status === 'infected',
                                                'text-slate-500': file.virus_scan_status === 'skipped'
                                            }"
                                            x-text="file.virus_scan_status"></span>
                                        
                                        <!-- Manual trigger scan button -->
                                        <button type="button" @click="runVirusScan(file.id)" class="text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline flex items-center ml-2">
                                            Re-scan
                                        </button>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-slate-400" x-text="new Date(file.created_at).toLocaleDateString() + ' ' + new Date(file.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })"></td>
                                
                                <!-- Actions column -->
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-x-3">
                                        <a :href="`/api/files/${file.id}/download`" target="_blank" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-bold hover:underline">Download</a>
                                        <button type="button" @click="openSignedUrlModal(file.id)" class="text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white font-bold hover:underline">Sign Link</button>
                                        <button type="button" @click="deleteFile(file.id)" class="text-red-600 hover:text-red-800 dark:text-red-400 font-bold hover:underline">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <template x-if="files.length === 0">
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">
                                    No secured files found inside this vault directory. Drag & drop a file above to begin!
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- Signed temporary URL modal overlay popup -->
    <div x-show="signedUrlPopupOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" x-cloak>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-sm font-bold text-slate-950 dark:text-white">Secure Signed Link Generator</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Generate a temporary, SHA-256 HMAC-signed link to share this private document securely.</p>
                </div>
                <button type="button" @click="signedUrlPopupOpen = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Expiry input -->
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase">Link Expiration (Minutes)</label>
                <div class="flex gap-x-3">
                    <input type="number" x-model="signedUrlExpiry" @change="generateSignedUrl()" class="w-24 px-3 py-1.5 text-xs border border-slate-200 bg-white rounded-lg focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white">
                    <button type="button" @click="generateSignedUrl()" class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-500">Regenerate</button>
                </div>
            </div>

            <!-- Url Output block -->
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase">Signed URL Payload</label>
                <div class="flex items-center gap-x-2">
                    <input type="text" x-model="generatedSignedUrl" readonly class="w-full px-3 py-1.5 text-xs border border-slate-200 bg-slate-50 rounded-lg focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300 text-ellipsis overflow-hidden">
                    <button type="button" @click="copySignedUrl()" class="px-3 py-1.5 text-xs bg-slate-900 text-white rounded-lg font-semibold dark:bg-white dark:text-slate-950 hover:bg-slate-800">Copy</button>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="button" @click="signedUrlPopupOpen = false" class="px-4 py-1.5 text-xs border border-slate-200 rounded-lg text-slate-600 font-semibold hover:bg-slate-50 dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800">Close</button>
            </div>
        </div>
    </div>

</div>
@endsection
