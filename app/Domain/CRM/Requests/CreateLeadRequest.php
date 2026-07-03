<?php

namespace App\Domain\CRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Checked via Policies in the controller/action
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'contact_id' => 'nullable|uuid|exists:crm_contacts,id',
            'lead_source_id' => 'nullable|uuid|exists:crm_lead_sources,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'status' => 'nullable|string|in:new,contacted,qualified,unqualified,converted,lost',
            'custom_fields' => 'nullable|array',
        ];
    }
}
