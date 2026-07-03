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
        ];
    }
}
