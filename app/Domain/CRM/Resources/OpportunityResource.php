<?php

namespace App\Domain\CRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'close_date' => $this->close_date?->toDateString(),
            'status' => $this->status,
            'custom_fields' => $this->custom_fields,
            'lock_version' => $this->lock_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            
            // Phase 4.6 extended attributes
            'opportunity_number' => $this->opportunity_number,
            'description' => $this->description,
            'source' => $this->source,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'actual_close_date' => $this->actual_close_date?->toDateString(),
            'estimated_revenue' => $this->estimated_revenue,
            'weighted_revenue' => $this->weighted_revenue,
            'win_probability' => $this->win_probability,
            'currency' => $this->currency,
            'forecast_category' => $this->forecast_category,
            'competitor' => $this->competitor,
            'lost_reason' => $this->lost_reason,
            'won_reason' => $this->won_reason,
            'sales_team' => $this->sales_team,
            
            // AI Readiness Placeholders
            'ai_confidence' => $this->ai_confidence,
            'ai_win_probability_prediction' => $this->ai_win_probability_prediction,
            'ai_next_best_action' => $this->ai_next_best_action,
            'ai_deal_health' => $this->ai_deal_health,
            'ai_risk_detection' => $this->ai_risk_detection,
            'ai_upsell_recommendations' => $this->ai_upsell_recommendations,

            // Relations
            'pipeline_id' => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'user_id' => $this->user_id,
            'products' => $this->products,
        ];
    }
}
