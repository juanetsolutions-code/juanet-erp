<?php

namespace App\Domain\Marketplace\Services;

use App\Contracts\EventBus;
use App\Domain\Marketplace\Events\NewsletterSubmitted;
use Illuminate\Support\Facades\Log;

class MarketplaceNewsletterService
{
    protected EventBus $eventBus;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    public function subscribe(string $email, ?string $visitorId = null, ?string $sessionId = null): bool
    {
        // 1. Log the subscription
        Log::info('Marketplace newsletter subscription recorded.', [
            'email' => $email,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);

        // 2. Dispatch domain event to EventBus
        try {
            $event = new NewsletterSubmitted($email, $visitorId, $sessionId);
            $this->eventBus->dispatch($event);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch Marketplace NewsletterSubmitted event.', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
        }

        return true;
    }
}
