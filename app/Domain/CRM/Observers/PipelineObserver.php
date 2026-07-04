<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Pipeline;
use App\Domain\CRM\Events\PipelineUpdatedEvent;
use App\Contracts\EventBus;
use App\Services\Cache\TenantCacheManagerInterface;

class PipelineObserver
{
    protected EventBus $eventBus;
    protected TenantCacheManagerInterface $cache;

    public function __construct(EventBus $eventBus, TenantCacheManagerInterface $cache)
    {
        $this->eventBus = $eventBus;
        $this->cache = $cache;
    }

    public function updated(Pipeline $pipeline): void
    {
        $this->eventBus->dispatch(new PipelineUpdatedEvent($pipeline));
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
