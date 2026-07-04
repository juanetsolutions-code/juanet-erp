<?php

namespace App\Domain\CRM\Observers;

use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Events\OpportunityCreated;
use App\Domain\CRM\Events\OpportunityUpdated;
use App\Domain\CRM\Events\OpportunityStageChanged;
use App\Domain\CRM\Events\OpportunityClosedWon;
use App\Domain\CRM\Events\OpportunityClosedLost;
use App\Domain\CRM\Events\OpportunityForecastUpdated;
use App\Domain\CRM\Activities\Models\Activity;
use App\Contracts\EventBus;
use App\Services\Cache\TenantCacheManagerInterface;

class OpportunityObserver
{
    protected EventBus $eventBus;
    protected TenantCacheManagerInterface $cache;

    public function __construct(EventBus $eventBus, TenantCacheManagerInterface $cache)
    {
        $this->eventBus = $eventBus;
        $this->cache = $cache;
    }

    public function creating(Opportunity $opportunity): void
    {
        if (empty($opportunity->opportunity_number)) {
            $opportunity->opportunity_number = 'OPP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        }

        if (empty($opportunity->currency)) {
            $opportunity->currency = 'USD';
        }
    }

    public function created(Opportunity $opportunity): void
    {
        $this->eventBus->dispatch(new OpportunityCreated($opportunity));
        
        // Log activity
        Activity::create([
            'organization_id' => $opportunity->organization_id,
            'loggable_type' => Opportunity::class,
            'loggable_id' => $opportunity->id,
            'user_id' => $opportunity->user_id,
            'type' => 'system_event',
            'subject' => 'Opportunity Created',
            'description' => "Opportunity #{$opportunity->opportunity_number} '{$opportunity->name}' was created.",
            'properties' => [
                'amount' => $opportunity->amount,
                'forecast_category' => $opportunity->forecast_category,
            ]
        ]);

        $this->invalidateCache($opportunity);
    }

    public function updating(Opportunity $opportunity): void
    {
        // Automatically calculate weighted revenue on save
        $opportunity->weighted_revenue = $opportunity->amount * ($opportunity->win_probability / 100.0);
    }

    public function updated(Opportunity $opportunity): void
    {
        $dirty = $opportunity->getDirty();

        $this->eventBus->dispatch(new OpportunityUpdated($opportunity, $dirty));

        // Check if stage is updated
        if (isset($dirty['pipeline_stage_id'])) {
            $originalStageId = $opportunity->getOriginal('pipeline_stage_id');
            $this->eventBus->dispatch(new OpportunityStageChanged($opportunity, $opportunity->pipeline_stage_id, $originalStageId));
        }

        // Check forecast category changes
        if (isset($dirty['forecast_category'])) {
            $this->eventBus->dispatch(new OpportunityForecastUpdated($opportunity));
        }

        // Check if won/lost
        if (isset($dirty['status'])) {
            if ($opportunity->status === 'won') {
                $this->eventBus->dispatch(new OpportunityClosedWon($opportunity));
            } elseif ($opportunity->status === 'lost') {
                $this->eventBus->dispatch(new OpportunityClosedLost($opportunity));
            }
        }

        // Log updated activity
        Activity::create([
            'organization_id' => $opportunity->organization_id,
            'loggable_type' => Opportunity::class,
            'loggable_id' => $opportunity->id,
            'user_id' => auth()->id() ?? $opportunity->user_id,
            'type' => 'system_event',
            'subject' => 'Opportunity Updated',
            'description' => "Opportunity '{$opportunity->name}' has been updated.",
            'properties' => [
                'changes' => $dirty
            ]
        ]);

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

