<?php

namespace App\Domain\Marketplace\Events;

class CouponApplied extends MarketplaceDomainEvent
{
    public function __construct(
        int $cartId,
        string $couponCode,
        ?string $visitorId,
        ?string $sessionId,
        array $metadata = []
    ) {
        parent::__construct(
            eventName: 'marketplace.cart.coupon_applied',
            payload: [
                'cart_id' => $cartId,
                'coupon_code' => $couponCode,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]
        );
    }
}
