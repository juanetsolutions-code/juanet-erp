<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Events\CompanyCreated;
use App\Domain\CRM\Events\CompanyUpdated;
use App\Domain\CRM\Events\CompanyDeleted;
use App\Contracts\EventBus;
use App\Services\Cache\TenantCacheManagerInterface;

class CompanyObserver
{
    protected EventBus $eventBus;
    protected TenantCacheManagerInterface $cache;

    public function __construct(EventBus $eventBus, TenantCacheManagerInterface $cache)
    {
        $this->eventBus = $eventBus;
        $this->cache = $cache;
    }

    public function created(Company $company): void
    {
        $this->eventBus->dispatch(new CompanyCreated($company));
        $this->invalidateCache($company);
    }

    public function updated(Company $company): void
    {
        $this->eventBus->dispatch(new CompanyUpdated($company));

        if ($company->wasChanged('health_score')) {
            $oldScore = (int) $company->getOriginal('health_score');
            $newScore = (int) $company->health_score;
            if ($newScore < $oldScore) {
                $this->eventBus->dispatch(new \App\Domain\CRM\Events\CompanyHealthDeterioratedEvent($company, $oldScore, $newScore));
            }
        }

        $this->invalidateCache($company);
    }

    public function deleted(Company $company): void
    {
        $this->eventBus->dispatch(new CompanyDeleted($company));
        $this->invalidateCache($company);
    }

    protected function invalidateCache(Company $company): void
    {
        $orgId = $company->organization_id;
        if ($orgId) {
            $this->cache->tags(["companies_{$orgId}"])->flush();
        }
    }
}
