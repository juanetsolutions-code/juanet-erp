<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\VisitorPageView;

class PageViewed extends CrmDomainEvent
{
    public function __construct(
        VisitorPageView $pageView,
        ?string $actorId = null,
        array $metadata = [],
        ?string $correlationId = null,
        ?string $causationId = null
    ) {
        parent::__construct(
            eventName: 'crm.page.viewed',
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
                'route_name' => $pageView->route_name,
                'page_title' => $pageView->page_title,
                'timestamp' => $pageView->timestamp->toIso8601String(),
            ],
            actorId: $actorId,
            metadata: $metadata,
            correlationId: $correlationId,
            causationId: $causationId
        );
    }
}
