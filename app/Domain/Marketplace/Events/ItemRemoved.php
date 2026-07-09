<?php

namespace App\Domain\Marketplace\Events;

class ItemRemoved extends MarketplaceDomainEvent
{
    public function __construct(
        int $cartId,
        string $productId,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cart.item_removed',
            payload: [
                'cart_id' => $cartId,
                'product_id' => $productId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
