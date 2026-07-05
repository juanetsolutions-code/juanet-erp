<?php

namespace App\Domain\Marketplace\Events;

class ShareClicked extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        string $platform,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.share.clicked',
            payload: [
                'product_slug' => $productSlug,
                'platform' => $platform,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
