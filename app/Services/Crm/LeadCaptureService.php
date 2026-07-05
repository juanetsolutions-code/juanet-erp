<?php

namespace App\Services\Crm;

use App\Domain\CRM\Contracts\LeadRepositoryInterface;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Models\LeadSource;
use App\Domain\CRM\Models\Tag;
use App\Domain\CRM\Events\LeadCreatedEvent;
use App\Contracts\EventBus;
use App\Services\TenantContext;
use App\Services\DTO\PublicLeadDto;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadCaptureService
{
    protected LeadRepositoryInterface $leadRepo;
    protected TenantContext $tenantContext;
    protected EventBus $eventBus;
    protected \App\Domain\CRM\Services\LeadAssignmentService $assignmentService;

    public function __construct(
        LeadRepositoryInterface $leadRepo,
        TenantContext $tenantContext,
        EventBus $eventBus,
        ?\App\Domain\CRM\Services\LeadAssignmentService $assignmentService = null
    ) {
        $this->leadRepo = $leadRepo;
        $this->tenantContext = $tenantContext;
        $this->eventBus = $eventBus;
        $this->assignmentService = $assignmentService ?? app(\App\Domain\CRM\Services\LeadAssignmentService::class);
    }

    /**
     * Capture a public lead, resolve tenant, normalize metadata, and persist to CRM.
     */
    public function capture(PublicLeadDto $dto, ?string $correlationId = null): Lead
    {
        return DB::transaction(function () use ($dto, $correlationId) {
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

            // Check if there's any matching existing lead for deduplication (Tenant Isolation guaranteed)
            $existingLead = $this->findMatchingLead($dto, $orgId);
            $isReturningVisitor = $existingLead !== null;

            // Compute Lead Score, Priority, and Estimated Deal Size
            $scoring = $this->calculateConversionScore($dto, $isReturningVisitor);
            $priority = $scoring['priority'];

            if ($existingLead) {
                // Merge duplicate lead
                $lead = $this->mergeDuplicateLead($existingLead, $dto, $scoring, $correlationId);

                // Publish LeadMerged event
                $this->eventBus->dispatch(new \App\Domain\CRM\Events\LeadMerged(
                    lead: $lead,
                    inquiryCount: $lead->crm_lead_metadata['inquiry_count'] ?? 2,
                    correlationId: $correlationId
                ));

                return $lead;
            }

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
                'landing_page' => $dto->landing_page,
                'exit_page' => $dto->exit_page,
                'session_id' => $dto->session_id,
                'captured_at' => now()->toIso8601String(),
                'inquiry_count' => 1,
                'last_contact_at' => now()->toIso8601String(),
                'estimated_deal_size' => $scoring['estimated_deal_size'],
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
                'crm_lead_metadata' => $customFields,
                'score' => $scoring['score'],
                'score_breakdown' => $scoring['breakdown'],
                'duplicate_status' => 'none',
                'lock_version' => 1,
            ];

            // 6. Create the Lead via LeadRepository
            $lead = $this->leadRepo->create($leadData);

            // 7. Auto Assignment routing via Round-Robin
            $userIds = \App\Models\OrganizationMember::where('organization_id', $orgId)
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();

            if (!empty($userIds) && isset($this->assignmentService)) {
                try {
                    $lead = $this->assignmentService->assignRoundRobin($lead, $userIds, null);
                } catch (\Throwable $e) {
                    Log::warning("Round-robin auto-assignment failed: " . $e->getMessage());
                }
            }

            // 8. Log CRM Activity timeline entry
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
                    'score' => $scoring['score'],
                    'estimated_deal_size' => $scoring['estimated_deal_size'],
                ],
            ]);

            // 9. Auto Tagging Engine
            $this->autoTagLead($lead, $dto);

            // 10. Schedule initial follow-up task, 24h reminder, and sales owner notification
            $this->scheduleFollowUpAutomation($lead, $dto, $priority, $orgId);

            // 11. Emit domain & analytics events via EventBus
            $this->eventBus->dispatch(new LeadCreatedEvent($lead));
            $this->eventBus->dispatch(new \App\Domain\CRM\Events\LeadCaptured(lead: $lead, correlationId: $correlationId));

            if ($scoring['score'] >= 70) {
                $this->eventBus->dispatch(new \App\Domain\CRM\Events\LeadQualified(lead: $lead, correlationId: $correlationId));
            }

            return $lead;
        });
    }

    /**
     * Compute Lead Score (0-100), Priority, and Estimated Deal Size based on multi-dimensional rules.
     */
    protected function calculateConversionScore(PublicLeadDto $dto, bool $isReturningVisitor): array
    {
        $score = 0;
        $breakdown = [
            'budget' => 0,
            'company_size' => 0,
            'email_trust' => 0,
            'intent_complexity' => 0,
            'returning_visitor' => 0,
            'acquisition_source' => 0,
        ];

        // 1. Budget & Deal Size Estimation
        $budget = strtolower($dto->budget_range ?? '');
        $estimatedDealSize = 0;
        $budgetPoints = 0;

        if (str_contains($budget, '5m') || str_contains($budget, 'enterprise') || str_contains($budget, 'million')) {
            $budgetPoints = 30;
            $estimatedDealSize = 5000000;
        } elseif (str_contains($budget, '3m') || str_contains($budget, '1m')) {
            $budgetPoints = 25;
            $estimatedDealSize = 1500000;
        } elseif (str_contains($budget, '500k')) {
            $budgetPoints = 20;
            $estimatedDealSize = 500000;
        } elseif (str_contains($budget, '250k') || str_contains($budget, '300k')) {
            $budgetPoints = 15;
            $estimatedDealSize = 250000;
        } elseif (str_contains($budget, '100k')) {
            $budgetPoints = 10;
            $estimatedDealSize = 100000;
        } else {
            $budgetPoints = 5;
            $estimatedDealSize = 25000; // Minimal baseline
        }
        $breakdown['budget'] = $budgetPoints;
        $score += $budgetPoints;

        // 2. Company Size / Value
        $companySizePoints = 0;
        $company = strtolower($dto->company ?? '');
        if (!empty($company)) {
            if (strlen($company) > 3 && !in_array($company, ['none', 'self', 'na', 'n/a', 'personal'])) {
                $companySizePoints = 15;
            } else {
                $companySizePoints = 5;
            }
        }
        $breakdown['company_size'] = $companySizePoints;
        $score += $companySizePoints;

        // 3. Email Trust / Quality (Business Email vs. Public/Free Domain)
        $email = strtolower($dto->email);
        $freeDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com', 'protonmail.com', 'zoho.com'];
        $emailDomain = substr(strrchr($email, "@"), 1);
        
        $emailTrustPoints = 0;
        if (!in_array($emailDomain, $freeDomains)) {
            $emailTrustPoints = 20;
        } else {
            $emailTrustPoints = 5;
        }
        $breakdown['email_trust'] = $emailTrustPoints;
        $score += $emailTrustPoints;

        // 4. Intent & Message Complexity
        $message = $dto->message ?? '';
        $messageLength = strlen($message);
        $intentPoints = 0;

        if ($messageLength > 200) {
            $intentPoints = 15;
        } elseif ($messageLength > 50) {
            $intentPoints = 10;
        } elseif ($messageLength > 10) {
            $intentPoints = 5;
        }
        $breakdown['intent_complexity'] = $intentPoints;
        $score += $intentPoints;

        // 5. Returning Visitor
        $visitorPoints = 0;
        if ($isReturningVisitor) {
            $visitorPoints = 10;
        }
        $breakdown['returning_visitor'] = $visitorPoints;
        $score += $visitorPoints;

        // 6. Acquisition / Referral Source
        $sourcePoints = 0;
        $referrer = strtolower($dto->referrer ?? '');
        $utmSource = strtolower($dto->utm_fields['utm_source'] ?? '');

        if (str_contains($referrer, 'google') || $utmSource === 'google' || $utmSource === 'adwords' || $utmSource === 'cpc') {
            $sourcePoints = 10;
        } elseif (str_contains($referrer, 'linkedin') || $utmSource === 'linkedin') {
            $sourcePoints = 8;
        } elseif (!empty($referrer)) {
            $sourcePoints = 5;
        }
        $breakdown['acquisition_source'] = $sourcePoints;
        $score += $sourcePoints;

        // Normalize final score to 0 - 100
        $finalScore = min(100, $score);

        // Determine priority based on final score
        $priority = 'low';
        if ($finalScore >= 70) {
            $priority = 'high';
        } elseif ($finalScore >= 40) {
            $priority = 'medium';
        }

        return [
            'score' => $finalScore,
            'priority' => $priority,
            'estimated_deal_size' => $estimatedDealSize,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Search existing leads safely in a database-agnostic way for deduplication (Tenant Isolated).
     */
    protected function findMatchingLead(PublicLeadDto $dto, ?string $orgId): ?Lead
    {
        if (!$orgId) {
            return null;
        }

        // Keep database queries simple and fully SQLite + Postgres compatible
        $potentialLeads = Lead::where('organization_id', $orgId)
            ->where(function ($q) use ($dto) {
                $q->where('email', $dto->email);
                if ($dto->phone) {
                    $q->orWhere('phone', $dto->phone);
                }
                $q->orWhere('name', $dto->name);
            })
            ->get();

        $normalizedName = strtolower(preg_replace('/[^a-z0-9]/', '', $dto->name));
        $normalizedPhone = $dto->phone ? preg_replace('/[^0-9]/', '', $dto->phone) : null;

        foreach ($potentialLeads as $lead) {
            // 1. Check email match
            if (strtolower($lead->email) === strtolower($dto->email)) {
                return $lead;
            }

            // 2. Check phone match
            if ($normalizedPhone && $lead->phone) {
                $leadPhoneNormalized = preg_replace('/[^0-9]/', '', $lead->phone);
                if ($leadPhoneNormalized === $normalizedPhone) {
                    return $lead;
                }
            }

            // 3. Check company match (from custom fields / metadata)
            if (!empty($dto->company)) {
                $leadCompany = $lead->crm_lead_metadata['company'] ?? $lead->custom_fields['company'] ?? '';
                if (strtolower($leadCompany) === strtolower($dto->company)) {
                    return $lead;
                }
            }

            // 4. Check normalized name match
            $leadNameNormalized = strtolower(preg_replace('/[^a-z0-9]/', '', $lead->name));
            if ($leadNameNormalized === $normalizedName) {
                return $lead;
            }
        }

        return null;
    }

    /**
     * Merge duplicate lead, incrementing inquiry counts and timeline activities.
     */
    protected function mergeDuplicateLead(Lead $lead, PublicLeadDto $dto, array $scoring, ?string $correlationId): Lead
    {
        $metadata = $lead->crm_lead_metadata ?? $lead->custom_fields ?? [];
        
        // Increment inquiry count
        $inquiryCount = ($metadata['inquiry_count'] ?? 1) + 1;
        $metadata['inquiry_count'] = $inquiryCount;
        
        // Refresh last contact at
        $metadata['last_contact_at'] = now()->toIso8601String();
        
        // Update UTM parameters to the latest campaign if provided
        foreach ($dto->utm_fields as $key => $val) {
            if ($val) {
                $metadata[$key] = $val;
            }
        }
        
        // Update referrer, user_agent, ip_address
        $metadata['referrer'] = $dto->referrer ?? $metadata['referrer'] ?? null;
        $metadata['user_agent'] = $dto->user_agent ?? $metadata['user_agent'] ?? null;
        $metadata['ip_address'] = $dto->ip_address ?? $metadata['ip_address'] ?? null;
        $metadata['captured_at'] = now()->toIso8601String();
        
        // Save back
        $lead->crm_lead_metadata = $metadata;
        
        // Merge custom_fields
        $customFields = $lead->custom_fields ?? [];
        $customFields = array_merge($customFields, $metadata);
        $lead->custom_fields = $customFields;
        
        // Update score and score breakdown
        $lead->score = $scoring['score'];
        $lead->score_breakdown = $scoring['breakdown'];
        $lead->duplicate_status = 'potential';
        $lead->last_activity_at = now();
        $lead->save();

        // Append Activity Timeline
        LeadActivity::create([
            'organization_id' => $lead->organization_id,
            'lead_id' => $lead->id,
            'user_id' => null,
            'type' => 'creation',
            'description' => "Duplicate inquiry captured from '{$dto->source}'. Inquiry count incremented to {$inquiryCount}.",
            'properties' => [
                'source' => $dto->source,
                'inquiry_count' => $inquiryCount,
                'score' => $scoring['score'],
                'interest' => $dto->service_interest,
            ],
        ]);

        return $lead;
    }

    /**
     * Automatically generate and assign descriptive, colored lead tags based on intent keywords.
     */
    protected function autoTagLead(Lead $lead, PublicLeadDto $dto): void
    {
        $orgId = $lead->organization_id;
        if (!$orgId) return;

        $tagMappings = [
            'website-design' => ['web', 'website', 'design', 'landing page', 'portfolio', 'frontend', 'ui/ux', 'blade', 'tailwind', 'react'],
            'crm' => ['crm', 'lead', 'customer relationship', 'salesforce', 'hubspot', 'pipeline'],
            'saas' => ['saas', 'software as a service', 'subscription', 'multi-tenant', 'billing', 'tenant', 'platform'],
            'mobile-app' => ['mobile', 'app', 'ios', 'android', 'flutter', 'react native', 'phone', 'kotlin', 'swift'],
            'ai' => ['ai', 'artificial intelligence', 'llm', 'gpt', 'gemini', 'openai', 'model', 'agent', 'rag', 'machine learning'],
            'automation' => ['automat', 'workflow', 'zapier', 'cron', 'queue', 'optimize', 'webhook'],
            'branding' => ['brand', 'logo', 'identity', 'wireframe', 'vector', 'guideline'],
            'ecommerce' => ['ecommerce', 'shop', 'cart', 'checkout', 'payment', 'daraja', 'mpesa', 'billing', 'store'],
            'consultation' => ['consult', 'advice', 'audit', 'review', 'architecture', 'strategy'],
        ];

        $matchedTags = [];
        $textToSearch = strtolower(
            ($dto->service_interest ?? '') . ' ' . 
            ($dto->message ?? '') . ' ' . 
            ($dto->company ?? '')
        );

        foreach ($tagMappings as $tagSlug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textToSearch, $keyword)) {
                    $matchedTags[] = $tagSlug;
                    break;
                }
            }
        }

        if (empty($matchedTags)) {
            $matchedTags[] = 'consultation';
        }

        $tagMetadata = [
            'website-design' => ['name' => 'Website Design', 'color' => '#3B82F6'],
            'crm' => ['name' => 'CRM Systems', 'color' => '#10B981'],
            'saas' => ['name' => 'SaaS Platforms', 'color' => '#8B5CF6'],
            'mobile-app' => ['name' => 'Mobile Apps', 'color' => '#EC4899'],
            'ai' => ['name' => 'Artificial Intelligence', 'color' => '#F59E0B'],
            'automation' => ['name' => 'Workflows & Automation', 'color' => '#06B6D4'],
            'branding' => ['name' => 'Corporate Branding', 'color' => '#6366F1'],
            'ecommerce' => ['name' => 'E-Commerce', 'color' => '#14B8A6'],
            'consultation' => ['name' => 'General Consultation', 'color' => '#6B7280'],
        ];

        $tagIds = [];
        foreach ($matchedTags as $slug) {
            $meta = $tagMetadata[$slug] ?? ['name' => ucfirst(str_replace('-', ' ', $slug)), 'color' => '#6B7280'];
            $tag = Tag::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'slug' => $slug,
                ],
                [
                    'name' => $meta['name'],
                    'color' => $meta['color'],
                    'lock_version' => 1,
                ]
            );
            $tagIds[] = $tag->id;
        }

        $pivotData = [];
        foreach ($tagIds as $id) {
            $pivotData[$id] = ['taggable_type' => Lead::class];
        }
        $lead->tags()->syncWithoutDetaching($pivotData);
    }

    /**
     * Schedule follow-up activities, 24-hour verification task, and sales owner notification.
     */
    protected function scheduleFollowUpAutomation(Lead $lead, PublicLeadDto $dto, string $priority, ?string $orgId): void
    {
        try {
            // 1. Initial follow-up task (due in 30 mins)
            $task = \App\Domain\CRM\Activities\Models\Activity::create([
                'organization_id' => $orgId,
                'loggable_type' => Lead::class,
                'loggable_id' => $lead->id,
                'user_id' => $lead->user_id,
                'type' => 'follow_up_task',
                'subject' => 'Initial Lead Follow-up',
                'description' => "Initial follow-up task for newly captured lead: {$lead->name} ({$lead->email}).",
                'due_at' => now()->addMinutes(30),
                'is_completed' => false,
                'priority' => $priority,
                'lock_version' => 1,
            ]);

            $this->eventBus->dispatch(new \App\Domain\CRM\Events\TaskCreated([
                'id' => $task->id,
                'organization_id' => $orgId,
                'lead_id' => $lead->id,
                'user_id' => $lead->user_id,
                'type' => 'follow_up_task',
                'subject' => $task->subject,
                'due_at' => $task->due_at->toIso8601String(),
            ]));

            // 2. Reminder task (due in 24 hours)
            $reminder = \App\Domain\CRM\Activities\Models\Activity::create([
                'organization_id' => $orgId,
                'loggable_type' => Lead::class,
                'loggable_id' => $lead->id,
                'user_id' => $lead->user_id,
                'type' => 'reminder',
                'subject' => '24-Hour Follow-up Reminder',
                'description' => "Reminder to verify follow-up status for lead: {$lead->name}.",
                'due_at' => now()->addHours(24),
                'is_completed' => false,
                'priority' => $priority,
                'lock_version' => 1,
            ]);

            $this->eventBus->dispatch(new \App\Domain\CRM\Events\TaskCreated([
                'id' => $reminder->id,
                'organization_id' => $orgId,
                'lead_id' => $lead->id,
                'user_id' => $lead->user_id,
                'type' => 'reminder',
                'subject' => $reminder->subject,
                'due_at' => $reminder->due_at->toIso8601String(),
            ]));

            // 3. Sales owner notification
            if ($lead->user_id) {
                \App\Models\Notification::create([
                    'organization_id' => $orgId,
                    'user_id' => $lead->user_id,
                    'title' => 'New Lead Assigned',
                    'body' => "You have been assigned a new lead: {$lead->name} from " . ($dto->company ?? 'Unknown Company') . ".",
                    'type' => 'lead_assignment',
                    'category' => 'crm',
                    'priority' => $priority,
                    'is_read' => false,
                    'data' => [
                        'lead_id' => $lead->id,
                        'score' => $lead->score,
                    ],
                    'version' => 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed scheduling follow-up automation: " . $e->getMessage());
        }
    }
}
