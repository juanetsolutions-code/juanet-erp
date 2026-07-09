<?php

namespace App\Http\Controllers;

use App\Domain\Proposal\Models\Proposal;
use App\Domain\Proposal\Models\ProposalRevision;
use App\Domain\Proposal\Services\ProposalService;
use App\Domain\CRM\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProposalController extends Controller
{
    public function __construct(private ProposalService $proposalService) {}

    /**
     * Show the Proposal builder form.
     */
    public function create(Request $request)
    {
        $leads = Lead::all();
        $clients = User::where('role', 'client')->get();
        if ($clients->isEmpty()) {
            $clients = User::all(); // Fallback if no specific role defined yet
        }
        return view('crm.proposals.create', compact('leads', 'clients'));
    }

    /**
     * Store a new proposal in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'client_id' => 'required|exists:users,id',
            'lead_id' => 'nullable|exists:leads,id',
            'expires_at' => 'nullable|date',
            'sections' => 'required|array|min:1',
            'sections.*.title' => 'required|string|max:255',
            'sections.*.content' => 'required|string',
            'sections.*.sort_order' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Calculate total amount from line items
        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }
        $validated['total_amount'] = $totalAmount;
        $validated['organization_id'] = session('organization_id') ?? '00000000-0000-0000-0000-000000000000';

        $proposal = $this->proposalService->createProposal($validated);

        return redirect()->route('client.proposal.show', $proposal->id)
            ->with('success', 'Proposal created successfully and saved as draft.');
    }

    /**
     * Show the Client Portal proposal view.
     */
    public function show(Proposal $proposal)
    {
        $proposal->load(['sections', 'items', 'comments.user', 'revisions.creator', 'activities.user']);
        return view('client_portal.proposals.show', compact('proposal'));
    }

    /**
     * Update an existing proposal.
     */
    public function update(Request $request, Proposal $proposal)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
            'sections' => 'nullable|array',
            'sections.*.title' => 'required|string|max:255',
            'sections.*.content' => 'required|string',
            'sections.*.sort_order' => 'nullable|integer',
            'items' => 'nullable|array',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'revision_notes' => 'nullable|string|max:255',
        ]);

        if (isset($validated['items'])) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }
            $validated['total_amount'] = $totalAmount;
        }

        $this->proposalService->updateProposal($proposal, $validated);

        return redirect()->back()->with('success', 'Proposal updated and new revision stored.');
    }

    /**
     * Transition proposal status.
     */
    public function transition(Request $request, Proposal $proposal)
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        try {
            $this->proposalService->transitionStatus($proposal, $validated['status']);
            return redirect()->back()->with('success', "Proposal status updated to '{$validated['status']}'.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Restore a proposal version.
     */
    public function restore(Request $request, Proposal $proposal, $revisionId)
    {
        $this->proposalService->restoreRevision($proposal, (int) $revisionId);
        return redirect()->back()->with('success', 'Proposal restored to previous version successfully.');
    }

    /**
     * Client adds a comment or PM adds internally.
     */
    public function addComment(Request $request, Proposal $proposal)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|integer',
        ]);

        $this->proposalService->addComment(
            $proposal,
            Auth::id() ?? $proposal->client_id,
            $validated['content'],
            $validated['parent_id'] ?? null
        );

        return redirect()->back()->with('success', 'Comment added successfully.');
    }

    /**
     * Client signs proposal electronically.
     */
    public function sign(Request $request, Proposal $proposal)
    {
        $validated = $request->validate([
            'signature_type' => 'required|in:typed,mouse,touch',
            'signature_representation' => 'required|string',
        ]);

        try {
            $this->proposalService->signProposal($proposal, [
                'signer_id' => Auth::id() ?? $proposal->client_id,
                'ip_address' => $request->ip(),
                'type' => $validated['signature_type'],
                'representation' => $validated['signature_representation'],
            ]);

            return redirect()->route('client.proposal.onboarding', $proposal->id)
                ->with('success', 'Proposal signed electronically! Welcome to your onboarding workspace.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the beautiful client onboarding experience.
     */
    public function onboarding(Proposal $proposal)
    {
        $proposal->load(['sections', 'items', 'activities.user']);

        // Find the matching project created via the auto-onboarding workflow
        $project = \App\Domain\Project\Models\Project::where('client_id', $proposal->client_id)
            ->orWhere('user_id', $proposal->client_id)
            ->with(['milestones.tasks', 'activities', 'files', 'comments'])
            ->latest()
            ->first();

        return view('client_portal.proposals.onboarding', compact('proposal', 'project'));
    }
}
