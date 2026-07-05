<?php

namespace App\Domain\Marketplace\Events;

class DemoLaunched extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        string $demoType, // e.g., admin, customer, generic
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.demo.launched',
            payload: [
                'product_slug' => $productSlug,
                'demo_type' => $demoType,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
