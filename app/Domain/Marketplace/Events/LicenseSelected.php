<?php

namespace App\Domain\Marketplace\Events;

class LicenseSelected extends MarketplaceDomainEvent
{
    public function __construct(
        string $productSlug,
        string $licenseType,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.license.selected',
            payload: [
                'product_slug' => $productSlug,
                'license_type' => $licenseType,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
