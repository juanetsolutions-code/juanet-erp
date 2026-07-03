<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Pipeline;

class PipelineUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Pipeline $pipeline)
    {
        parent::__construct(
            'crm.pipeline.updated',
            'queued',
            [
                'id' => $pipeline->id,
                'organization_id' => $pipeline->organization_id,
                'name' => $pipeline->name,
                'is_active' => $pipeline->is_active,
            ],
            $pipeline->organization_id,
            'idemp_pipeline_updated_' . $pipeline->id . '_' . now()->getTimestamp()
        );
    }
}
