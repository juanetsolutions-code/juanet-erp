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
        ];
    }
}
