<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadStatusHistory;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Events\LeadStatusChanged;
use App\Contracts\EventBus;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class LeadStateMachine
{
    protected EventBus $eventBus;

    public const STATES = [
        'new',
        'qualified',
        'contacted',
        'meeting_scheduled',
        'proposal_sent',
        'negotiation',
        'won',
        'lost',
        'archived',
        'converted'
    ];

    /**
     * Map of valid transitions.
     * key: current state
     * value: list of valid next states
     */
    protected const TRANSITION_MAP = [
        'new' => ['contacted', 'qualified', 'lost', 'archived', 'converted'],
        'contacted' => ['qualified', 'meeting_scheduled', 'lost', 'archived', 'converted'],
        'meeting_scheduled' => ['proposal_sent', 'negotiation', 'lost', 'archived', 'converted'],
        'proposal_sent' => ['negotiation', 'won', 'lost', 'archived', 'converted'],
        'negotiation' => ['won', 'lost', 'archived', 'converted'],
        'won' => ['archived', 'converted'],
        'lost' => ['new', 'archived'],
        'archived' => ['new', 'contacted', 'qualified', 'meeting_scheduled', 'proposal_sent', 'negotiation', 'won', 'lost', 'converted'],
        'converted' => ['archived']
    ];

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Validate if a transition from one state to another is allowed.
     */
    public function canTransition(string $from, string $to): bool
    {
        $from = strtolower($from);
        $to = strtolower($to);

        if (!in_array($from, self::STATES) || !in_array($to, self::STATES)) {
            return false;
        }

        if ($from === $to) {
            return true; // No-op is valid
        }

        // Standard transitions
        $allowed = self::TRANSITION_MAP[$from] ?? [];
        if (in_array($to, $allowed)) {
            return true;
        }

        // Broad fallback: any active state can go to archived, and archived can go back to any state.
        if ($to === 'archived') {
            return true;
        }

        return false;
    }

    /**
     * Perform the state transition.
     */
    public function transition(Lead $lead, string $toStatus, ?string $changedBy = null, ?string $reason = null): Lead
    {
        $fromStatus = strtolower($lead->status);
        $toStatus = strtolower($toStatus);

        if ($fromStatus === $toStatus) {
            return $lead; // No-op
        }

        if (!$this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Invalid transition from status [{$fromStatus}] to [{$toStatus}].");
        }

        DB::transaction(function () use ($lead, $fromStatus, $toStatus, $changedBy, $reason) {
            // Update Lead
            $lead->status = $toStatus;
            $lead->save();

            // Record History
            LeadStatusHistory::create([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);

            // Record Timeline Activity
            LeadActivity::create([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'user_id' => $changedBy,
                'type' => 'status_change',
                'description' => "Changed lead status from '{$fromStatus}' to '{$toStatus}'." . ($reason ? " Reason: {$reason}" : ""),
                'properties' => [
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'reason' => $reason,
                ],
            ]);

            // Dispatch Event via EventBus
            $event = new LeadStatusChanged($lead, $toStatus, $fromStatus, $changedBy, ['reason' => $reason]);
            $this->eventBus->dispatch($event);
        });

        return $lead;
    }
}
