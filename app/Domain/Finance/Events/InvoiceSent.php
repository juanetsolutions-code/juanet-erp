<?php

namespace App\Domain\Finance\Events;

class InvoiceSent extends FinanceDomainEvent
{
    public function __construct(array $invoiceData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'finance.invoice.sent',
            payload: $invoiceData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
