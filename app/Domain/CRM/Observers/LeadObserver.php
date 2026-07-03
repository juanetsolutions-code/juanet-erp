<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Events\LeadCreatedEvent;
use App\Domain\CRM\Events\LeadUpdatedEvent;
use App\Domain\CRM\Events\LeadDeletedEvent;
use App\Services\EventBus\TransactionalOutboxInterface;
use App\Services\Cache\TenantCacheManagerInterface;

class LeadObserver
{
    protected TransactionalOutboxInterface $outbox;
    protected TenantCacheManagerInterface $cache;

    public function __construct(TransactionalOutboxInterface $outbox, TenantCacheManagerInterface $cache)
    {
        $this->outbox = $outbox;
        $this->cache = $cache;
    }

    public function created(Lead $lead): void
    {
        $this->outbox->store(new LeadCreatedEvent($lead));
        $this->invalidateCache($lead);
    }

    public function updated(Lead $lead): void
    {
        $this->outbox->store(new LeadUpdatedEvent($lead));
        $this->invalidateCache($lead);
    }

    public function deleted(Lead $lead): void
    {
        $this->outbox->store(new LeadDeletedEvent($lead));
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
