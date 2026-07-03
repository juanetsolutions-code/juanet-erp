<?php

namespace App\Domain\CRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'job_title' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
        ];
    }
}
