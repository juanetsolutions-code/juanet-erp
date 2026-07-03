<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Events\CompanyCreatedEvent;
use App\Services\EventBus\TransactionalOutboxInterface;
use App\Services\Cache\TenantCacheManagerInterface;

class CompanyObserver
{
    protected TransactionalOutboxInterface $outbox;
    protected TenantCacheManagerInterface $cache;

    public function __construct(TransactionalOutboxInterface $outbox, TenantCacheManagerInterface $cache)
    {
        $this->outbox = $outbox;
        $this->cache = $cache;
    }

    public function created(Company $company): void
    {
        $this->outbox->store(new CompanyCreatedEvent($company));
        $this->invalidateCache($company);
    }

    public function updated(Company $company): void
    {
        $this->invalidateCache($company);
    }

    public function deleted(Company $company): void
    {
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
