<?php

namespace App\Domain\CRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'contact_id' => 'nullable|uuid|exists:crm_contacts,id',
            'pipeline_id' => 'required|uuid|exists:crm_pipelines,id',
            'pipeline_stage_id' => 'required|uuid|exists:crm_pipeline_stages,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'amount' => 'nullable|numeric|min:0',
            'close_date' => 'nullable|date',
            'status' => 'nullable|string|in:open,won,lost',
            'custom_fields' => 'nullable|array',

            // Extended fields
            'opportunity_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'expected_close_date' => 'nullable|date',
            'actual_close_date' => 'nullable|date',
            'estimated_revenue' => 'nullable|numeric|min:0',
            'win_probability' => 'nullable|integer|min:0|max:100',
            'currency' => 'nullable|string|size:3',
            'forecast_category' => 'nullable|string|in:commit,best_case,pipeline,omitted',
            'competitor' => 'nullable|string|max:255',
            'lost_reason' => 'nullable|string',
            'won_reason' => 'nullable|string',
            'sales_team' => 'nullable|string|max:255',
            
            // AI Placeholders
            'ai_confidence' => 'nullable|numeric|min:0|max:100',
            'ai_win_probability_prediction' => 'nullable|numeric|min:0|max:100',
            'ai_next_best_action' => 'nullable|string',
            'ai_deal_health' => 'nullable|string|max:50',
            'ai_risk_detection' => 'nullable|string',
            'ai_upsell_recommendations' => 'nullable|array',
        ];
    }
}
