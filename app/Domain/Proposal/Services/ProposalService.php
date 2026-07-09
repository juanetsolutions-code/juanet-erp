<?php

namespace App\Domain\Proposal\Services;

use App\Domain\Proposal\Models\Proposal;
use App\Domain\Proposal\Models\ProposalItem;
use App\Domain\Proposal\Models\ProposalSection;
use App\Domain\Proposal\Models\ProposalRevision;
use App\Domain\Proposal\Models\ProposalActivity;
use App\Domain\Proposal\Models\ProposalComment;
use App\Domain\Contract\Models\Contract;
use App\Domain\Contract\Models\Signature;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectMilestone;
use App\Domain\Project\Models\ProjectTask;
use App\Domain\Project\Models\ProjectActivity;
use App\Domain\Project\Models\ProjectFile;
use App\Domain\Project\Models\ProjectComment;
use App\Domain\CRM\Models\Lead;
use App\Contracts\EventBus;
use App\Domain\Proposal\Events\ProposalCreated;
use App\Domain\Proposal\Events\ProposalUpdated;
use App\Domain\Proposal\Events\ProposalStatusChanged;
use App\Domain\Proposal\Events\ProposalSigned;
use App\Domain\Proposal\Events\ProposalConverted;
use App\Domain\Proposal\Events\ProposalAccepted;
use App\Domain\Proposal\Events\ProjectCreated as ProposalProjectCreated;
use App\Domain\Proposal\Events\ClientInvited;
use App\Domain\Proposal\Events\ProjectInitialized;
use App\Domain\Proposal\Events\MilestoneCreated;
use App\Domain\Proposal\Events\ChecklistCreated;
use App\Domain\Proposal\Events\TimelineInitialized;
use App\Domain\Proposal\Events\PortalAccessGranted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class ProposalService
{
    public function __construct(private EventBus $eventBus) {}

    /**
     * Create a new proposal with sections and line items.
     */
    public function createProposal(array $data): Proposal
    {
        return DB::transaction(function () use ($data) {
            $proposal = Proposal::create([
                'lead_id' => $data['lead_id'] ?? null,
                'client_id' => $data['client_id'],
                'organization_id' => $data['organization_id'] ?? '00000000-0000-0000-0000-000000000000',
                'title' => $data['title'],
                'status' => 'draft',
                'total_amount' => $data['total_amount'] ?? 0.00,
                'expires_at' => $data['expires_at'] ?? now()->addDays(30),
            ]);

            // Add Sections
            if (!empty($data['sections'])) {
                foreach ($data['sections'] as $index => $section) {
                    $proposal->sections()->create([
                        'title' => $section['title'],
                        'content' => $section['content'],
                        'sort_order' => $section['sort_order'] ?? $index,
                    ]);
                }
            }

            // Add Items (Quotation)
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $proposal->items()->create([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'],
                    ]);
                }
            }

            // Create initial revision
            $this->createRevision($proposal, 'Initial draft created.');

            // Log activity
            $this->logActivity($proposal, 'created', ['title' => $proposal->title]);

            // Dispatch Event
            $this->eventBus->dispatch(new ProposalCreated($proposal->toArray(), $proposal->organization_id));

            return $proposal;
        });
    }

    /**
     * Update an existing proposal and create a revision.
     */
    public function updateProposal(Proposal $proposal, array $data): Proposal
    {
        if (in_array($proposal->status, ['accepted', 'signed', 'converted'])) {
            throw new \InvalidArgumentException("Cannot edit a proposal that has been accepted, signed, or converted.");
        }

        return DB::transaction(function () use ($proposal, $data) {
            $proposal->update(array_filter([
                'title' => $data['title'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]));

            // Update sections if provided
            if (isset($data['sections'])) {
                $proposal->sections()->delete();
                foreach ($data['sections'] as $index => $section) {
                    $proposal->sections()->create([
                        'title' => $section['title'],
                        'content' => $section['content'],
                        'sort_order' => $section['sort_order'] ?? $index,
                    ]);
                }
            }

            // Update items if provided
            if (isset($data['items'])) {
                $proposal->items()->delete();
                foreach ($data['items'] as $item) {
                    $proposal->items()->create([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'],
                    ]);
                }
            }

            // Create a revision
            $this->createRevision($proposal, $data['revision_notes'] ?? 'Proposal details updated.');

            // Log activity
            $this->logActivity($proposal, 'updated', ['notes' => $data['revision_notes'] ?? 'Updated fields']);

            // Dispatch Event
            $this->eventBus->dispatch(new ProposalUpdated($proposal->toArray(), $proposal->organization_id));

            return $proposal;
        });
    }

    /**
     * Restore a proposal from a previous revision.
     */
    public function restoreRevision(Proposal $proposal, int $revisionId): Proposal
    {
        if (in_array($proposal->status, ['accepted', 'signed', 'converted'])) {
            throw new \InvalidArgumentException("Cannot restore a revision on a proposal that has been accepted, signed, or converted.");
        }

        return DB::transaction(function () use ($proposal, $revisionId) {
            $revision = $proposal->revisions()->findOrFail($revisionId);
            $content = $revision->content;

            $proposal->update([
                'title' => $content['title'],
                'total_amount' => $content['total_amount'],
            ]);

            // Restore sections
            $proposal->sections()->delete();
            foreach ($content['sections'] as $section) {
                $proposal->sections()->create([
                    'title' => $section['title'],
                    'content' => $section['content'],
                    'sort_order' => $section['sort_order'],
                ]);
            }

            // Restore items
            $proposal->items()->delete();
            foreach ($content['items'] as $item) {
                $proposal->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }

            $this->createRevision($proposal, "Restored to version #{$revision->version}");
            $this->logActivity($proposal, 'restored_revision', ['version' => $revision->version]);

            $this->eventBus->dispatch(new ProposalUpdated($proposal->toArray(), $proposal->organization_id));

            return $proposal;
        });
    }

    /**
     * Transition proposal status based on state machine rules.
     */
    public function transitionStatus(Proposal $proposal, string $newStatus): void
    {
        $validTransitions = [
            'draft' => ['internal_review', 'sent'],
            'internal_review' => ['draft', 'sent'],
            'sent' => ['viewed', 'negotiating', 'approved', 'rejected', 'expired', 'accepted'],
            'viewed' => ['negotiating', 'approved', 'rejected', 'expired', 'accepted'],
            'negotiating' => ['sent', 'approved', 'rejected', 'expired', 'accepted'],
            'approved' => ['signed', 'rejected', 'accepted'],
            'rejected' => ['draft', 'negotiating'],
            'expired' => ['draft'],
            'accepted' => ['signed', 'converted'],
            'signed' => ['converted', 'accepted'],
            'converted' => []
        ];

        $currentStatus = $proposal->status;

        if ($currentStatus !== $newStatus && !in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \InvalidArgumentException("Invalid state transition from {$currentStatus} to {$newStatus}");
        }

        $proposal->status = $newStatus;
        $proposal->save();

        $this->logActivity($proposal, 'status_changed', ['from' => $currentStatus, 'to' => $newStatus]);

        $this->eventBus->dispatch(new ProposalStatusChanged([
            'proposal_id' => $proposal->id,
            'from' => $currentStatus,
            'to' => $newStatus,
        ], $proposal->organization_id));
    }

    /**
     * Electronically sign a proposal.
     */
    public function signProposal(Proposal $proposal, array $signatureData): void
    {
        DB::transaction(function () use ($proposal, $signatureData) {
            // Check status transition
            if ($proposal->status === 'draft' || $proposal->status === 'expired' || $proposal->status === 'signed' || $proposal->status === 'converted') {
                throw new \InvalidArgumentException("Cannot sign proposal in '{$proposal->status}' state.");
            }

            // 1. Create a Contract representing the legal state
            $contract = Contract::create([
                'organization_id' => $proposal->organization_id,
                'client_id' => $proposal->client_id,
                'title' => "Contract for " . $proposal->title,
                'document_url' => "/contracts/signed_" . Str::slug($proposal->title) . "_" . time() . ".pdf",
                'status' => 'signed'
            ]);

            // 2. Log client's cryptographic Signature
            $signerId = $signatureData['signer_id'] ?? $proposal->client_id;
            $ipAddress = $signatureData['ip_address'] ?? '127.0.0.1';
            $signatureHash = hash('sha256', "{$contract->id}-{$signerId}-{$ipAddress}-" . now()->timestamp);

            Signature::create([
                'contract_id' => $contract->id,
                'signer_id' => $signerId,
                'ip_address' => $ipAddress,
                'signature_hash' => $signatureHash,
                'signed_at' => now(),
                'signature_type' => $signatureData['type'] ?? 'typed',
                'signature_data' => $signatureData['representation'] ?? 'Digital Signature'
            ]);

            // 3. Update proposal status
            $proposal->status = 'signed';
            $proposal->save();

            // Log activity
            $this->logActivity($proposal, 'signed', [
                'contract_id' => $contract->id,
                'signer_id' => $signerId,
                'signature_hash' => $signatureHash,
            ]);

            // Dispatch Signed Event
            $this->eventBus->dispatch(new ProposalSigned([
                'proposal_id' => $proposal->id,
                'contract_id' => $contract->id,
                'signature_hash' => $signatureHash,
            ], $proposal->organization_id));

            // 4. Automatically convert to Project!
            $this->convertToProject($proposal);
        });
    }

    /**
     * Auto-convert proposal to project, creating milestones, tasks, timeline, CRM linkage.
     */
    public function convertToProject(Proposal $proposal): void
    {
        DB::transaction(function () use ($proposal) {
            // 1. Set Proposal Status to accepted first and lock editing
            $proposal->status = 'accepted';
            $proposal->save();

            // Log activity
            $this->logActivity($proposal, 'accepted', [
                'notes' => 'Proposal accepted. Auto-onboarding and project instantiation triggered.'
            ]);

            // Dispatch ProposalAccepted Event
            $this->eventBus->dispatch(new ProposalAccepted([
                'proposal_id' => $proposal->id,
                'client_id' => $proposal->client_id,
                'total_amount' => $proposal->total_amount
            ], $proposal->organization_id));

            // 2. Client Invitation / Portal Access Checks
            $clientId = $proposal->client_id;
            $clientUser = User::find($clientId);
            $invitedNewUser = false;

            if (!$clientUser && $proposal->lead_id) {
                $lead = Lead::find($proposal->lead_id);
                if ($lead && $lead->email) {
                    $clientUser = User::where('email', $lead->email)->first();
                }
            }

            if (!$clientUser) {
                $invitedNewUser = true;
                // Create client user account dynamically
                $clientUser = User::create([
                    'name' => $proposal->lead ? $proposal->lead->name : 'Client Representative',
                    'email' => $proposal->lead ? $proposal->lead->email : 'client_' . Str::random(8) . '@example.com',
                    'password' => bcrypt(Str::random(16)),
                    'role' => 'client',
                    'status' => 'active',
                ]);
                $clientId = $clientUser->id;

                // Associate newly created user with proposal
                $proposal->client_id = $clientId;
                $proposal->save();
            } else {
                $clientId = $clientUser->id;
            }

            // Dispatch Invitation & Access Events if applicable
            if ($invitedNewUser) {
                $this->eventBus->dispatch(new ClientInvited([
                    'client_id' => $clientId,
                    'email' => $clientUser->email,
                    'invitation_token' => Str::random(40)
                ], $proposal->organization_id));
            }

            $this->eventBus->dispatch(new PortalAccessGranted([
                'client_id' => $clientId,
                'portal_url' => "/client/proposal/{$proposal->id}"
            ], $proposal->organization_id));

            // 3. Create Project with generated UUID, reference number & expected completion date
            $projectUuid = (string) Str::uuid();
            $referenceNumber = 'PRJ-' . strtoupper(Str::random(8));
            $expectedCompletion = now()->addDays(120);

            $project = Project::create([
                'uuid' => $projectUuid,
                'reference_number' => $referenceNumber,
                'user_id' => $clientId, // backward compatibility
                'client_id' => $clientId,
                'owner_id' => auth()->id() ?? User::where('role', 'admin')->first()?->id ?? 1,
                'organization_id' => $proposal->organization_id,
                'name' => $proposal->title,
                'type' => 'Professional Services',
                'status' => 'initiated',
                'budget' => $proposal->total_amount,
                'timeline' => '4 months',
                'expected_completion' => $expectedCompletion
            ]);

            // Dispatch ProjectCreated event
            $this->eventBus->dispatch(new ProposalProjectCreated([
                'project_id' => $project->id,
                'uuid' => $projectUuid,
                'reference_number' => $referenceNumber,
                'client_id' => $clientId
            ], $proposal->organization_id));

            // 4. Create Default Milestones (Discovery, UI/UX, Development, Testing, Deployment, Support)
            $milestoneSpecs = [
                ['title' => 'Discovery', 'due_days' => 14],
                ['title' => 'UI/UX', 'due_days' => 30],
                ['title' => 'Development', 'due_days' => 60],
                ['title' => 'Testing', 'due_days' => 75],
                ['title' => 'Deployment', 'due_days' => 90],
                ['title' => 'Support', 'due_days' => 120]
            ];

            $createdMilestones = [];
            foreach ($milestoneSpecs as $spec) {
                $milestone = ProjectMilestone::create([
                    'project_id' => $project->id,
                    'title' => $spec['title'],
                    'status' => 'pending',
                    'due_date' => now()->addDays($spec['due_days'])
                ]);
                $createdMilestones[$spec['title']] = $milestone;

                // Dispatch MilestoneCreated event
                $this->eventBus->dispatch(new MilestoneCreated([
                    'project_id' => $project->id,
                    'milestone_id' => $milestone->id,
                    'title' => $spec['title']
                ], $proposal->organization_id));
            }

            // 5. Generate the first onboarding checklist (Project Checklist Tasks) under Discovery milestone
            $checklistItems = [
                'Welcome call',
                'Requirements gathering',
                'Brand assets request',
                'Hosting confirmation',
                'Domain confirmation',
                'Content collection',
                'Kickoff meeting'
            ];

            foreach ($checklistItems as $title) {
                ProjectTask::create([
                    'milestone_id' => $createdMilestones['Discovery']->id,
                    'assignee_id' => null,
                    'title' => $title,
                    'priority' => 'medium',
                    'status' => 'todo',
                    'due_date' => now()->addDays(7)
                ]);
            }

            // Dispatch ChecklistCreated event
            $this->eventBus->dispatch(new ChecklistCreated([
                'project_id' => $project->id,
                'milestone_id' => $createdMilestones['Discovery']->id,
                'tasks_count' => count($checklistItems)
            ], $proposal->organization_id));

            // 6. Create Default Folder Structure (/Assets, /Documents, /Design, /Development, /Deliverables, /Invoices, /Contracts)
            $folders = ['Assets', 'Documents', 'Design', 'Development', 'Deliverables', 'Invoices', 'Contracts'];
            foreach ($folders as $folder) {
                ProjectFile::create([
                    'project_id' => $project->id,
                    'filename' => $folder,
                    'path' => '/' . $folder,
                    'user_id' => $clientId,
                    'folder' => '1',
                    'version' => 1
                ]);
            }

            // 7. Initialize Project discussion thread with Welcome Comment
            ProjectComment::create([
                'project_id' => $project->id,
                'user_id' => $project->owner_id,
                'content' => "Welcome to your JUANET project workspace! We have initialized default asset directories and onboarding checklists for your convenience. Let's build something exceptional.",
            ]);

            // 8. Generate immutable activity timeline for project timeline
            $timelineLogs = [
                ['activity' => 'proposal_accepted', 'msg' => 'Proposal signed and accepted.'],
                ['activity' => 'project_created', 'msg' => "Project created automatically (Ref: {$referenceNumber})."],
                ['activity' => 'milestones_created', 'msg' => 'Default project milestones initialized (Discovery, UI/UX, Development, Testing, Deployment, Support).'],
                ['activity' => 'client_invited', 'msg' => $invitedNewUser ? 'Client invited to Portal access.' : 'Client Portal linked.'],
                ['activity' => 'portal_enabled', 'msg' => 'Client Portal access successfully enabled.'],
                ['activity' => 'project_initialized', 'msg' => 'Project discussion thread and activity workspace initialized.']
            ];

            foreach ($timelineLogs as $log) {
                ProjectActivity::create([
                    'project_id' => $project->id,
                    'activity' => $log['activity'],
                    'metadata' => [
                        'message' => $log['msg'],
                        'proposal_id' => $proposal->id,
                        'client_id' => $clientId
                    ]
                ]);
            }

            // Dispatch initialization events
            $this->eventBus->dispatch(new ProjectInitialized([
                'project_id' => $project->id,
                'proposal_id' => $proposal->id,
            ], $proposal->organization_id));

            $this->eventBus->dispatch(new TimelineInitialized([
                'project_id' => $project->id,
            ], $proposal->organization_id));

            // 9. Update proposal status to terminal state 'converted'
            $proposal->status = 'converted';
            $proposal->save();

            // 10. CRM Linkage - update linked lead to 'won'
            if ($proposal->lead_id) {
                $lead = Lead::find($proposal->lead_id);
                if ($lead) {
                    $lead->status = 'won';
                    $lead->save();
                }
            }

            // Fire Converted Event (Backward compatibility)
            $this->eventBus->dispatch(new ProposalConverted([
                'proposal_id' => $proposal->id,
                'project_id' => $project->id,
                'lead_id' => $proposal->lead_id,
            ], $proposal->organization_id));
        });
    }

    /**
     * Log comments on a proposal.
     */
    public function addComment(Proposal $proposal, string $userId, string $content, ?int $parentId = null): ProposalComment
    {
        $comment = $proposal->comments()->create([
            'user_id' => $userId,
            'content' => $content,
            'parent_id' => $parentId
        ]);

        $this->logActivity($proposal, 'comment_added', [
            'comment_id' => $comment->id,
            'user_id' => $userId,
        ]);

        return $comment;
    }

    /**
     * Helper to create a proposal revision.
     */
    private function createRevision(Proposal $proposal, string $notes): void
    {
        $latest = $proposal->revisions()->orderBy('version', 'desc')->first();
        $nextVersion = $latest ? $latest->version + 1 : 1;

        $content = [
            'title' => $proposal->title,
            'total_amount' => $proposal->total_amount,
            'sections' => $proposal->sections->map(fn($s) => [
                'title' => $s->title,
                'content' => $s->content,
                'sort_order' => $s->sort_order,
            ])->toArray(),
            'items' => $proposal->items->map(fn($i) => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
            ])->toArray(),
            'notes' => $notes,
        ];

        ProposalRevision::create([
            'proposal_id' => $proposal->id,
            'version' => $nextVersion,
            'content' => $content,
            'created_by' => auth()->id() ?? $proposal->client_id,
        ]);
    }

    /**
     * Helper to log proposal activity.
     */
    private function logActivity(Proposal $proposal, string $activity, array $metadata = []): void
    {
        ProposalActivity::create([
            'proposal_id' => $proposal->id,
            'user_id' => auth()->id() ?? $proposal->client_id,
            'activity' => $activity,
            'metadata' => $metadata,
        ]);
    }
}
