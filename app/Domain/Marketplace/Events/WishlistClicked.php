<?php

namespace App\Domain\Marketplace\Events;

class WishlistClicked extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        bool $added,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.wishlist.clicked',
            payload: [
                'product_slug' => $productSlug,
                'added' => $added,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
