<?php

namespace App\Domain\Finance\Events;

class RecurringInvoiceGenerated extends FinanceDomainEvent
{
    public function __construct(array $recurringData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'finance.recurring.generated',
            payload: $recurringData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
