<?php

namespace App\Domain\Marketplace\Events;

class QuantityUpdated extends MarketplaceDomainEvent
{
    public function __construct(
        int $cartId,
        string $productId,
        int $newQuantity,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cart.quantity_updated',
            payload: [
                'cart_id' => $cartId,
                'product_id' => $productId,
                'new_quantity' => $newQuantity,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
