<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadAssignmentHistory;
use App\Domain\CRM\Models\LeadActivity;
use App\Domain\CRM\Events\LeadAssigned;
use App\Contracts\EventBus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadAssignmentService
{
    protected EventBus $eventBus;

    public function __construct(
        EventBus $eventBus
    ) {
        $this->eventBus = $eventBus;
    }

    /**
     * Assign a lead to a user manually.
     */
    public function assign(Lead $lead, ?string $toUserId, ?string $assignedBy = null, string $method = 'manual'): Lead
    {
        $fromUserId = $lead->user_id;

        if ($fromUserId === $toUserId) {
            return $lead; // No-op
        }

        DB::transaction(function () use ($lead, $fromUserId, $toUserId, $assignedBy, $method) {
            // Update Lead Owner
            $lead->user_id = $toUserId;
            $lead->save();

            // Record History
            LeadAssignmentHistory::create([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'assigned_by' => $assignedBy,
                'method' => $method,
            ]);

            // Get names for description
            $toName = $toUserId ? (User::find($toUserId)->name ?? 'Unknown') : 'Unassigned';
            $fromName = $fromUserId ? (User::find($fromUserId)->name ?? 'Unknown') : 'Unassigned';

            // Record Timeline Activity
            LeadActivity::create([
                'organization_id' => $lead->organization_id,
                'lead_id' => $lead->id,
                'user_id' => $assignedBy,
                'type' => 'assignment',
                'description' => "Reassigned lead owner from '{$fromName}' to '{$toName}' via [{$method}].",
                'properties' => [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'method' => $method,
                ],
            ]);

            // Dispatch Event via EventBus
            $event = new LeadAssigned($lead, $toUserId, $fromUserId, $assignedBy, ['method' => $method]);
            $this->eventBus->dispatch($event);
        });

        return $lead;
    }

    /**
     * Assign a lead to a user using a Round-Robin strategy.
     */
    public function assignRoundRobin(Lead $lead, array $userIds, ?string $assignedBy = null): Lead
    {
        if (empty($userIds)) {
            throw new InvalidArgumentException("User IDs list cannot be empty for round-robin assignment.");
        }

        // Filter and remove duplicates
        $userIds = array_values(array_unique($userIds));

        // Find the user whose most recent assignment is the oldest, or who has no assignment history
        $selectedUserId = null;
        $oldestTime = null;

        foreach ($userIds as $userId) {
            $latestAssignment = LeadAssignmentHistory::where('to_user_id', $userId)
                ->where('organization_id', $lead->organization_id)
                ->latest()
                ->first();

            if (!$latestAssignment) {
                // No assignment history means they are the absolute highest priority!
                $selectedUserId = $userId;
                break;
            }

            $timestamp = $latestAssignment->created_at->getTimestamp();
            if ($oldestTime === null || $timestamp < $oldestTime) {
                $oldestTime = $timestamp;
                $selectedUserId = $userId;
            }
        }

        if (!$selectedUserId) {
            $selectedUserId = $userIds[0];
        }

        return $this->assign($lead, $selectedUserId, $assignedBy, 'round_robin');
    }

    /**
     * Assign a lead to a user using a Load-Balanced strategy (lowest active lead count).
     */
    public function assignLoadBalanced(Lead $lead, array $userIds, ?string $assignedBy = null): Lead
    {
        if (empty($userIds)) {
            throw new InvalidArgumentException("User IDs list cannot be empty for load-balanced assignment.");
        }

        $userIds = array_values(array_unique($userIds));

        // Find active lead count for each user
        // Active lead means status is not won, lost, or archived
        $counts = [];
        foreach ($userIds as $userId) {
            $counts[$userId] = Lead::where('user_id', $userId)
                ->where('organization_id', $lead->organization_id)
                ->whereNotIn('status', ['won', 'lost', 'archived'])
                ->count();
        }

        // Sort by count ascending, keeping keys
        asort($counts);

        // Get the first key (user with minimum count)
        $selectedUserId = key($counts);

        return $this->assign($lead, $selectedUserId, $assignedBy, 'load_balanced');
    }

    /**
     * Assign a lead using manager override.
     */
    public function managerOverride(Lead $lead, ?string $toUserId, ?string $managerId): Lead
    {
        return $this->assign($lead, $toUserId, $managerId, 'manager_override');
    }
}
