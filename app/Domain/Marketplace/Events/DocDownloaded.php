<?php

namespace App\Domain\Marketplace\Events;

class DocDownloaded extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.download_docs.clicked',
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
