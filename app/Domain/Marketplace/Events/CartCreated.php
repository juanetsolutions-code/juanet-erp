<?php

namespace App\Domain\Marketplace\Events;

class CartCreated extends MarketplaceDomainEvent
{
    public function __construct(
        int $cartId,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cart.created',
            payload: [
                'cart_id' => $cartId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
