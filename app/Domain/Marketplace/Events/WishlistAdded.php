<?php

namespace App\Domain\Marketplace\Events;

class WishlistAdded extends MarketplaceDomainEvent
{
    public function __construct(
        string $productId,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.wishlist.added',
            payload: [
                'product_id' => $productId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
