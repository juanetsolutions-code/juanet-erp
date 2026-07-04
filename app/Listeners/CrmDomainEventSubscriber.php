<?php

namespace App\Listeners;

use App\Events\Interfaces\DomainEventInterface;
use App\Events\QueuedEvent;
use App\Services\NotificationServiceInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class CrmDomainEventSubscriber
{
    protected NotificationServiceInterface $notifications;

    public function __construct(NotificationServiceInterface $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Route standard DomainEventInterface events (both class and string name).
     */
    public function handleEvent(DomainEventInterface $event): void
    {
        $eventName = $event->getEventName();
        
        Log::info("CRM Subscriber handling event: {$eventName}", [
            'org_id' => $event->getOrganizationId(),
            'idemp_key' => $event->getIdempotencyKey()
        ]);

        try {
            switch ($eventName) {
                case 'crm.lead.assigned':
                    $this->handleLeadAssigned($event);
                    break;
                case 'crm.contact.updated':
                    $this->handleContactUpdated($event);
                    break;
                case 'crm.opportunity.created':
                    $this->handleOpportunityCreated($event);
                    break;
                case 'crm.opportunity.stage_changed':
                    $this->handleOpportunityStageChanged($event);
                    break;
                case 'crm.opportunity.forecast_updated':
                    $this->handleOpportunityForecastUpdated($event);
                    break;
                case 'crm.opportunity.closed_won':
                    $this->handleOpportunityClosedWon($event);
                    break;
                case 'crm.opportunity.closed_lost':
                    $this->handleOpportunityClosedLost($event);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error("CRM Subscriber failed to handle event {$eventName}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle QueuedEvent wrapper specifically.
     */
    public function handleQueuedEvent(QueuedEvent $event): void
    {
        $this->handleEvent($event);
    }

    /**
     * Handle Lead Assigned Event.
     */
    protected function handleLeadAssigned(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $orgId = $event->getOrganizationId();

        $leadId = $payload['id'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $previousUserId = $payload['previous_user_id'] ?? null;
        $leadName = $payload['name'] ?? 'Lead';

        if ($userId) {
            $this->notifications->send(
                $userId,
                'New Lead Assigned',
                "You have been assigned the lead: {$leadName}.",
                'info',
                'crm',
                'normal',
                $orgId,
                ['lead_id' => $leadId]
            );
        }

        if ($previousUserId) {
            $this->notifications->send(
                $previousUserId,
                'Lead Reassigned',
                "Lead '{$leadName}' has been reassigned to another owner.",
                'info',
                'crm',
                'normal',
                $orgId,
                ['lead_id' => $leadId]
            );
        }
    }

    /**
     * Handle Contact Updated Event.
     */
    protected function handleContactUpdated(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $contactId = $payload['id'] ?? null;
        
        if (!$contactId) {
            return;
        }

        $contact = \App\Domain\CRM\Models\Contact::find($contactId);
        if (!$contact || !$contact->user_id) {
            return;
        }

        $isVip = in_array(strtolower($contact->decision_maker_level ?? ''), ['c-level', 'vp', 'director']) ||
                 strtolower($contact->buying_influence ?? '') === 'decision maker';

        $dirty = $payload['dirty'] ?? [];

        // VIP Contact Updated
        if ($isVip && (isset($dirty['decision_maker_level']) || isset($dirty['buying_influence']) || isset($dirty['first_name']) || isset($dirty['last_name']))) {
            $this->notifications->send(
                $contact->user_id,
                "VIP Contact Updated: {$contact->full_name}",
                "The VIP contact {$contact->full_name} ({$contact->job_title}) has been updated.",
                'info',
                'crm',
                'high',
                $contact->organization_id
            );
        }

        // Birthday Today
        if ($contact->birthday && $contact->birthday->isToday() && isset($dirty['birthday'])) {
            $this->notifications->send(
                $contact->user_id,
                "Birthday Today: {$contact->full_name}",
                "Today is {$contact->full_name}'s birthday! Reach out to them to send your wishes.",
                'info',
                'crm',
                'normal',
                $contact->organization_id
            );
        }

        // Critical Health Status Overdue alert
        if ($contact->health_status === 'Critical' && isset($dirty['health_status'])) {
            $this->notifications->send(
                $contact->user_id,
                "Communication Overdue: {$contact->full_name}",
                "Contact {$contact->full_name}'s health score has dropped to {$contact->health_score} ({$contact->health_status}). Active engagement is overdue.",
                'warning',
                'crm',
                'high',
                $contact->organization_id
            );
        }
    }

    /**
     * Handle Opportunity Created Event.
     */
    protected function handleOpportunityCreated(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $amount = $payload['amount'] ?? 0;
        $userId = $payload['user_id'] ?? null;

        if ($amount >= 100000 && $userId) {
            $opportunityName = $payload['name'] ?? 'Opportunity';
            $this->notifications->send(
                $userId,
                "🔥 High-Value Deal Alert",
                "High-value Opportunity '{$opportunityName}' has been created with a value of " . number_format($amount, 2),
                'high_value_deal',
                'crm',
                'high',
                $event->getOrganizationId(),
                ['opportunity_id' => $payload['id'] ?? null]
            );
        }
    }

    /**
     * Handle Opportunity Stage Changed Event.
     */
    protected function handleOpportunityStageChanged(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $oppId = $payload['id'] ?? null;

        if (!$oppId) {
            return;
        }

        $opportunity = \App\Domain\CRM\Models\Opportunity::find($oppId);
        if (!$opportunity || !$opportunity->user_id) {
            return;
        }

        $oldStageName = $payload['old_stage_id'] ? (\App\Domain\CRM\Models\PipelineStage::find($payload['old_stage_id'])->name ?? 'Previous') : 'None';
        $newStageName = $payload['new_stage_id'] ? (\App\Domain\CRM\Models\PipelineStage::find($payload['new_stage_id'])->name ?? 'New') : 'None';

        $this->notifications->send(
            $opportunity->user_id,
            "Opportunity Stage Updated",
            "Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) has moved from '{$oldStageName}' to '{$newStageName}'.",
            'stage_change',
            'crm',
            'info',
            $opportunity->organization_id,
            ['opportunity_id' => $opportunity->id]
        );
    }

    /**
     * Handle Opportunity Forecast Updated Event.
     */
    protected function handleOpportunityForecastUpdated(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $oppId = $payload['id'] ?? null;

        if (!$oppId) {
            return;
        }

        $opportunity = \App\Domain\CRM\Models\Opportunity::find($oppId);
        if (!$opportunity || !$opportunity->user_id) {
            return;
        }

        $this->notifications->send(
            $opportunity->user_id,
            "Forecast Category Updated",
            "Opportunity #{$opportunity->opportunity_number} forecast category changed to '{$opportunity->forecast_category}'.",
            'forecast_change',
            'crm',
            'info',
            $opportunity->organization_id,
            ['opportunity_id' => $opportunity->id]
        );
    }

    /**
     * Handle Opportunity Closed Won Event.
     */
    protected function handleOpportunityClosedWon(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $oppId = $payload['id'] ?? null;

        if (!$oppId) {
            return;
        }

        $opportunity = \App\Domain\CRM\Models\Opportunity::find($oppId);
        if (!$opportunity || !$opportunity->user_id) {
            return;
        }

        $this->notifications->send(
            $opportunity->user_id,
            "🎉 Deal Won!",
            "Congratulations! Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) has been Closed Won for " . number_format($opportunity->amount, 2),
            'deal_won',
            'crm',
            'high',
            $opportunity->organization_id,
            ['opportunity_id' => $opportunity->id]
        );
    }

    /**
     * Handle Opportunity Closed Lost Event.
     */
    protected function handleOpportunityClosedLost(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $oppId = $payload['id'] ?? null;

        if (!$oppId) {
            return;
        }

        $opportunity = \App\Domain\CRM\Models\Opportunity::find($oppId);
        if (!$opportunity || !$opportunity->user_id) {
            return;
        }

        $this->notifications->send(
            $opportunity->user_id,
            "💔 Deal Lost",
            "Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) was Closed Lost. Reason: " . ($payload['lost_reason'] ?? $opportunity->lost_reason),
            'deal_lost',
            'crm',
            'medium',
            $opportunity->organization_id,
            ['opportunity_id' => $opportunity->id]
        );
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            QueuedEvent::class => 'handleQueuedEvent',
            \App\Domain\CRM\Events\LeadAssigned::class => 'handleEvent',
            \App\Domain\CRM\Events\LeadStatusChanged::class => 'handleEvent',
            \App\Domain\CRM\Events\LeadConverted::class => 'handleEvent',
            \App\Domain\CRM\Events\ContactUpdated::class => 'handleEvent',
            \App\Domain\CRM\Events\OpportunityCreated::class => 'handleEvent',
            \App\Domain\CRM\Events\OpportunityStageChanged::class => 'handleEvent',
            \App\Domain\CRM\Events\OpportunityForecastUpdated::class => 'handleEvent',
            \App\Domain\CRM\Events\OpportunityClosedWon::class => 'handleEvent',
            \App\Domain\CRM\Events\OpportunityClosedLost::class => 'handleEvent',
        ];
    }
}
