<?php

namespace App\Domain\CRM\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\Opportunity;
use App\Domain\CRM\Models\Pipeline;
use App\Domain\CRM\Models\PipelineStage;
use App\Domain\CRM\Models\LeadSource;
use App\Domain\CRM\Models\Industry;
use App\Domain\CRM\Services\LeadService;
use App\Domain\CRM\Services\ContactService;
use App\Domain\CRM\Services\CompanyService;
use App\Domain\CRM\Services\OpportunityService;
use App\Domain\CRM\Actions\ConvertLead;
use App\Domain\CRM\Actions\AssignLead;
use App\Domain\CRM\Actions\MoveOpportunity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class CrmWebController extends Controller
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

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Lead::class);

        $query = Lead::query();

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('crm.leads.index', compact('leads'));
    }

    public function createLead()
    {
        Gate::authorize('create', Lead::class);

        $companies = Company::all();
        $contacts = Contact::all();
        $sources = LeadSource::all();
        $users = User::all();

        return view('crm.leads.create', compact('companies', 'contacts', 'sources', 'users'));
    }

    public function storeLead(Request $request)
    {
        Gate::authorize('create', Lead::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'contact_id' => 'nullable|uuid|exists:crm_contacts,id',
            'lead_source_id' => 'nullable|uuid|exists:crm_lead_sources,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'status' => 'nullable|string|in:new,contacted,qualified,unqualified,converted,lost',
            'custom_fields' => 'nullable|array',
        ]);

        $this->leadService->createLead($validated);

        return redirect()->route('crm.leads.index')->with('success', 'Lead created successfully.');
    }

    public function showLead(string $id)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('view', $lead);

        $users = User::all();
        $pipelines = Pipeline::with('stages')->get();

        return view('crm.leads.show', compact('lead', 'users', 'pipelines'));
    }

    public function editLead(string $id)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('update', $lead);

        $companies = Company::all();
        $contacts = Contact::all();
        $sources = LeadSource::all();
        $users = User::all();

        return view('crm.leads.edit', compact('lead', 'companies', 'contacts', 'sources', 'users'));
    }

    public function updateLead(Request $request, string $id)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'contact_id' => 'nullable|uuid|exists:crm_contacts,id',
            'lead_source_id' => 'nullable|uuid|exists:crm_lead_sources,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'status' => 'nullable|string|in:new,contacted,qualified,unqualified,converted,lost',
            'custom_fields' => 'nullable|array',
        ]);

        $this->leadService->updateLead($id, $validated);

        return redirect()->route('crm.leads.show', $id)->with('success', 'Lead updated successfully.');
    }

    public function destroyLead(string $id)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('delete', $lead);

        $this->leadService->deleteLead($id);

        return redirect()->route('crm.leads.index')->with('success', 'Lead archived successfully.');
    }

    public function assignLead(Request $request, string $id, AssignLead $assignLeadAction)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $assignLeadAction->execute($id, $validated['user_id']);

        return redirect()->route('crm.leads.show', $id)->with('success', 'Lead assigned successfully.');
    }

    public function convertLeadForm(string $id)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('update', $lead);

        $pipelines = Pipeline::with('stages')->get();

        return view('crm.leads.convert', compact('lead', 'pipelines'));
    }

    public function convertLead(Request $request, string $id, ConvertLead $convertLeadAction)
    {
        $lead = $this->leadService->getLead($id);
        if (!$lead) {
            abort(404, 'Lead not found.');
        }

        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'create_company' => 'nullable|boolean',
            'company_name' => 'required_if:create_company,1|nullable|string|max:255',
            'create_contact' => 'nullable|boolean',
            'create_opportunity' => 'nullable|boolean',
            'opportunity_name' => 'required_if:create_opportunity,1|nullable|string|max:255',
            'pipeline_id' => 'required_if:create_opportunity,1|nullable|uuid|exists:crm_pipelines,id',
            'pipeline_stage_id' => 'required_if:create_opportunity,1|nullable|uuid|exists:crm_pipeline_stages,id',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $convertLeadAction->execute($id, [
            'create_company' => $request->boolean('create_company'),
            'company_name' => $validated['company_name'] ?? null,
            'create_contact' => $request->boolean('create_contact'),
            'create_opportunity' => $request->boolean('create_opportunity'),
            'opportunity_name' => $validated['opportunity_name'] ?? null,
            'pipeline_id' => $validated['pipeline_id'] ?? null,
            'pipeline_stage_id' => $validated['pipeline_stage_id'] ?? null,
            'amount' => $validated['amount'] ?? 0.00,
        ]);

        return redirect()->route('crm.leads.show', $id)->with('success', 'Lead converted successfully.');
    }

    public function contacts(Request $request)
    {
        Gate::authorize('viewAny', Contact::class);

        $query = Contact::query();

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $contacts = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('crm.contacts.index', compact('contacts'));
    }

    public function companies(Request $request)
    {
        Gate::authorize('viewAny', Company::class);

        $query = Company::query();

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        $companies = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('crm.companies.index', compact('companies'));
    }

    public function opportunities(Request $request)
    {
        Gate::authorize('viewAny', Opportunity::class);

        $pipeline = Pipeline::with('stages.opportunities.company', 'stages.opportunities.contact')->first();

        if (!$pipeline) {
            // Seed a default pipeline and stages if not present
            $pipeline = Pipeline::create([
                'organization_id' => Auth::user()->memberships()->first()?->organization_id,
                'name' => 'Standard Pipeline',
            ]);

            $stages = ['Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];
            foreach ($stages as $index => $stageName) {
                PipelineStage::create([
                    'pipeline_id' => $pipeline->id,
                    'organization_id' => $pipeline->organization_id,
                    'name' => $stageName,
                    'order' => $index,
                ]);
            }

            $pipeline->load('stages.opportunities.company', 'stages.opportunities.contact');
        }

        return view('crm.opportunities.index', compact('pipeline'));
    }

    public function moveOpportunity(Request $request, MoveOpportunity $moveOpportunityAction)
    {
        $validated = $request->validate([
            'opportunity_id' => 'required|uuid|exists:crm_opportunities,id',
            'stage_id' => 'required|uuid|exists:crm_pipeline_stages,id',
        ]);

        $opportunity = $this->opportunityService->getOpportunity($validated['opportunity_id']);
        if (!$opportunity) {
            return response()->json(['message' => 'Opportunity not found.'], 404);
        }

        Gate::authorize('update', $opportunity);

        $moveOpportunityAction->execute($validated['opportunity_id'], $validated['stage_id']);

        return response()->json(['message' => 'Opportunity moved successfully.']);
    }
}
