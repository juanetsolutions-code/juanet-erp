<?php

namespace App\Events\Interfaces;

use DateTimeInterface;

interface DomainEventInterface
{
    /**
     * Get the descriptive dot-notation name of the event.
     * e.g., "crm.lead.created", "finance.invoice.paid"
     */
    public function getEventName(): string;

    /**
     * Get the processing mode/type of the event.
     * Allowed: "immediate", "queued", "scheduled", "webhook", "internal"
     */
    public function getEventType(): string;

    /**
     * Get the serializeable payload of the event.
     */
    public function getPayload(): array;

    /**
     * Get the associated organization (tenant) ID if applicable.
     */
    public function getOrganizationId(): ?string;

    /**
     * Get the optional idempotency key of this event.
     */
    public function getIdempotencyKey(): ?string;

    /**
     * Get the target execution timestamp if the event is scheduled.
     */
    public function getScheduledAt(): ?DateTimeInterface;

    /**
     * Get the webhook endpoint URL if this is a webhook event.
     */
    public function getWebhookUrl(): ?string;
}
