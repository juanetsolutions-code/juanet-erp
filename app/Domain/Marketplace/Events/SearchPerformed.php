<?php

namespace App\Domain\Marketplace\Events;

class SearchPerformed extends MarketplaceDomainEvent
{
    public function __construct(
        string $searchQuery,
        int $resultsCount,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.search.performed',
            payload: [
                'search_query' => $searchQuery,
                'results_count' => $resultsCount,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
