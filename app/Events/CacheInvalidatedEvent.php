<?php

namespace App\Events;

class CacheInvalidatedEvent
{
    public string $tagOrKey;
    public ?string $tenantId;
    public string $type; // e.g. "dashboard", "permissions", "search", "organization", "config", "feature"

    /**
     * Create a new event instance.
     */
    public function __construct(string $tagOrKey, ?string $tenantId = null, string $type = 'general')
    {
        $this->tagOrKey = $tagOrKey;
        $this->tenantId = $tenantId;
        $this->type = $type;
    }
}
