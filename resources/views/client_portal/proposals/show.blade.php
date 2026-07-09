@extends('layouts.app')

@section('header', 'Proposal Review & Sign')

@section('content')
<div class="max-w-5xl mx-auto space-y-8" x-data="{
    sigType: 'typed',
    typedName: '{{ Auth::user()->name ?? 'Corporate Partner' }}',
    isDrawing: false,
    sigCanvas: null,
    ctx: null,
    canvasDataUrl: '',
    
    init() {
        this.sigCanvas = document.getElementById('signature-canvas');
        if (this.sigCanvas) {
            this.ctx = this.sigCanvas.getContext('2d');
            this.ctx.strokeStyle = '#312e81'; // Deep indigo line
            this.ctx.lineWidth = 2.5;
            this.ctx.lineCap = 'round';
        }
    },
    startDrawing(e) {
        this.isDrawing = true;
        const rect = this.sigCanvas.getBoundingClientRect();
        this.ctx.beginPath();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        this.ctx.moveTo(clientX - rect.left, clientY - rect.top);
    },
    draw(e) {
        if (!this.isDrawing) return;
        const rect = this.sigCanvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        this.ctx.lineTo(clientX - rect.left, clientY - rect.top);
        this.ctx.stroke();
    },
    stopDrawing() {
        if (this.isDrawing) {
            this.isDrawing = false;
            this.canvasDataUrl = this.sigCanvas.toDataURL();
            document.getElementById('sig-representation-input').value = this.canvasDataUrl;
        }
    },
    clearCanvas() {
        this.ctx.clearRect(0, 0, this.sigCanvas.width, this.sigCanvas.height);
        this.canvasDataUrl = '';
        document.getElementById('sig-representation-input').value = '';
    },
    setSigType(type) {
        this.sigType = type;
        if (type === 'typed') {
            document.getElementById('sig-representation-input').value = this.typedName;
        } else if (type === 'mouse' || type === 'touch') {
            document.getElementById('sig-representation-input').value = this.canvasDataUrl;
        }
    }
}">
    <!-- Top Progress Bar / Status Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium uppercase tracking-wider
                    @if($proposal->status === 'draft') bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200
                    @elseif($proposal->status === 'sent' || $proposal->status === 'viewed') bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400
                    @elseif($proposal->status === 'signed' || $proposal->status === 'converted') bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400
                    @else bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 @endif">
                    Status: {{ strtoupper($proposal->status) }}
                </span>
                <span class="text-xs text-slate-400">|</span>
                <span class="text-xs text-slate-500">Expires: {{ $proposal->expires_at->format('M d, Y') }}</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $proposal->title }}</h1>
        </div>

        <!-- Admin Transition Controls -->
        @if(Auth::user() && in_array(Auth::user()->role, ['admin', 'employee', 'staff']))
            <div class="flex flex-wrap gap-2 items-center">
                <form action="{{ route('crm.proposals.transition', $proposal->id) }}" method="POST" class="flex gap-1">
                    @csrf
                    <select name="status" class="rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs text-slate-900 dark:text-white p-2">
                        <option value="draft" @selected($proposal->status === 'draft')>Draft</option>
                        <option value="sent" @selected($proposal->status === 'sent')>Sent</option>
                        <option value="viewed" @selected($proposal->status === 'viewed')>Viewed</option>
                        <option value="negotiating" @selected($proposal->status === 'negotiating')>Negotiating</option>
                        <option value="approved" @selected($proposal->status === 'approved')>Approved</option>
                        <option value="rejected" @selected($proposal->status === 'rejected')>Rejected</option>
                    </select>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-3 py-2 rounded-lg shadow-sm">
                        Apply State
                    </button>
                </form>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400 p-4 rounded-lg border border-emerald-200 dark:border-emerald-800 text-xs">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Document Body -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Sections Content -->
            <div class="bg-white dark:bg-slate-900 p-8 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-8 font-sans">
                @foreach($proposal->sections->sortBy('sort_order') as $section)
                    <div class="space-y-3">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                            {{ $section->title }}
                        </h2>
                        <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed space-y-2 whitespace-pre-line">
                            {{ $section->content }}
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Quotation Valuation Summary -->
            <div class="bg-white dark:bg-slate-900 p-8 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <h2 class="text-sm font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">Proposal Itemized Quotation Valuation</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-800 text-xs text-left">
                        <thead>
                            <tr class="text-slate-500 font-bold uppercase">
                                <th class="py-3 px-2">Work Scope / Service Block</th>
                                <th class="py-3 px-2 text-right">Units / Quantity</th>
                                <th class="py-3 px-2 text-right">Unit Pricing</th>
                                <th class="py-3 px-2 text-right">Segment Sum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($proposal->items as $item)
                                <tr class="text-slate-700 dark:text-slate-300">
                                    <td class="py-3 px-2 font-medium">{{ $item->description }}</td>
                                    <td class="py-3 px-2 text-right">{{ number_format($item->quantity, 1) }}</td>
                                    <td class="py-3 px-2 text-right">${{ number_format($item->unit_price, 2) }}</td>
                                    <td class="py-3 px-2 text-right font-semibold">${{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-800 pt-4 flex justify-end">
                    <div class="text-right">
                        <p class="text-[10px] uppercase font-bold text-slate-400">Total Project Scope Evaluation</p>
                        <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">${{ number_format($proposal->total_amount, 2) }}</p>
                    </div>
                </div>
            </div>

            <!-- Collaborative Discussion / Feedback -->
            <div class="bg-white dark:bg-slate-900 p-8 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Collaborative Discussions & Review Requests</h2>

                <!-- Comments List -->
                <div class="space-y-4">
                    @forelse($proposal->comments->where('parent_id', null) as $comment)
                        <div class="p-4 border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/25 rounded-lg space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $comment->user->name ?? 'Client Partner' }}</span>
                                <span class="text-[10px] text-slate-400">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ $comment->content }}</p>

                            <!-- Thread Replies -->
                            @foreach($comment->replies as $reply)
                                <div class="ml-6 mt-3 p-3 border-l-2 border-indigo-500 bg-white dark:bg-slate-950/40 rounded space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-bold text-slate-900 dark:text-white">{{ $reply->user->name ?? 'Staff Partner' }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $reply->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-400">{{ $reply->content }}</p>
                                </div>
                            @endforeach

                            <!-- Quick Reply Form -->
                            <form action="{{ route('client.proposal.comment', $proposal->id) }}" method="POST" class="mt-3 flex gap-2">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                <input type="text" name="content" required placeholder="Write a reply..." class="block w-full rounded border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 text-xs p-2">
                                <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white text-xs px-3 rounded">
                                    Reply
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No review logs compiled yet. Add comments below to request scope revisions.</p>
                    @endforelse
                </div>

                <!-- Primary Comment Form -->
                <form action="{{ route('client.proposal.comment', $proposal->id) }}" method="POST" class="space-y-3 pt-2">
                    @csrf
                    <div>
                        <label for="comment-body" class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-2">New Comment or Scope Feedback</label>
                        <textarea name="content" id="comment-body" rows="3" required placeholder="Request specific budget revisions, service additions, or SLA updates..." class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-xs p-3 text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700">
                        Post Comment
                    </button>
                </form>
            </div>
        </div>

        <!-- Sidebar Actions: Signature box, Revision History, and Activity log -->
        <div class="space-y-8">
            <!-- Dynamic E-Signature Workspace -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">4. Electronic Legal Signature</h2>

                @if($proposal->status === 'signed' || $proposal->status === 'converted')
                    <div class="text-center p-6 border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50/50 dark:bg-emerald-950/20 rounded-lg space-y-3">
                        <span class="inline-flex items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900 p-2 text-emerald-600 dark:text-emerald-300">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-slate-900 dark:text-white">Proposal Fully Signed</p>
                            <p class="text-[10px] text-slate-500">Legal agreement bound and project delivery initiated.</p>
                        </div>
                    </div>
                @else
                    <form action="{{ route('client.proposal.sign', $proposal->id) }}" method="POST" class="space-y-4">
                        @csrf
                        
                        <!-- Toggle Signature Format -->
                        <div class="flex border border-slate-200 dark:border-slate-800 rounded-lg p-1 bg-slate-50 dark:bg-slate-950">
                            <button type="button" @click="setSigType('typed')" :class="sigType === 'typed' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500'" class="flex-1 text-[10px] font-bold py-1.5 rounded-md uppercase transition-all">
                                Type Name
                            </button>
                            <button type="button" @click="setSigType('mouse')" :class="sigType === 'mouse' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500'" class="flex-1 text-[10px] font-bold py-1.5 rounded-md uppercase transition-all">
                                Draw Signature
                            </button>
                        </div>

                        <!-- Signature Input Content -->
                        <div>
                            <!-- Typed Signature -->
                            <div x-show="sigType === 'typed'" class="space-y-2">
                                <label for="signer-typed-name" class="block text-[10px] font-bold uppercase text-slate-400">Payer Full Legal Name</label>
                                <input type="text" id="signer-typed-name" x-model="typedName" @input="document.getElementById('sig-representation-input').value = typedName" class="block w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2.5 text-xs text-slate-900 dark:text-white font-mono" placeholder="Alice Wanjiru">
                            </div>

                            <!-- Drawing Canvas -->
                            <div x-show="sigType === 'mouse'" class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-[10px] font-bold uppercase text-slate-400">Interactive Signature Canvas</label>
                                    <button type="button" @click="clearCanvas()" class="text-indigo-600 hover:text-indigo-500 text-[10px] font-semibold">
                                        Clear Signature
                                    </button>
                                </div>
                                <div class="border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 rounded-lg overflow-hidden h-36 relative">
                                    <canvas id="signature-canvas" width="350" height="144" class="w-full h-full cursor-crosshair"
                                        @mousedown="startDrawing($event)"
                                        @mousemove="draw($event)"
                                        @mouseup="stopDrawing()"
                                        @mouseleave="stopDrawing()"
                                        @touchstart="startDrawing($event)"
                                        @touchmove="draw($event)"
                                        @touchend="stopDrawing()">
                                    </canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Cryptographic Audit Trail Declaration -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-lg text-[9px] text-slate-400 space-y-1">
                            <p class="font-semibold uppercase text-slate-500">Legal Disclosure & Audit Log</p>
                            <p>By signing, you agree that your IP address ({{ request()->ip() }}), browser signatures, and timestamp will be stored cryptographically. The resulting signature is legally binding as equivalent to a physical ink signature.</p>
                        </div>

                        <!-- Hidden Inputs -->
                        <input type="hidden" name="signature_type" :value="sigType">
                        <input type="hidden" name="signature_representation" id="sig-representation-input" :value="typedName">

                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                            Certify & Sign Proposal
                        </button>
                    </form>
                @endif
            </div>

            <!-- Versioning / Revision Control Panel -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">5. Revision & Version Control</h2>
                <div class="space-y-3">
                    @forelse($proposal->revisions->sortByDesc('version') as $revision)
                        <div class="p-3 border border-slate-100 dark:border-slate-800/60 rounded-lg text-xs space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">v{{ $revision->version }}</span>
                                <span class="text-[9px] text-slate-400">{{ $revision->created_at->format('M d, H:i') }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-relaxed">{{ $revision->content['notes'] ?? 'No revision notes provided.' }}</p>
                            <div class="flex justify-between items-center pt-1 text-[10px]">
                                <span class="text-slate-400">By: {{ $revision->creator->name ?? 'System' }}</span>
                                @if($proposal->status !== 'signed' && $proposal->status !== 'converted')
                                    <form action="{{ route('crm.proposals.restore', [$proposal->id, $revision->id]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-500 font-semibold">
                                            Restore Version
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No revisions recorded yet.</p>
                    @endforelse
                </div>
            </div>

            <!-- Audit Activity Timeline -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <h2 class="text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">6. Proposal Activity Timeline</h2>
                <div class="flow-root">
                    <ul class="-mb-8 text-xs text-slate-600 dark:text-slate-400 space-y-4">
                        @foreach($proposal->activities->sortByDesc('created_at') as $activity)
                            <li class="relative pb-4">
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-2 w-2 rounded-full bg-indigo-500 inline-block"></span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-0">
                                        <p class="text-[11px] text-slate-900 dark:text-white">
                                            <span class="font-semibold">{{ $activity->user->name ?? 'System' }}</span>
                                            {{ str_replace('_', ' ', $activity->activity) }}
                                        </p>
                                        <span class="text-[9px] text-slate-400 block">{{ $activity->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
