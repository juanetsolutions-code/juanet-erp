<?php

namespace App\Services\EventBus;

use App\Models\EventOutbox;
use App\Events\QueuedEvent;
use App\Events\WebhookEvent;
use App\Events\InternalEvent;
use App\Events\ScheduledEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class OutboxConsumer implements OutboxConsumerInterface
{
    protected IdempotencyCheckerInterface $idempotency;

    public function __construct(IdempotencyCheckerInterface $idempotency)
    {
        $this->idempotency = $idempotency;
    }

    /**
     * Consume/process an Outbox item.
     */
    public function consume(EventOutbox $outbox): void
    {
        $key = $outbox->idempotency_key;

        // 1. Enforce Idempotency if key is present
        if ($key) {
            if ($this->idempotency->isDuplicate($key)) {
                Log::info("Idempotency bypass: Event already processed successfully.", ['key' => $key]);
                return;
            }

            $claimed = $this->idempotency->claimProcessing($key);
            if (!$claimed) {
                throw new Exception("Conflict: Event with key {$key} is already being processed.");
            }
        }

        try {
            // 2. Route by event type
            if ($outbox->event_type === 'webhook') {
                $this->fireWebhook($outbox);
            } else {
                $this->fireLocalEvent($outbox);
            }

            // 3. Mark completed in Idempotency Checker
            if ($key) {
                $this->idempotency->markCompleted($key, ['processed_at' => now()->toIso8601String()]);
            }
        } catch (Throwable $e) {
            // 4. Mark failed in Idempotency Checker so it can be retried
            if ($key) {
                $this->idempotency->markFailed($key);
            }
            throw $e;
        }
    }

    /**
     * Executes a secure Webhook call to an external endpoint.
     */
    protected function fireWebhook(EventOutbox $outbox): void
    {
        if (empty($outbox->webhook_url)) {
            throw new Exception("Webhook URL is missing on outbox event {$outbox->id}.");
        }

        $secret = config('app.webhook_secret', 'juanet_enterprise_secret_key');
        $payloadJson = json_encode($outbox->payload);
        $signature = hash_hmac('sha256', $payloadJson, $secret);

        Log::info("Sending Webhook event out", [
            'url' => $outbox->webhook_url,
            'event' => $outbox->event_name
        ]);

        $response = Http::withHeaders([
            'X-Juanet-Event' => $outbox->event_name,
            'X-Juanet-Signature' => $signature,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(15)->post($outbox->webhook_url, $outbox->payload);

        if (!$response->successful()) {
            throw new Exception("Webhook target responded with non-2xx status: " . $response->status());
        }
    }

    /**
     * Resolves and dispatches the native PHP/Laravel event to local listeners.
     */
    protected function fireLocalEvent(EventOutbox $outbox): void
    {
        $eventClass = $outbox->event_name;
        $eventInstance = null;

        // Try to reconstruct the original event class if it exists
        if (class_exists($eventClass)) {
            try {
                // If the constructor expects parameters, pass them
                $eventInstance = new $eventClass(...array_values($outbox->payload));
            } catch (Throwable $e) {
                try {
                    $eventInstance = new $eventClass($outbox->payload);
                } catch (Throwable $e2) {
                    // Fallback to manual allocation
                }
            }
        }

        // Fallback to generic DomainEvent wraps if the class doesn't exist
        if (!$eventInstance) {
            $eventInstance = match ($outbox->event_type) {
                'internal' => new InternalEvent($outbox->event_name, $outbox->payload, $outbox->organization_id, $outbox->idempotency_key),
                'scheduled' => new ScheduledEvent($outbox->event_name, $outbox->scheduled_at ?? now(), $outbox->payload, $outbox->organization_id, $outbox->idempotency_key),
                default => new QueuedEvent($outbox->event_name, $outbox->payload, $outbox->organization_id, $outbox->idempotency_key),
            };
        }

        // Fire standard Laravel event listeners
        \Illuminate\Support\Facades\Event::dispatch($eventInstance);
    }
}
