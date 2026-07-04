<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Events\LeadCreated;
use App\Domain\CRM\Events\LeadUpdated;
use App\Domain\CRM\Events\LeadDeleted;
use App\Contracts\EventBus;
use App\Services\Cache\TenantCacheManagerInterface;

class LeadObserver
{
    protected EventBus $eventBus;
    protected TenantCacheManagerInterface $cache;

    public function __construct(EventBus $eventBus, TenantCacheManagerInterface $cache)
    {
        $this->eventBus = $eventBus;
        $this->cache = $cache;
    }

    public function created(Lead $lead): void
    {
        $this->eventBus->dispatch(new LeadCreated($lead));
        $this->invalidateCache($lead);
    }

    public function updated(Lead $lead): void
    {
        // Capture changes on the lead
        $dirty = $lead->getChanges();
        $this->eventBus->dispatch(new LeadUpdated($lead, $dirty));
        $this->invalidateCache($lead);
    }

    public function deleted(Lead $lead): void
    {
        $this->eventBus->dispatch(new LeadDeleted($lead));
        $this->invalidateCache($lead);
    }

    protected function invalidateCache(Lead $lead): void
    {
        $orgId = $lead->organization_id;
        if ($orgId) {
            $this->cache->tags(["leads_{$orgId}"])->flush();
        }
    }
}
