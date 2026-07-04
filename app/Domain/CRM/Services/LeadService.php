<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Contracts\LeadRepositoryInterface;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\Opportunity;
use App\Models\User;
use App\Domain\CRM\Events\LeadConverted;
use App\Contracts\EventBus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadService
{
    protected LeadRepositoryInterface $repo;
    protected LeadStateMachine $stateMachine;
    protected LeadAssignmentService $assignmentService;
    protected LeadScoringEngine $scoringEngine;
    protected DuplicateDetector $duplicateDetector;
    protected CustomFieldValidator $fieldValidator;
    protected LeadImportExportService $importExportService;
    protected EventBus $eventBus;

    public function __construct(
        LeadRepositoryInterface $repo,
        LeadStateMachine $stateMachine,
        LeadAssignmentService $assignmentService,
        LeadScoringEngine $scoringEngine,
        DuplicateDetector $duplicateDetector,
        CustomFieldValidator $fieldValidator,
        LeadImportExportService $importExportService,
        EventBus $eventBus
    ) {
        $this->repo = $repo;
        $this->stateMachine = $stateMachine;
        $this->assignmentService = $assignmentService;
        $this->scoringEngine = $scoringEngine;
        $this->duplicateDetector = $duplicateDetector;
        $this->fieldValidator = $fieldValidator;
        $this->importExportService = $importExportService;
        $this->eventBus = $eventBus;
    }

    public function getLead(string $id): ?Lead
    {
        return $this->repo->find($id);
    }

    /**
     * Create a new Lead with custom field validation, activity logging, scoring, and duplicate detection.
     */
    public function createLead(array $data): Lead
    {
        $organizationId = $data['organization_id'] ?? null;

        // 1. Validate custom fields if supplied
        if (isset($data['custom_fields']) && is_array($data['custom_fields']) && $organizationId) {
            $errors = $this->fieldValidator->validate('Lead', $organizationId, $data['custom_fields']);
            if (!empty($errors)) {
                throw new InvalidArgumentException("Custom field validation failed: " . json_encode($errors));
            }
        }

        return DB::transaction(function () use ($data) {
            // 2. Persist Lead via Repo
            $lead = $this->repo->create($data);

            // 3. Record Timeline Activity
            LeadActivity::create([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'user_id' => $data['created_by'] ?? null,
                'type' => 'creation',
                'description' => "Lead profile successfully created for '{$lead->name}'.",
                'properties' => $lead->only(['name', 'email', 'phone', 'status']),
            ]);

            // 4. Auto-calculate Score
            $this->scoringEngine->updateScore($lead);

            // 5. Run Duplicate Detection
            $this->duplicateDetector->flagDuplicates($lead);

            return $lead;
        });
    }

    /**
     * Update an existing Lead with audited changes, recalculating score and duplicates.
     */
    public function updateLead(string $id, array $data): ?Lead
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            return null;
        }

        // 1. Validate custom fields if updated
        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $errors = $this->fieldValidator->validate('Lead', $lead->organization_id, $data['custom_fields']);
            if (!empty($errors)) {
                throw new InvalidArgumentException("Custom field validation failed: " . json_encode($errors));
            }
        }

        return DB::transaction(function () use ($lead, $data) {
            $oldValues = $lead->only(['name', 'email', 'phone', 'status', 'company_id', 'contact_id']);
            
            // 2. Perform database update
            $updatedLead = $this->repo->update($lead->id, $data);

            // 3. Determine audited changes for the activity timeline
            $newValues = $updatedLead->only(['name', 'email', 'phone', 'status', 'company_id', 'contact_id']);
            $changes = array_diff_assoc($newValues, $oldValues);

            if (!empty($changes)) {
                LeadActivity::create([
                    'organization_id' => $updatedLead->organization_id,
                    'lead_id' => $updatedLead->id,
                    'user_id' => $data['updated_by'] ?? null,
                    'type' => 'edit',
                    'description' => "Updated fields: " . implode(', ', array_keys($changes)) . ".",
                    'properties' => [
                        'changes' => $changes,
                        'old_values' => $oldValues,
                    ],
                ]);

                // 4. Recalculate score & duplicate flags
                $this->scoringEngine->updateScore($updatedLead);

                if (isset($changes['email']) || isset($changes['phone'])) {
                    $this->duplicateDetector->flagDuplicates($updatedLead);
                }
            }

            return $updatedLead;
        });
    }

    public function deleteLead(string $id): bool
    {
        return $this->repo->delete($id);
    }

    public function listLeads(?string $orgId = null): Collection
    {
        return $this->repo->getByOrganization($orgId);
    }

    public function listLeadsByOwner(string $userId, ?string $orgId = null): Collection
    {
        return $this->repo->getByUser($userId, $orgId);
    }

    /**
     * Transition lead status using the deterministic Finite State Machine.
     */
    public function changeLeadStatus(string $id, string $toStatus, ?string $changedBy = null, ?string $reason = null): Lead
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            throw new InvalidArgumentException("Lead not found [{$id}].");
        }

        $lead = $this->stateMachine->transition($lead, $toStatus, $changedBy, $reason);

        // Recalculate score after status shift (engagement scoring)
        $this->scoringEngine->updateScore($lead);

        return $lead;
    }

    /**
     * Reassign Lead Ownership.
     */
    public function assignLead(string $id, ?string $toUserId, ?string $assignedBy = null, string $method = 'manual'): Lead
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            throw new InvalidArgumentException("Lead not found [{$id}].");
        }

        $lead = $this->assignmentService->assign($lead, $toUserId, $assignedBy, $method);
        $this->scoringEngine->updateScore($lead);

        return $lead;
    }

    /**
     * Reassign Lead via Round-Robin.
     */
    public function assignLeadRoundRobin(string $id, array $userIds, ?string $assignedBy = null): Lead
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            throw new InvalidArgumentException("Lead not found [{$id}].");
        }

        $lead = $this->assignmentService->assignRoundRobin($lead, $userIds, $assignedBy);
        $this->scoringEngine->updateScore($lead);

        return $lead;
    }

    /**
     * Reassign Lead via Load-Balanced strategy.
     */
    public function assignLeadLoadBalanced(string $id, array $userIds, ?string $assignedBy = null): Lead
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            throw new InvalidArgumentException("Lead not found [{$id}].");
        }

        $lead = $this->assignmentService->assignLoadBalanced($lead, $userIds, $assignedBy);
        $this->scoringEngine->updateScore($lead);

        return $lead;
    }

    /**
     * Convert Lead to Account (Company), Contact, and Opportunity, and mark status as Qualified/Won.
     */
    public function convertLead(string $id, array $data, ?string $userId = null): array
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            throw new InvalidArgumentException("Lead not found [{$id}].");
        }

        if ($lead->status === 'won' || $lead->status === 'converted') {
            throw new InvalidArgumentException("Lead is already converted.");
        }

        return DB::transaction(function () use ($lead, $data, $userId) {
            $orgId = $lead->organization_id;

            // 1. Create Company (Account) if requested
            $company = null;
            if (!empty($data['company_name'])) {
                $company = Company::create([
                    'organization_id' => $orgId,
                    'name' => $data['company_name'],
                    'phone' => $lead->phone,
                    'website' => $data['company_website'] ?? null,
                    'created_by' => $userId,
                ]);
            }

            // 2. Create Contact if requested
            $contact = null;
            if (!empty($data['create_contact'])) {
                $nameParts = explode(' ', $lead->name, 2);
                $firstName = $nameParts[0] ?? 'Unknown';
                $lastName = $nameParts[1] ?? 'Unknown';

                $contact = Contact::create([
                    'organization_id' => $orgId,
                    'company_id' => $company?->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'created_by' => $userId,
                ]);
            }

            // 3. Create Opportunity if requested
            $opportunity = null;
            if (!empty($data['create_opportunity'])) {
                $opportunity = Opportunity::create([
                    'organization_id' => $orgId,
                    'company_id' => $company?->id,
                    'contact_id' => $contact?->id,
                    'pipeline_id' => $data['pipeline_id'],
                    'pipeline_stage_id' => $data['pipeline_stage_id'],
                    'user_id' => $lead->user_id, // Same owner
                    'name' => $data['opportunity_name'] ?? ($lead->name . ' Deal'),
                    'amount' => $data['opportunity_amount'] ?? 0.00,
                    'close_date' => $data['opportunity_close_date'] ?? now()->addDays(30)->toDateString(),
                    'status' => 'open',
                    'created_by' => $userId,
                ]);
            }

            // 4. Update Lead with conversions
            $lead->company_id = $company?->id;
            $lead->contact_id = $contact?->id;
            $lead->status = 'won'; // Lead won and converted
            $lead->save();

            // 5. Record Conversion Log on Lead Activity Timeline
            LeadActivity::create([
                'organization_id' => $orgId,
                'lead_id' => $lead->id,
                'user_id' => $userId,
                'type' => 'conversion',
                'description' => "Successfully converted lead to Company: '" . ($company?->name ?? 'None') . "', Contact: '" . ($contact?->first_name . ' ' . $contact?->last_name) . "' and Opportunity: '" . ($opportunity?->name ?? 'None') . "'.",
                'properties' => [
                    'company_id' => $company?->id,
                    'contact_id' => $contact?->id,
                    'opportunity_id' => $opportunity?->id,
                ],
            ]);

            // Recalculate score
            $this->scoringEngine->updateScore($lead);

            // 6. Dispatch LeadConverted event via EventBus
            $this->eventBus->dispatch(new LeadConverted(
                $lead,
                (string) ($company?->id ?? ''),
                (string) ($contact?->id ?? ''),
                $opportunity?->id,
                $userId
            ));

            return [
                'lead' => $lead,
                'company' => $company,
                'contact' => $contact,
                'opportunity' => $opportunity,
            ];
        });
    }

    /**
     * Get Activity Timeline.
     */
    public function getTimeline(string $id): Collection
    {
        return LeadActivity::where('lead_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Export To CSV helper.
     */
    public function exportLeads(?string $orgId = null): string
    {
        $leads = $this->listLeads($orgId);
        return $this->importExportService->exportToCsv($leads);
    }

    /**
     * Import CSV helper.
     */
    public function importLeads(string $csvContent, string $organizationId, ?string $userId = null, bool $dryRun = false): array
    {
        return $this->importExportService->importFromCsv($csvContent, $organizationId, $userId, $dryRun);
    }

    /**
     * Rollback import helper.
     */
    public function rollbackImport(string $batchId, string $organizationId): array
    {
        return $this->importExportService->rollbackBatch($batchId, $organizationId);
    }

    /**
     * Run Duplicate Check helper.
     */
    public function findDuplicates(string $id): Collection
    {
        $lead = $this->getLead($id);
        if (!$lead) {
            return collect();
        }
        return $this->duplicateDetector->findDuplicates($lead);
    }

    /**
     * Merge duplicate leads helper.
     */
    public function mergeLeads(string $primaryId, string $duplicateId, array $overrideFields = [], ?string $mergedBy = null): Lead
    {
        $primary = $this->getLead($primaryId);
        $duplicate = $this->getLead($duplicateId);

        if (!$primary || !$duplicate) {
            throw new InvalidArgumentException("Primary or duplicate lead not found.");
        }

        return $this->duplicateDetector->mergeLeads($primary, $duplicate, $overrideFields, $mergedBy);
    }
}
