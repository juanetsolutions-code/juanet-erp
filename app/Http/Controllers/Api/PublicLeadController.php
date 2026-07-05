<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicLeadRequest;
use App\Services\Crm\LeadCaptureService;
use App\Services\Crm\LeadSpamFilter;
use App\Services\DTO\PublicLeadDto;
use App\Contracts\EventBus;
use App\Services\TenantContext;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublicLeadController extends Controller
{
    protected LeadCaptureService $leadCaptureService;
    protected LeadSpamFilter $spamFilter;
    protected EventBus $eventBus;
    protected TenantContext $tenantContext;

    public function __construct(
        LeadCaptureService $leadCaptureService,
        LeadSpamFilter $spamFilter,
        EventBus $eventBus,
        TenantContext $tenantContext
    ) {
        $this->leadCaptureService = $leadCaptureService;
        $this->spamFilter = $spamFilter;
        $this->eventBus = $eventBus;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Store a public lead submission.
     */
    public function store(PublicLeadRequest $request): JsonResponse
    {
        // 1. Establish Correlation ID for Request tracing (Observability)
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        // 2. Resolve Active Tenant / Organization context
        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            $tenant = Organization::where('slug', 'juanet-hq')->first() ?? Organization::first();
            if ($tenant) {
                $this->tenantContext->setTenant($tenant);
            }
        }
        $tenantId = $tenant ? $tenant->id : null;

        try {
            // 3. Apply Multi-Layer Anti-Spam Protections
            $spamAnalysis = $this->spamFilter->analyze($request);

            if ($spamAnalysis['is_spam']) {
                // Structured warning log for spam attempt (Observability)
                Log::warning('Public lead submission rejected as SPAM', [
                    'correlation_id' => $correlationId,
                    'tenant_id' => $tenantId,
                    'email' => $request->input('email'),
                    'name' => $request->input('name'),
                    'spam_score' => $spamAnalysis['score'],
                    'reasons' => $spamAnalysis['reasons'],
                    'timestamp' => now()->toIso8601String(),
                ]);

                // Publish LeadRejected event to Transactional Outbox
                $this->eventBus->dispatch(new \App\Domain\CRM\Events\LeadRejected(
                    email: $request->input('email') ?? 'unknown@example.com',
                    name: $request->input('name') ?? 'Unknown Submitter',
                    reason: implode(', ', $spamAnalysis['reasons']),
                    spamScore: $spamAnalysis['score'],
                    organizationId: $tenantId,
                    correlationId: $correlationId
                ));

                return response()->json([
                    'success' => false,
                    'error' => 'Inquiry verification failed. Obvious bot or spam detected.',
                    'reasons' => $spamAnalysis['reasons'],
                ], 422);
            }

            // 4. Create the DTO from request
            $dto = PublicLeadDto::fromRequest($request);

            // 5. Capture & persist lead (or update existing if duplicate)
            $lead = $this->leadCaptureService->capture($dto, $correlationId);

            // 5b. Associate Anonymous Visitor history (if present)
            $visitorId = $request->input('visitor_id') ?? $request->cookie('juanet_visitor_id') ?? $request->input('session_id') ?? $request->cookie('juanet_session_id');
            // If visitorId is actually a session ID, we can resolve visitor from it, but let's check both
            if ($visitorId) {
                try {
                    // Try to find if visitorId matches a visitor or if we can resolve it
                    $visitor = \App\Domain\CRM\Models\Visitor::find($visitorId);
                    if (!$visitor) {
                        // Check if it is a session ID
                        $session = \App\Domain\CRM\Models\VisitorSession::find($visitorId);
                        if ($session) {
                            $visitorId = $session->visitor_id;
                        } else {
                            $visitorId = null;
                        }
                    }
                    if ($visitorId) {
                        app(\App\Services\Crm\VisitorTrackerService::class)->associateLead($visitorId, $lead, $correlationId);
                    }
                } catch (\Throwable $ex) {
                    Log::warning('Failed to associate visitor history with public lead', [
                        'correlation_id' => $correlationId,
                        'error' => $ex->getMessage(),
                    ]);
                }
            }

            // Structured success log (Observability)
            Log::info('Public lead successfully processed', [
                'correlation_id' => $correlationId,
                'tenant_id' => $tenantId,
                'lead_uuid' => $lead->id,
                'email' => $lead->email,
                'source' => $dto->source,
                'is_duplicate' => $lead->duplicate_status === 'potential',
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
                'message' => 'Your inquiry has been successfully received. Our engineering team in Nairobi will reach out within 2 hours.',
            ], 201);

        } catch (\Throwable $e) {
            // Structured failure log (Observability)
            Log::error('Error capturing public lead', [
                'correlation_id' => $correlationId,
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your request. Please try again later.',
            ], 500);
        }
    }
}
