<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Domain\CRM\Models\Lead;
use App\Domain\Proposal\Models\Proposal;
use App\Domain\Proposal\Models\ProposalItem;
use App\Domain\Proposal\Models\ProposalSection;
use App\Domain\Proposal\Models\ProposalRevision;
use App\Domain\Proposal\Services\ProposalService;
use App\Domain\Contract\Models\Contract;
use App\Domain\Contract\Models\Signature;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectMilestone;
use App\Domain\Project\Models\ProjectTask;
use App\Contracts\EventBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalManagementAndEContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $manager;
    protected Organization $organization;
    protected Lead $lead;
    protected ProposalService $proposalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proposalService = app(ProposalService::class);

        // 1. Setup Tenant Organization
        $this->organization = Organization::create([
            'name' => 'JUANET Enterprise',
            'domain' => 'juanet.co.ke',
            'status' => 'active',
        ]);

        // 2. Setup Client & Manager Users
        $this->client = User::create([
            'name' => 'Alice Wanjiru',
            'email' => 'alice@apex.co.ke',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'status' => 'active',
        ]);

        $this->manager = User::create([
            'name' => 'Lead Architect',
            'email' => 'architect@juanet.co.ke',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // 3. Setup CRM Lead
        $this->lead = Lead::create([
            'organization_id' => $this->organization->id,
            'name' => 'Alice Wanjiru',
            'company_name' => 'Apex Digital',
            'email' => 'alice@apex.co.ke',
            'status' => 'qualified',
        ]);
    }

    /** @test */
    public function it_can_create_a_proposal_with_sections_and_line_items()
    {
        $data = [
            'lead_id' => $this->lead->id,
            'client_id' => $this->client->id,
            'organization_id' => $this->organization->id,
            'title' => 'Enterprise Fiber & Wifi Solution',
            'total_amount' => 48000.00,
            'sections' => [
                ['title' => 'Executive Summary', 'content' => 'High speed fiber installation details.', 'sort_order' => 1],
                ['title' => 'Terms', 'content' => 'SLA terms apply.', 'sort_order' => 2]
            ],
            'items' => [
                ['description' => 'Outdoor Access Points', 'quantity' => 4, 'unit_price' => 12000.00]
            ]
        ];

        $proposal = $this->proposalService->createProposal($data);

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'title' => 'Enterprise Fiber & Wifi Solution',
            'status' => 'draft',
            'total_amount' => 48000.00,
        ]);

        $this->assertDatabaseHas('proposal_sections', [
            'proposal_id' => $proposal->id,
            'title' => 'Executive Summary'
        ]);

        $this->assertDatabaseHas('proposal_items', [
            'proposal_id' => $proposal->id,
            'description' => 'Outdoor Access Points'
        ]);

        // Check if revision 1 is created automatically
        $this->assertDatabaseHas('proposal_revisions', [
            'proposal_id' => $proposal->id,
            'version' => 1
        ]);
    }

    /** @test */
    public function it_can_track_revision_history_and_restore_a_version()
    {
        // 1. Create original proposal
        $data = [
            'lead_id' => $this->lead->id,
            'client_id' => $this->client->id,
            'organization_id' => $this->organization->id,
            'title' => 'Original Draft Title',
            'total_amount' => 10000.00,
            'sections' => [
                ['title' => 'Original Sec', 'content' => 'Original content', 'sort_order' => 1]
            ],
            'items' => [
                ['description' => 'Original Item', 'quantity' => 1, 'unit_price' => 10000.00]
            ]
        ];

        $proposal = $this->proposalService->createProposal($data);

        // 2. Update proposal to v2
        $updateData = [
            'title' => 'Updated Draft Title',
            'total_amount' => 15000.00,
            'sections' => [
                ['title' => 'Updated Sec', 'content' => 'Updated content', 'sort_order' => 1]
            ],
            'items' => [
                ['description' => 'Updated Item', 'quantity' => 1, 'unit_price' => 15000.00]
            ],
            'revision_notes' => 'Changing to higher grade gear'
        ];

        $this->proposalService->updateProposal($proposal, $updateData);

        $this->assertEquals('Updated Draft Title', $proposal->fresh()->title);
        $this->assertCount(2, $proposal->revisions);

        // 3. Restore to revision 1 (original v1)
        $v1 = $proposal->revisions()->where('version', 1)->first();
        $this->proposalService->restoreRevision($proposal, $v1->id);

        $proposal = $proposal->fresh();
        $this->assertEquals('Original Draft Title', $proposal->title);
        $this->assertEquals(10000.00, $proposal->total_amount);
        $this->assertEquals('Original Sec', $proposal->sections->first()->title);
    }

    /** @test */
    public function it_enforces_valid_proposal_state_transitions()
    {
        $proposal = $this->proposalService->createProposal([
            'client_id' => $this->client->id,
            'title' => 'Draft Proposal',
        ]);

        // valid transition: draft -> sent
        $this->proposalService->transitionStatus($proposal, 'sent');
        $this->assertEquals('sent', $proposal->status);

        // invalid transition: sent -> draft (not allowed in transition lookup)
        $this->expectException(\InvalidArgumentException::class);
        $this->proposalService->transitionStatus($proposal, 'draft');
    }

    /** @test */
    public function it_can_electronically_sign_and_automatically_convert_proposal_to_project()
    {
        $proposal = $this->proposalService->createProposal([
            'lead_id' => $this->lead->id,
            'client_id' => $this->client->id,
            'organization_id' => $this->organization->id,
            'title' => 'Signed Fiber Setup',
            'total_amount' => 5000.00,
            'sections' => [
                ['title' => 'Scope', 'content' => 'Details', 'sort_order' => 1]
            ],
            'items' => [
                ['description' => 'Core installation', 'quantity' => 1, 'unit_price' => 5000.00]
            ]
        ]);

        // Transition to sent before signing
        $proposal->status = 'sent';
        $proposal->save();

        // Sign proposal
        $this->proposalService->signProposal($proposal, [
            'signer_id' => $this->client->id,
            'ip_address' => '192.168.1.1',
            'type' => 'mouse',
            'representation' => 'data:image/png;base64,...'
        ]);

        // Assert proposal status is converted
        $this->assertEquals('converted', $proposal->fresh()->status);

        // Assert Contract and Signature records were stored
        $this->assertDatabaseHas('contracts', [
            'client_id' => $this->client->id,
            'status' => 'signed'
        ]);

        $this->assertDatabaseHas('signatures', [
            'signer_id' => $this->client->id,
            'ip_address' => '192.168.1.1',
            'signature_type' => 'mouse'
        ]);

        // Assert CRM Lead has transitioned to won
        $this->assertDatabaseHas('leads', [
            'id' => $this->lead->id,
            'status' => 'won'
        ]);

        // Assert a Project has been automatically created with correct attributes
        $this->assertDatabaseHas('projects', [
            'client_id' => $this->client->id,
            'name' => 'Signed Fiber Setup',
            'budget' => 5000.00,
        ]);

        $project = Project::where('name', 'Signed Fiber Setup')->first();
        $this->assertNotNull($project->uuid);
        $this->assertNotNull($project->reference_number);
        $this->assertNotNull($project->expected_completion);

        // Assert the six core milestones
        $expectedMilestones = ['Discovery', 'UI/UX', 'Development', 'Testing', 'Deployment', 'Support'];
        foreach ($expectedMilestones as $title) {
            $this->assertDatabaseHas('project_milestones', [
                'project_id' => $project->id,
                'title' => $title
            ]);
        }

        // Assert 7 checklist items under Discovery milestone
        $discoveryMilestone = ProjectMilestone::where('project_id', $project->id)->where('title', 'Discovery')->first();
        $this->assertNotNull($discoveryMilestone);

        $expectedChecklist = [
            'Welcome call',
            'Requirements gathering',
            'Brand assets request',
            'Hosting confirmation',
            'Domain confirmation',
            'Content collection',
            'Kickoff meeting'
        ];

        foreach ($expectedChecklist as $title) {
            $this->assertDatabaseHas('project_tasks', [
                'milestone_id' => $discoveryMilestone->id,
                'title' => $title
            ]);
        }

        // Assert default directories / files (7 folders)
        $expectedFolders = ['Assets', 'Documents', 'Design', 'Development', 'Deliverables', 'Invoices', 'Contracts'];
        foreach ($expectedFolders as $folder) {
            $this->assertDatabaseHas('project_files', [
                'project_id' => $project->id,
                'filename' => $folder,
                'path' => '/' . $folder,
                'folder' => '1'
            ]);
        }

        // Assert welcome comments and activity logs are stored
        $this->assertDatabaseHas('project_comments', [
            'project_id' => $project->id,
            'content' => "Welcome to your JUANET project workspace! We have initialized default asset directories and onboarding checklists for your convenience. Let's build something exceptional."
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'activity' => 'proposal_accepted'
        ]);
    }
}
