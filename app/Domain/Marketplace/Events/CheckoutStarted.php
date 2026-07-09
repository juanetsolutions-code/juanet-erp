<?php

namespace App\Domain\Marketplace\Events;

class CheckoutStarted extends MarketplaceDomainEvent
{
    public function __construct(
        int $cartId,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.checkout.started',
            payload: [
                'cart_id' => $cartId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
