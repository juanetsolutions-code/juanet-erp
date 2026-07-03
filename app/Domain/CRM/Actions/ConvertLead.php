<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Services\LeadService;
use App\Domain\CRM\Services\ContactService;
use App\Domain\CRM\Services\CompanyService;
use App\Domain\CRM\Services\OpportunityService;
use Illuminate\Support\Facades\DB;

class ConvertLead
{
    protected LeadService $leadService;
    protected ContactService $contactService;
    protected CompanyService $companyService;
    protected OpportunityService $opportunityService;

    public function __construct(
        LeadService $leadService,
        ContactService $contactService,
        CompanyService $companyService,
        OpportunityService $opportunityService
    ) {
        $this->leadService = $leadService;
        $this->contactService = $contactService;
        $this->companyService = $companyService;
        $this->opportunityService = $opportunityService;
    }

    public function execute(string $leadId, array $options = []): array
    {
        return DB::transaction(function () use ($leadId, $options) {
            $lead = $this->leadService->getLead($leadId);
            if (!$lead) {
                throw new \InvalidArgumentException("Lead not found.");
            }

            // Create Company if not already associated
            $company = null;
            if (!empty($options['create_company']) && !empty($options['company_name'])) {
                $company = $this->companyService->createCompany([
                    'organization_id' => $lead->organization_id,
                    'name' => $options['company_name'],
                ]);
            } elseif ($lead->company_id) {
                $company = $lead->company;
            }

            // Create Contact
            $contact = null;
            if (!empty($options['create_contact'])) {
                // Parse first/last name
                $parts = explode(' ', $lead->name, 2);
                $firstName = $parts[0] ?? $lead->name;
                $lastName = $parts[1] ?? 'Contact';

                $contact = $this->contactService->createContact([
                    'organization_id' => $lead->organization_id,
                    'company_id' => $company ? $company->id : null,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                ]);
            }

            // Create Opportunity
            $opportunity = null;
            if (!empty($options['create_opportunity']) && !empty($options['opportunity_name'])) {
                $opportunity = $this->opportunityService->createOpportunity([
                    'organization_id' => $lead->organization_id,
                    'company_id' => $company ? $company->id : null,
                    'contact_id' => $contact ? $contact->id : null,
                    'pipeline_id' => $options['pipeline_id'],
                    'pipeline_stage_id' => $options['pipeline_stage_id'],
                    'name' => $options['opportunity_name'],
                    'amount' => $options['amount'] ?? 0.00,
                    'status' => 'open',
                ]);
            }

            // Mark lead as converted
            $lead->update([
                'status' => 'converted',
                'company_id' => $company ? $company->id : $lead->company_id,
                'contact_id' => $contact ? $contact->id : $lead->contact_id,
            ]);

            return [
                'lead' => $lead,
                'contact' => $contact,
                'company' => $company,
                'opportunity' => $opportunity,
            ];
        });
    }
}
