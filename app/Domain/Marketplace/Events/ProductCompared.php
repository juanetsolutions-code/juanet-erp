<?php

namespace App\Domain\Marketplace\Events;

class ProductCompared extends MarketplaceDomainEvent
{
    public function __construct(
        array $productIds,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.product.compared',
            payload: [
                'product_ids' => $productIds,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
