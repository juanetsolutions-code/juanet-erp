<?php

namespace App\Domain\Marketplace\Events;

class CategoryViewed extends MarketplaceDomainEvent
{
    public function __construct(
        string $categorySlug,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.category.viewed',
            payload: [
                'category_slug' => $categorySlug,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
