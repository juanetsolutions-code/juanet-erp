<?php

namespace App\Domain\CRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'industry_id' => 'nullable|uuid|exists:crm_industries,id',
            'industry_classification' => 'nullable|string|max:255',
            'company_size' => 'nullable|string|max:255',
            'annual_revenue' => 'nullable|numeric|min:0',
            'employees_count' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|uuid|exists:crm_companies,id',
            'status' => 'nullable|string|in:Prospect,Customer,Partner,Vendor,Inactive',
            'user_id' => 'nullable|uuid|exists:users,id',
            'territory' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:255',
            'preferred_language' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'domain' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'social_media_profiles' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ];
    }
}
