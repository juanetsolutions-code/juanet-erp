<?php

namespace App\Domain\CRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'preferred_name' => $this->preferred_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'decision_maker_level' => $this->decision_maker_level,
            'buying_influence' => $this->buying_influence,
            'linkedin_url' => $this->linkedin_url,
            'twitter_url' => $this->twitter_url,
            'facebook_url' => $this->facebook_url,
            'website' => $this->website,
            'profile_image_url' => $this->profile_image_url,
            'preferred_language' => $this->preferred_language,
            'timezone' => $this->timezone,
            'birthday' => $this->birthday?->toDateString(),
            'anniversary' => $this->anniversary?->toDateString(),
            'notes' => $this->notes,
            'communication_preferences' => $this->communication_preferences,
            'gdpr_consent_status' => $this->gdpr_consent_status,
            'health_score' => $this->health_score,
            'health_status' => $this->health_status,
            'health_breakdown' => $this->health_breakdown,
            'custom_fields' => $this->custom_fields,
            'user_id' => $this->user_id,
            'lock_version' => $this->lock_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'associated_companies' => CompanyResource::collection($this->whenLoaded('associatedCompanies')),
            'contact_methods' => $this->whenLoaded('contactMethods'),
            'relationships' => $this->whenLoaded('relationships'),
        ];
    }
}
