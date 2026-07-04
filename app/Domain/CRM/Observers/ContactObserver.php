<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Events\ContactCreated;
use App\Domain\CRM\Events\ContactUpdated;
use App\Domain\CRM\Events\ContactDeleted;
use App\Contracts\EventBus;
use App\Services\Cache\TenantCacheManagerInterface;

class ContactObserver
{
    protected EventBus $eventBus;
    protected TenantCacheManagerInterface $cache;

    public function __construct(EventBus $eventBus, TenantCacheManagerInterface $cache)
    {
        $this->eventBus = $eventBus;
        $this->cache = $cache;
    }

    public function created(Contact $contact): void
    {
        $this->eventBus->dispatch(new ContactCreated($contact));
        $this->invalidateCache($contact);
    }

    public function updated(Contact $contact): void
    {
        // Capture changed attributes for the updated event
        $dirty = $contact->getChanges();
        $this->eventBus->dispatch(new ContactUpdated($contact, $dirty));
        $this->invalidateCache($contact);
    }

    public function deleted(Contact $contact): void
    {
        $this->eventBus->dispatch(new ContactDeleted($contact));
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
