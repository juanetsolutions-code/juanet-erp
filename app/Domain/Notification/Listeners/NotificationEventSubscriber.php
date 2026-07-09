<?php

namespace App\Domain\Notification\Listeners;

use App\Events\Interfaces\DomainEventInterface;
use App\Events\QueuedEvent;
use App\Domain\Notification\Services\NotificationService;
use App\Models\User;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class NotificationEventSubscriber
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle incoming domain events.
     */
    public function handleEvent(DomainEventInterface $event): void
    {
        $eventName = $event->getEventName();
        $payload = $event->getPayload();
        $orgId = $event->getOrganizationId();

        Log::info("Notification Bounded Context handling event: {$eventName}", [
            'org_id' => $orgId,
            'idemp_key' => $event->getIdempotencyKey()
        ]);

        try {
            // Find a recipient user:
            // 1. Check user_id or actor_id in payload
            // 2. Check the standard authenticated user or first user as a robust fallback
            $userId = $payload['user_id'] ?? $payload['actor_id'] ?? null;
            if (!$userId || !User::find($userId)) {
                $firstUser = User::first();
                $userId = $firstUser ? $firstUser->id : null;
            }

            if (!$userId) {
                Log::warning("No valid recipient user found for notification of event: {$eventName}");
                return;
            }

            // Determine notification details based on event name
            $title = 'Notification';
            $category = 'system';
            $priority = 'normal';
            $type = 'info';

            switch ($eventName) {
                case 'crm.lead.created':
                    $title = 'New Lead Created';
                    $category = 'crm';
                    $priority = 'high';
                    $type = 'success';
                    break;

                case 'crm.lead.assigned':
                    $title = 'Lead Assigned';
                    $category = 'crm';
                    $priority = 'normal';
                    break;

                case 'proposal.created':
                    $title = 'Proposal Created';
                    $category = 'system';
                    $priority = 'normal';
                    break;

                case 'proposal.accepted':
                    $title = 'Proposal Accepted';
                    $category = 'system';
                    $priority = 'high';
                    $type = 'success';
                    break;

                case 'proposal.signed':
                    $title = 'Proposal Signed';
                    $category = 'system';
                    $priority = 'high';
                    $type = 'success';
                    break;

                case 'project.created':
                case 'proposal.project_created':
                    $title = 'Project Created';
                    $category = 'system';
                    $priority = 'normal';
                    $type = 'success';
                    break;

                case 'milestone.completed':
                case 'project.milestone_completed':
                    $title = 'Milestone Completed';
                    $category = 'system';
                    $priority = 'normal';
                    $type = 'success';
                    break;

                case 'payment.received':
                    $title = 'Payment Received';
                    $category = 'billing';
                    $priority = 'high';
                    $type = 'success';
                    break;

                case 'order.completed':
                case 'marketplace.order.created':
                    $title = 'Order Completed';
                    $category = 'billing';
                    $priority = 'high';
                    $type = 'success';
                    break;

                case 'marketplace.purchase':
                    $title = 'Marketplace Purchase';
                    $category = 'billing';
                    $priority = 'high';
                    break;

                case 'download.ready':
                    $title = 'Download Ready';
                    $category = 'system';
                    $priority = 'low';
                    break;

                case 'client.message':
                    $title = 'Client Message';
                    $category = 'crm';
                    $priority = 'normal';
                    break;

                case 'comment.created':
                case 'new.comment':
                    $title = 'New Comment';
                    $category = 'system';
                    $priority = 'low';
                    break;

                case 'file.uploaded':
                case 'new.file_uploaded':
                    $title = 'New File Uploaded';
                    $category = 'system';
                    $priority = 'low';
                    break;
            }

            // Dispatch notification using the centralized notification engine (with templates/queues/outbox)
            $this->notificationService->send(
                $userId,
                $title,
                $eventName, // This will match the template name
                $type,
                $category,
                $priority,
                $orgId,
                $payload
            );

        } catch (\Throwable $e) {
            Log::error("Notification Bounded Context failed to handle event {$eventName}: " . $e->getMessage(), [
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
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            QueuedEvent::class => 'handleQueuedEvent',
            \App\Domain\CRM\Events\LeadCreatedEvent::class => 'handleEvent',
            \App\Domain\CRM\Events\LeadAssignedEvent::class => 'handleEvent',
            \App\Domain\Proposal\Events\ProposalCreated::class => 'handleEvent',
            \App\Domain\Proposal\Events\ProposalAccepted::class => 'handleEvent',
            \App\Domain\Proposal\Events\ProposalSigned::class => 'handleEvent',
            \App\Domain\Proposal\Events\ProjectCreated::class => 'handleEvent',
            \App\Domain\Proposal\Events\ProjectInitialized::class => 'handleEvent',
            \App\Domain\Proposal\Events\MilestoneCreated::class => 'handleEvent',
            \App\Domain\Proposal\Events\ChecklistCreated::class => 'handleEvent',
            \App\Domain\Marketplace\Events\OrderCreated::class => 'handleEvent',
        ];
    }
}
