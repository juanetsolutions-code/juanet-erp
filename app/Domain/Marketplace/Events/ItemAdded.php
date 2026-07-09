<?php

namespace App\Domain\Marketplace\Events;

class ItemAdded extends MarketplaceDomainEvent
{
    public function __construct(
        int $cartId,
        string $productId,
        string $licenseType,
        int $quantity,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cart.item_added',
            payload: [
                'cart_id' => $cartId,
                'product_id' => $productId,
                'license_type' => $licenseType,
                'quantity' => $quantity,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
