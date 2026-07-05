<?php

namespace App\Domain\Marketplace\Events;

class CTAClicked extends MarketplaceDomainEvent
{
    public function __construct(
        string $ctaName,
        ?string $productId,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cta.clicked',
            payload: [
                'cta_name' => $ctaName,
                'product_id' => $productId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
