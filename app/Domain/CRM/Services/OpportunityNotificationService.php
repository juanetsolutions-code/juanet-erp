<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Opportunity;
use App\Models\Notification;
use App\Models\User;

class OpportunityNotificationService
{
    /**
     * Notify about stage changes
     */
    public function notifyStageChange(Opportunity $opportunity, string $fromStageName, string $toStageName): void
    {
        $this->sendToOwnerAndTeam(
            $opportunity,
            "Opportunity Stage Updated",
            "Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) has moved from '{$fromStageName}' to '{$toStageName}'.",
            'stage_change',
            'info'
        );
    }

    /**
     * Notify about forecast category changes
     */
    public function notifyForecastChange(Opportunity $opportunity, string $fromCategory, string $toCategory): void
    {
        $this->sendToOwnerAndTeam(
            $opportunity,
            "Forecast Category Updated",
            "Opportunity #{$opportunity->opportunity_number} forecast category changed from '{$fromCategory}' to '{$toCategory}'.",
            'forecast_change',
            'info'
        );
    }

    /**
     * Notify if high value deal is updated/created
     */
    public function notifyHighValueDeal(Opportunity $opportunity): void
    {
        if ($opportunity->amount >= 100000) {
            $this->sendToOwnerAndTeam(
                $opportunity,
                "🔥 High-Value Deal Alert",
                "High-value Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) is currently at stage '{$opportunity->stage?->name}' with value {$opportunity->currency} " . number_format($opportunity->amount, 2),
                'high_value_deal',
                'high'
            );
        }
    }

    /**
     * Notify when won
     */
    public function notifyDealWon(Opportunity $opportunity): void
    {
        $this->sendToOwnerAndTeam(
            $opportunity,
            "🎉 Deal Won!",
            "Congratulations! Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) has been Closed Won for " . number_format($opportunity->amount, 2),
            'deal_won',
            'high'
        );
    }

    /**
     * Notify when lost
     */
    public function notifyDealLost(Opportunity $opportunity): void
    {
        $this->sendToOwnerAndTeam(
            $opportunity,
            "💔 Deal Lost",
            "Opportunity #{$opportunity->opportunity_number} ({$opportunity->name}) was Closed Lost. Reason: {$opportunity->lost_reason}",
            'deal_lost',
            'medium'
        );
    }

    /**
     * Notify if approaching close date (within 7 days)
     */
    public function notifyApproachingCloseDate(Opportunity $opportunity): void
    {
        if ($opportunity->expected_close_date && $opportunity->status === 'open') {
            $days = now()->diffInDays($opportunity->expected_close_date, false);
            if ($days >= 0 && $days <= 7) {
                $this->sendToOwnerAndTeam(
                    $opportunity,
                    "⏰ Approaching Close Date",
                    "Opportunity #{$opportunity->opportunity_number} close date is in {$days} days ({$opportunity->expected_close_date->format('Y-m-d')}).",
                    'approaching_close_date',
                    'medium'
                );
            }
        }
    }

    /**
     * Helper to dispatch notification to the owner and relevant team members
     */
    protected function sendToOwnerAndTeam(Opportunity $opportunity, string $title, string $body, string $type, string $priority): void
    {
        $userIds = [];
        if ($opportunity->user_id) {
            $userIds[] = $opportunity->user_id;
        }

        // De-duplicate user IDs and send
        $userIds = array_unique($userIds);

        foreach ($userIds as $userId) {
            Notification::create([
                'organization_id' => $opportunity->organization_id,
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'category' => 'crm',
                'priority' => $priority,
                'is_read' => false,
                'data' => [
                    'opportunity_id' => $opportunity->id,
                    'opportunity_number' => $opportunity->opportunity_number,
                    'amount' => $opportunity->amount,
                ],
                'version' => 1,
            ]);
        }
    }
}
