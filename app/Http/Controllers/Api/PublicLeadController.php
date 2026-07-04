<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicLeadRequest;
use App\Services\Crm\LeadCaptureService;
use App\Services\DTO\PublicLeadDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PublicLeadController extends Controller
{
    protected LeadCaptureService $leadCaptureService;

    public function __construct(LeadCaptureService $leadCaptureService)
    {
        $this->leadCaptureService = $leadCaptureService;
    }

    /**
     * Store a public lead submission.
     */
    public function store(PublicLeadRequest $request): JsonResponse
    {
        try {
            // Create the DTO from validated request data
            $dto = PublicLeadDto::fromRequest($request);

            // Capture and persist lead via our specialized service
            $lead = $this->leadCaptureService->capture($dto);

            Log::info('Public lead successfully captured', [
                'lead_id' => $lead->id,
                'email' => $lead->email,
                'source' => $dto->source
            ]);

            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
                'message' => 'Your inquiry has been successfully received. Our engineering team in Nairobi will reach out within 2 hours.',
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Error capturing public lead', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while processing your request. Please try again later.',
            ], 500);
        }
    }
}
