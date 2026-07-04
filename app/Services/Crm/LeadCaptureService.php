<?php

namespace App\Services\Crm;

use App\Domain\CRM\Contracts\LeadRepositoryInterface;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Models\LeadSource;
use App\Domain\CRM\Events\LeadCreatedEvent;
use App\Contracts\EventBus;
use App\Services\TenantContext;
use App\Services\DTO\PublicLeadDto;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadCaptureService
{
    protected LeadRepositoryInterface $leadRepo;
    protected TenantContext $tenantContext;
    protected EventBus $eventBus;

    public function __construct(
        LeadRepositoryInterface $leadRepo,
        TenantContext $tenantContext,
        EventBus $eventBus
    ) {
        $this->leadRepo = $leadRepo;
        $this->tenantContext = $tenantContext;
        $this->eventBus = $eventBus;
    }

    /**
     * Capture a public lead, resolve tenant, normalize metadata, and persist to CRM.
     */
    public function capture(PublicLeadDto $dto): Lead
    {
        return DB::transaction(function () use ($dto) {
            // 1. Resolve Tenant Organization
            $tenant = $this->tenantContext->getTenant();
            if (!$tenant) {
                // Fallback to the default HQ organization
                $tenant = Organization::where('slug', 'juanet-hq')->first();
                if (!$tenant) {
                    $tenant = Organization::first();
                }
                if ($tenant) {
                    $this->tenantContext->setTenant($tenant);
                }
            }

            $orgId = $tenant ? $tenant->id : null;

            // 2. Find or Create the Lead Source for Public Website
            $leadSourceId = null;
            if ($orgId) {
                $leadSource = LeadSource::firstOrCreate(
                    [
                        'slug' => 'public-website',
                        'organization_id' => $orgId,
                    ],
                    [
                        'name' => 'Public Website',
                        'lock_version' => 1,
                    ]
                );
                $leadSourceId = $leadSource->id;
            }

            // 3. Determine Priority & Score Offset
            $priority = $this->determinePriority($dto->budget_range);

            // 4. Populate custom fields array (metadata fields)
            $customFields = [
                'source_page' => $dto->source,
                'service_interest' => $dto->service_interest,
                'budget_range' => $dto->budget_range,
                'message_payload' => $dto->message,
                'priority' => $priority,
                'utm_source' => $dto->utm_fields['utm_source'] ?? null,
                'utm_medium' => $dto->utm_fields['utm_medium'] ?? null,
                'utm_campaign' => $dto->utm_fields['utm_campaign'] ?? null,
                'utm_term' => $dto->utm_fields['utm_term'] ?? null,
                'utm_content' => $dto->utm_fields['utm_content'] ?? null,
                'referrer' => $dto->referrer,
                'user_agent' => $dto->user_agent,
                'ip_address' => $dto->ip_address,
                'captured_at' => now()->toIso8601String(),
            ];

            // 5. Build Lead creation data
            $leadData = [
                'organization_id' => $orgId,
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'status' => 'new',
                'lead_source_id' => $leadSourceId,
                'custom_fields' => $customFields,
                'lock_version' => 1,
            ];

            // 6. Create the Lead via LeadRepository
            $lead = $this->leadRepo->create($leadData);

            // 7. Log CRM Activity timeline entry
            LeadActivity::create([
                'organization_id' => $orgId,
                'lead_id' => $lead->id,
                'user_id' => null, // Public lead, no logged-in user
                'type' => 'creation',
                'description' => "Public lead captured via '{$dto->source}' form. Interest: '{$dto->service_interest}'.",
                'properties' => [
                    'source' => $dto->source,
                    'priority' => $priority,
                    'budget_range' => $dto->budget_range,
                    'interest' => $dto->service_interest,
                    'utm' => $dto->utm_fields,
                ],
            ]);

            // 8. Emit LeadCreatedEvent via EventBus
            $this->eventBus->dispatch(new LeadCreatedEvent($lead));

            return $lead;
        });
    }

    /**
     * Determine priority based on the estimated budget.
     */
    protected function determinePriority(?string $budgetRange): string
    {
        if (empty($budgetRange)) {
            return 'medium';
        }

        $budgetLower = strtolower($budgetRange);

        // KES 1M+, KES 3M+, KES 5M+, or 'enterprise' gets high priority
        if (
            str_contains($budgetLower, '1m') ||
            str_contains($budgetLower, '3m') ||
            str_contains($budgetLower, '5m') ||
            str_contains($budgetLower, 'enterprise') ||
            str_contains($budgetLower, 'million')
        ) {
            return 'high';
        }

        // KES 100k - 300k, 250k - 500k, or similar gets medium priority
        if (
            str_contains($budgetLower, '100k') ||
            str_contains($budgetLower, '250k') ||
            str_contains($budgetLower, '300k') ||
            str_contains($budgetLower, '500k')
        ) {
            return 'medium';
        }

        return 'low';
    }
}
