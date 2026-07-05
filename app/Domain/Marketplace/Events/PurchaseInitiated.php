<?php

namespace App\Domain\Marketplace\Events;

class PurchaseInitiated extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        string $licenseType,
        int $quantity,
        int $price,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.purchase.initiated',
            payload: [
                'product_slug' => $productSlug,
                'license_type' => $licenseType,
                'quantity' => $quantity,
                'price' => $price,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
