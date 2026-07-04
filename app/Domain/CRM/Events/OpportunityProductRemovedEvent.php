<?php

namespace App\Domain\CRM\Events;

use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Models\OpportunityProduct;

class OpportunityProductRemovedEvent extends CrmDomainEvent
{
    public function __construct(Opportunity $opportunity, OpportunityProduct $product)
    {
        parent::__construct(
            'crm.opportunity.product.removed',
            'queued',
            [
                'id' => $opportunity->id,
                'organization_id' => $opportunity->organization_id,
                'opportunity_product_id' => $product->id,
                'product_name' => $product->product_name,
                'subtotal' => $product->subtotal,
            ],
            $opportunity->organization_id,
            'idemp_opportunity_prod_rem_' . $product->id . '_' . time()
        );
    }
}
