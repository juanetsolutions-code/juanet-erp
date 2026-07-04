<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Anyone can submit a public lead form
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            
            // Handle various frontend field aliases
            'interest' => 'nullable|string|max:255',
            'service' => 'nullable|string|max:255',
            'service_interest' => 'nullable|string|max:255',
            
            'budget' => 'nullable|string|max:255',
            'budget_range' => 'nullable|string|max:255',
            
            'scope' => 'nullable|string|max:5000',
            'details' => 'nullable|string|max:5000',
            'message' => 'nullable|string|max:5000',
            
            'source' => 'nullable|string|max:100',
            
            // UTM fields
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
        ];
    }
}
