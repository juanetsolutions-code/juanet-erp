<?php

namespace App\Domain\Marketplace\Events;

class CartMerged extends MarketplaceDomainEvent
{
    public function __construct(
        int $fromCartId,
        int $toCartId,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cart.merged',
            payload: [
                'from_cart_id' => $fromCartId,
                'to_cart_id' => $toCartId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
