<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Pipeline;
use App\Domain\CRM\Events\PipelineUpdatedEvent;
use App\Services\EventBus\TransactionalOutboxInterface;
use App\Services\Cache\TenantCacheManagerInterface;

class PipelineObserver
{
    protected TransactionalOutboxInterface $outbox;
    protected TenantCacheManagerInterface $cache;

    public function __construct(TransactionalOutboxInterface $outbox, TenantCacheManagerInterface $cache)
    {
        $this->outbox = $outbox;
        $this->cache = $cache;
    }

    public function updated(Pipeline $pipeline): void
    {
        $this->outbox->store(new PipelineUpdatedEvent($pipeline));
        $this->invalidateCache($pipeline);
    }

    public function created(Pipeline $pipeline): void
    {
        $this->invalidateCache($pipeline);
    }

    public function deleted(Pipeline $pipeline): void
    {
        $this->invalidateCache($pipeline);
    }

    protected function invalidateCache(Pipeline $pipeline): void
    {
        $orgId = $pipeline->organization_id;
        if ($orgId) {
            $this->cache->tags(["pipelines_{$orgId}"])->flush();
        }
    }
}
