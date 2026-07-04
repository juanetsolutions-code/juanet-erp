<?php

namespace App\Services\DTO;

use Illuminate\Http\Request;

class PublicLeadDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?string $company = null,
        public readonly ?string $service_interest = null,
        public readonly ?string $budget_range = null,
        public readonly ?string $message = null,
        public readonly ?string $source = null,
        public readonly array $utm_fields = [],
        public readonly ?string $referrer = null,
        public readonly ?string $user_agent = null,
        public readonly ?string $ip_address = null
    ) {}

    /**
     * Create DTO from Laravel Request.
     */
    public static function fromRequest(Request $request): self
    {
        // Extract UTM parameters
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $utmFields = [];
        foreach ($utmKeys as $key) {
            if ($request->filled($key)) {
                $utmFields[$key] = strip_tags($request->input($key));
            }
        }

        return new self(
            name: strip_tags($request->input('name')),
            email: strip_tags($request->input('email')),
            phone: strip_tags($request->input('phone')),
            company: strip_tags($request->input('company')),
            service_interest: strip_tags($request->input('service_interest') ?? $request->input('interest') ?? $request->input('service')),
            budget_range: strip_tags($request->input('budget_range') ?? $request->input('budget')),
            message: strip_tags($request->input('message') ?? $request->input('scope') ?? $request->input('details')),
            source: strip_tags($request->input('source', 'public')),
            utm_fields: $utmFields,
            referrer: $request->header('referer') ?? $request->input('referrer'),
            user_agent: $request->userAgent(),
            ip_address: $request->ip()
        );
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'service_interest' => $this->service_interest,
            'budget_range' => $this->budget_range,
            'message' => $this->message,
            'source' => $this->source,
            'utm_fields' => $this->utm_fields,
            'referrer' => $this->referrer,
            'user_agent' => $this->user_agent,
            'ip_address' => $this->ip_address,
        ];
    }
}
