<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorPageView;

class CTAButtonClicked extends CrmDomainEvent
{
    public function __construct(
        VisitorPageView $pageView,
        array $ctaData,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.cta.clicked',
            eventType: 'queued',
            organizationId: $pageView->organization_id,
            aggregateType: 'VisitorPageView',
            aggregateId: (string) $pageView->id,
            aggregateVersion: 1,
            payload: [
                'id' => $pageView->id,
                'session_id' => $pageView->session_id,
                'visitor_id' => $pageView->visitor_id,
                'url' => $pageView->url,
                'cta_id' => $ctaData['cta_id'] ?? null,
                'label' => $ctaData['label'] ?? null,
                'clicked_at' => $ctaData['clicked_at'] ?? now()->toIso8601String(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
