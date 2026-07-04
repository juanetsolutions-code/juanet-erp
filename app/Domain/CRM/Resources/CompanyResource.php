<?php

namespace App\Domain\CRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'trading_name' => $this->trading_name,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
            'industry_id' => $this->industry_id,
            'industry_classification' => $this->industry_classification,
            'company_size' => $this->company_size,
            'annual_revenue' => $this->annual_revenue,
            'employees_count' => $this->employees_count,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'owner_name' => $this->owner?->name,
            'territory' => $this->territory,
            'timezone' => $this->timezone,
            'preferred_language' => $this->preferred_language,
            'currency' => $this->currency,
            'domain' => $this->domain,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'social_media_profiles' => $this->social_media_profiles,
            'custom_fields' => $this->custom_fields,
            'health_score' => $this->health_score ?? 100,
            'health_status' => $this->health_status ?? 'Healthy',
            'health_breakdown' => $this->health_breakdown,
            'locations' => $this->locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'type' => $loc->type,
                    'name' => $loc->name,
                    'address' => $loc->address,
                    'country' => $loc->country,
                    'state' => $loc->state,
                    'county' => $loc->county,
                    'city' => $loc->city,
                    'postal_code' => $loc->postal_code,
                    'gps_coordinates' => $loc->gps_coordinates,
                    'timezone' => $loc->timezone,
                    'phone' => $loc->phone,
                    'email' => $loc->email,
                ];
            }),
            'lock_version' => $this->lock_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
