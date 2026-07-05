<?php

namespace App\Domain\Marketplace\Events;

class MarketplaceOpened extends MarketplaceDomainEvent
{
    public function __construct(
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.opened',
            payload: [
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'url' => request()->fullUrl(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
