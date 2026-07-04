<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;

class OpportunityForecastUpdatedEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity)
    {
        parent::__construct(
            'crm.opportunity.forecast.updated',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'name' => $opportunity->name,
                'forecast_category' => $opportunity->forecast_category,
                'estimated_revenue' => $opportunity->estimated_revenue,
                'weighted_revenue' => $opportunity->weighted_revenue,
                'win_probability' => $opportunity->win_probability,
            ],
            $opportunity->organization_id,
            'idemp_opportunity_forecast_' . $opportunity->id . '_' . time()
        );
    }
}
