<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Events\ContactCreatedEvent;
use App\Services\EventBus\TransactionalOutboxInterface;
use App\Services\Cache\TenantCacheManagerInterface;

class ContactObserver
{
    protected TransactionalOutboxInterface $outbox;
    protected TenantCacheManagerInterface $cache;

    public function __construct(TransactionalOutboxInterface $outbox, TenantCacheManagerInterface $cache)
    {
        $this->outbox = $outbox;
        $this->cache = $cache;
    }

    public function created(Contact $contact): void
    {
        $this->outbox->store(new ContactCreatedEvent($contact));
        $this->invalidateCache($contact);
    }

    public function updated(Contact $contact): void
    {
        $this->invalidateCache($contact);
    }

    public function deleted(Contact $contact): void
    {
        $this->invalidateCache($contact);
    }

    protected function invalidateCache(Contact $contact): void
    {
        $orgId = $contact->organization_id;
        if ($orgId) {
            $this->cache->tags(["contacts_{$orgId}"])->flush();
        }
    }
}
