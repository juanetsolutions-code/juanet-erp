<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Models\PipelineStage;
use App\Domain\CRM\Events\OpportunityStageChangedEvent;
use App\Domain\CRM\Events\OpportunityClosedWonEvent;
use App\Domain\CRM\Events\OpportunityClosedLostEvent;
use App\Domain\CRM\Events\OpportunityForecastUpdatedEvent;
use App\Domain\CRM\Activities\Models\Activity;
use App\Contracts\EventBus;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class PipelineStateMachine
{
    protected EventBus $eventBus;

    public const STATES = [
        'qualification',
        'discovery',
        'proposal',
        'negotiation',
        'legal_review',
        'procurement',
        'closed_won',
        'closed_lost',
        'archived'
    ];

    protected const TRANSITION_MAP = [
        'qualification' => ['discovery', 'closed_won', 'closed_lost', 'archived'],
        'discovery' => ['proposal', 'closed_won', 'closed_lost', 'archived'],
        'proposal' => ['negotiation', 'closed_won', 'closed_lost', 'archived'],
        'negotiation' => ['legal_review', 'closed_won', 'closed_lost', 'archived'],
        'legal_review' => ['procurement', 'closed_won', 'closed_lost', 'archived'],
        'procurement' => ['closed_won', 'closed_lost', 'archived'],
        'closed_won' => ['archived'],
        'closed_lost' => ['qualification', 'discovery', 'archived'],
        'archived' => ['qualification', 'discovery', 'proposal', 'negotiation', 'legal_review', 'procurement', 'closed_won', 'closed_lost']
    ];

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Map a pipeline stage name/slug to one of our deterministic state machine states.
     */
    public function getLogicalState(PipelineStage $stage): string
    {
        $name = strtolower(trim($stage->name));
        $slug = str_replace([' ', '-'], '_', $name);

        foreach (self::STATES as $state) {
            if ($slug === $state || str_contains($slug, $state)) {
                return $state;
            }
        }

        return 'qualification';
    }

    public function canTransition(PipelineStage $fromStage, PipelineStage $toStage): bool
    {
        $fromState = $this->getLogicalState($fromStage);
        $toState = $this->getLogicalState($toStage);

        if ($fromState === $toState) {
            return true;
        }

        $allowed = self::TRANSITION_MAP[$fromState] ?? [];
        return in_array($toState, $allowed) || $toState === 'archived';
    }

    public function transition(Opportunity $opportunity, PipelineStage $toStage, ?string $changedBy = null, ?string $reason = null): Opportunity
    {
        $fromStage = $opportunity->stage;
        
        if (!$fromStage) {
            // Initial transition
            DB::transaction(function () use ($opportunity, $toStage, $changedBy) {
                $opportunity->pipeline_stage_id = $toStage->id;
                $opportunity->pipeline_id = $toStage->pipeline_id;
                $opportunity->win_probability = $toStage->probability;
                
                $logical = $this->getLogicalState($toStage);
                if ($logical === 'closed_won') {
                    $opportunity->status = 'won';
                    $opportunity->actual_close_date = now();
                } elseif ($logical === 'closed_lost') {
                    $opportunity->status = 'lost';
                    $opportunity->actual_close_date = now();
                } else {
                    $opportunity->status = 'open';
                }

                $opportunity->save();
                $opportunity->recalculateTotals();

                // Log into Activity Engine
                Activity::create([
                    'organization_id' => $opportunity->organization_id,
                    'loggable_type' => Opportunity::class,
                    'loggable_id' => $opportunity->id,
                    'user_id' => $changedBy,
                    'type' => 'status_change',
                    'subject' => 'Opportunity pipeline initiated',
                    'description' => "Opportunity pipeline set to stage '{$toStage->name}'.",
                    'properties' => [
                        'to_stage_id' => $toStage->id,
                        'to_stage_name' => $toStage->name,
                    ]
                ]);

                $this->eventBus->dispatch(new OpportunityStageChangedEvent($opportunity, '', $toStage->id));
            });
            return $opportunity;
        }

        if ($fromStage->id === $toStage->id) {
            return $opportunity; // No-op
        }

        if (!$this->canTransition($fromStage, $toStage)) {
            throw new InvalidArgumentException("Invalid pipeline stage transition from [{$fromStage->name}] to [{$toStage->name}].");
        }

        DB::transaction(function () use ($opportunity, $fromStage, $toStage, $changedBy, $reason) {
            $opportunity->pipeline_stage_id = $toStage->id;
            $opportunity->pipeline_id = $toStage->pipeline_id;
            $opportunity->win_probability = $toStage->probability;

            $fromLogical = $this->getLogicalState($fromStage);
            $toLogical = $this->getLogicalState($toStage);

            if ($toLogical === 'closed_won') {
                $opportunity->status = 'won';
                $opportunity->actual_close_date = now();
                $opportunity->won_reason = $reason;
            } elseif ($toLogical === 'closed_lost') {
                $opportunity->status = 'lost';
                $opportunity->actual_close_date = now();
                $opportunity->lost_reason = $reason;
            } else {
                $opportunity->status = 'open';
            }

            $opportunity->save();
            $opportunity->recalculateTotals();

            // Store stage changed event
            $this->eventBus->dispatch(new OpportunityStageChangedEvent($opportunity, $fromStage->id, $toStage->id));

            // Store specific closed won/lost events
            if ($toLogical === 'closed_won') {
                $this->eventBus->dispatch(new OpportunityClosedWonEvent($opportunity));
            } elseif ($toLogical === 'closed_lost') {
                $this->eventBus->dispatch(new OpportunityClosedLostEvent($opportunity));
            }

            // Store forecast updated event
            $this->eventBus->dispatch(new OpportunityForecastUpdatedEvent($opportunity));

            // Log activity
            Activity::create([
                'organization_id' => $opportunity->organization_id,
                'loggable_type' => Opportunity::class,
                'loggable_id' => $opportunity->id,
                'user_id' => $changedBy,
                'type' => 'status_change',
                'subject' => 'Opportunity Stage Changed',
                'description' => "Transitioned from '{$fromStage->name}' to '{$toStage->name}'." . ($reason ? " Reason: {$reason}" : ""),
                'properties' => [
                    'from_stage_id' => $fromStage->id,
                    'from_stage_name' => $fromStage->name,
                    'to_stage_id' => $toStage->id,
                    'to_stage_name' => $toStage->name,
                    'reason' => $reason
                ]
            ]);
        });

        return $opportunity;
    }
}
