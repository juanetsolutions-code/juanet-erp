<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Events\OpportunityCreatedEvent;
use App\Services\EventBus\TransactionalOutboxInterface;
use App\Services\Cache\TenantCacheManagerInterface;

class OpportunityObserver
{
    protected TransactionalOutboxInterface $outbox;
    protected TenantCacheManagerInterface $cache;

    public function __construct(TransactionalOutboxInterface $outbox, TenantCacheManagerInterface $cache)
    {
        $this->outbox = $outbox;
        $this->cache = $cache;
    }

    public function created(Opportunity $opportunity): void
    {
        $this->outbox->store(new OpportunityCreatedEvent($opportunity));
        $this->invalidateCache($opportunity);
    }

    public function updated(Opportunity $opportunity): void
    {
        $this->invalidateCache($opportunity);
    }

    public function deleted(Opportunity $opportunity): void
    {
        $this->invalidateCache($opportunity);
    }

    protected function invalidateCache(Opportunity $opportunity): void
    {
        $orgId = $opportunity->organization_id;
        if ($orgId) {
            $this->cache->tags(["opportunities_{$orgId}"])->flush();
        }
    }
}
