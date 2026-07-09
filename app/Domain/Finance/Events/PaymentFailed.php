<?php

namespace App\Domain\Finance\Events;

class PaymentFailed extends FinanceDomainEvent
{
    public function __construct(array $paymentData, ?string $organizationId = null, ?string $actorId = null)
    {
        parent::__construct(
            eventName: 'finance.payment.failed',
            payload: $paymentData,
            organizationId: $organizationId,
            actorId: $actorId
        );
    }
}
