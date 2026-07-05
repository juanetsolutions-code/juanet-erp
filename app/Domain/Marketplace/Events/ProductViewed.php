<?php

namespace App\Domain\Marketplace\Events;

class ProductViewed extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        string $productId,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.product.viewed',
            payload: [
                'product_slug' => $productSlug,
                'product_id' => $productId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
