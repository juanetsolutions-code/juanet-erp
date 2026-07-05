<?php

namespace App\Domain\Marketplace\Events;

class GalleryViewed extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.gallery.viewed',
            payload: [
                'product_slug' => $productSlug,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
