<?php

namespace App\Domain\Marketplace\Events;

class NewsletterSubmitted extends MarketplaceDomainEvent
{
    public function __construct(
        string $email,
        ?string $visitorId,
        ?string $sessionId,
        ?string $organizationId = null,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.newsletter.submitted',
            payload: [
                'email' => $email,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ],
            organizationId: $organizationId
        );
    }
}
