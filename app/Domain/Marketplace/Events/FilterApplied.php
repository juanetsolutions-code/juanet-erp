<?php

namespace App\Domain\Marketplace\Events;

class FilterApplied extends MarketplaceDomainEvent
{
    public function __construct(
        array $filters,
        int $resultsCount,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.filter.applied',
            payload: [
                'filters' => $filters,
                'results_count' => $resultsCount,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
