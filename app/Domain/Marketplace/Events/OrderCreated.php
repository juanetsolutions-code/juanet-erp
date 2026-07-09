<?php

namespace App\Domain\Marketplace\Events;

class OrderCreated extends MarketplaceDomainEvent
{
    public function __construct(int $orderId, ?string $organizationId = null)
    {
        parent::__construct(
            eventName: 'marketplace.order.created',
            payload: [
                'order_id' => $orderId,
            ],
            organizationId: $organizationId
        );
    }
}
