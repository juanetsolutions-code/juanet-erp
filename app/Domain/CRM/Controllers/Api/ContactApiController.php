<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\Company;
use App\Domain\CRM\Models\ContactMethod;
use App\Domain\CRM\Models\ContactRelationship;
use App\Domain\CRM\Models\ContactCompanyAssociation;
use App\Domain\CRM\Services\ContactService;
use App\Domain\CRM\Requests\CreateContactRequest;
use App\Domain\CRM\Resources\ContactResource;
use App\Domain\CRM\Events\ContactCommunicationUpdatedEvent;
use App\Domain\CRM\Events\ContactRelationshipUpdatedEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Services\TenantContext;

class ContactApiController extends Controller
{
    protected ContactService $service;

    public function __construct(ContactService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Contact::class);

        $query = Contact::query();

        // Standard tenant scope just in case, but repository covers it.
        $orgId = app(TenantContext::class)->getTenantId();
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        // Filtering
        if ($request->has('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }
        if ($request->has('department')) {
            $query->where('department', $request->input('department'));
        }
        if ($request->has('decision_maker_level')) {
            $query->where('decision_maker_level', $request->input('decision_maker_level'));
        }
        if ($request->has('buying_influence')) {
            $query->where('buying_influence', $request->input('buying_influence'));
        }
        if ($request->has('gdpr_consent_status')) {
            $query->where('gdpr_consent_status', $request->input('gdpr_consent_status'));
        }
        if ($request->has('health_status')) {
            $query->where('health_status', $request->input('health_status'));
        }

        // Search across: Name, Email, Phone, Company, Department, Job Title, LinkedIn, Tags
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('job_title', 'like', "%{$search}%")
                  ->orWhere('linkedin_url', 'like', "%{$search}%")
                  ->orWhereHas('company', function ($companyQuery) use ($search) {
                      $companyQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('tags', function ($tagQuery) use ($search) {
                      $tagQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Includes
        $includes = explode(',', $request->input('include', ''));
        if (in_array('company', $includes)) {
            $query->with('company');
        }
        if (in_array('associated_companies', $includes)) {
            $query->with('associatedCompanies');
        }
        if (in_array('contact_methods', $includes)) {
            $query->with('contactMethods');
        }
        if (in_array('relationships', $includes)) {
            $query->with('relationships');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $contacts = $query->paginate($perPage);

        return response()->json([
            'data' => ContactResource::collection($contacts->items()),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ]
        ]);
    }

    public function store(CreateContactRequest $request): JsonResponse
    {
        Gate::authorize('create', Contact::class);

        $data = $request->validated();
        // Capture extra extended properties
        $data = array_merge($data, $request->only([
            'preferred_name',
            'department',
            'decision_maker_level',
            'buying_influence',
            'linkedin_url',
            'twitter_url',
            'facebook_url',
            'website',
            'profile_image_url',
            'preferred_language',
            'timezone',
            'birthday',
            'anniversary',
            'notes',
            'communication_preferences',
            'gdpr_consent_status',
            'user_id',
        ]));

        $contact = $this->service->createContact($data);

        return response()->json([
            'message' => 'Contact created successfully.',
            'data' => new ContactResource($contact->load(['company'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('view', $contact);

        $contact->load(['company', 'associatedCompanies', 'contactMethods', 'relationships']);

        return response()->json([
            'data' => new ContactResource($contact),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'decision_maker_level' => 'nullable|string|max:255',
            'buying_influence' => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|string|max:255',
            'twitter_url' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'profile_image_url' => 'nullable|string|max:255',
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'birthday' => 'nullable|date',
            'anniversary' => 'nullable|date',
            'notes' => 'nullable|string',
            'communication_preferences' => 'nullable|array',
            'gdpr_consent_status' => 'nullable|string|max:50',
            'custom_fields' => 'nullable|array',
            'user_id' => 'nullable|uuid|exists:users,id',
        ]);

        $updated = $this->service->updateContact($id, $validated);

        return response()->json([
            'message' => 'Contact updated successfully.',
            'data' => new ContactResource($updated->load(['company'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('delete', $contact);

        $this->service->deleteContact($id);

        return response()->json([
            'message' => 'Contact deleted successfully.',
        ]);
    }

    // Recalculate Health score
    public function recalculateHealth(string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $score = $contact->recalculateHealthScore();

        return response()->json([
            'message' => 'Contact health score calculated successfully.',
            'health_score' => $score,
            'health_status' => $contact->health_status,
            'health_breakdown' => $contact->health_breakdown,
        ]);
    }

    // Bulk Updates
    public function bulkUpdate(Request $request): JsonResponse
    {
        Gate::authorize('update', Contact::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:crm_contacts,id',
            'data' => 'required|array',
            'data.department' => 'sometimes|string|max:255',
            'data.decision_maker_level' => 'sometimes|string|max:255',
            'data.buying_influence' => 'sometimes|string|max:255',
            'data.gdpr_consent_status' => 'sometimes|string|max:50',
            'data.user_id' => 'sometimes|uuid|exists:users,id',
        ]);

        $orgId = app(TenantContext::class)->getTenantId();

        DB::transaction(function () use ($validated, $orgId) {
            Contact::whereIn('id', $validated['ids'])
                ->when($orgId, function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->update($validated['data']);
        });

        return response()->json([
            'message' => 'Bulk contacts update completed successfully.',
        ]);
    }

    // Bulk Tagging
    public function bulkTag(Request $request): JsonResponse
    {
        Gate::authorize('update', Contact::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:crm_contacts,id',
            'tags' => 'required|array',
            'tags.*' => 'required|string',
            'action' => 'required|string|in:add,remove',
        ]);

        $orgId = app(TenantContext::class)->getTenantId();

        DB::transaction(function () use ($validated, $orgId) {
            $contacts = Contact::whereIn('id', $validated['ids'])
                ->when($orgId, function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->get();

            foreach ($contacts as $contact) {
                foreach ($validated['tags'] as $tagName) {
                    $tag = \App\Domain\CRM\Models\Tag::firstOrCreate([
                        'organization_id' => $orgId ?? $contact->organization_id,
                        'name' => $tagName,
                    ]);

                    if ($validated['action'] === 'add') {
                        $contact->tags()->syncWithoutDetaching([$tag->id => ['taggable_type' => Contact::class]]);
                    } else {
                        $contact->tags()->detach($tag->id);
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Bulk tagging completed successfully.',
        ]);
    }

    // Bulk Archive (soft delete)
    public function bulkArchive(Request $request): JsonResponse
    {
        Gate::authorize('delete', Contact::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:crm_contacts,id',
        ]);

        $orgId = app(TenantContext::class)->getTenantId();

        DB::transaction(function () use ($validated, $orgId) {
            Contact::whereIn('id', $validated['ids'])
                ->when($orgId, function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->delete();
        });

        return response()->json([
            'message' => 'Bulk contacts archived successfully.',
        ]);
    }

    // Timeline Integration
    public function timeline(string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('view', $contact);

        // Fetch direct activities mapped to this contact or polymorphic logs
        $activities = $contact->activities()->with(['owner'])->orderBy('created_at', 'desc')->get();
        
        $timeline = $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'type' => 'activity',
                'activity_type' => $activity->type,
                'title' => $activity->subject,
                'description' => $activity->description,
                'is_completed' => $activity->is_completed,
                'due_at' => $activity->due_at?->toIso8601String(),
                'created_at' => $activity->created_at?->toIso8601String(),
                'user' => $activity->owner ? [
                    'name' => $activity->owner->name,
                    'email' => $activity->owner->email,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $timeline,
        ]);
    }

    // Manage multiple company associations
    public function storeCompanyAssociation(Request $request, string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:crm_companies,id',
            'role' => 'nullable|string|max:255',
        ]);

        $orgId = app(TenantContext::class)->getTenantId() ?? $contact->organization_id;

        $association = ContactCompanyAssociation::updateOrCreate([
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
            'company_id' => $validated['company_id'],
        ], [
            'role' => $validated['role'] ?? 'Secondary',
        ]);

        return response()->json([
            'message' => 'Company association stored successfully.',
            'data' => $association,
        ], 201);
    }

    public function destroyCompanyAssociation(string $id, string $companyId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        ContactCompanyAssociation::where('contact_id', $contact->id)
            ->where('company_id', $companyId)
            ->delete();

        return response()->json([
            'message' => 'Company association removed successfully.',
        ]);
    }

    // Manage multiple contact methods
    public function storeMethod(Request $request, string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'type' => 'required|string|in:email,phone',
            'value' => 'required|string|max:255',
            'label' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ]);

        $orgId = app(TenantContext::class)->getTenantId() ?? $contact->organization_id;

        if ($validated['is_primary'] ?? false) {
            // Demote other primary methods of the same type
            ContactMethod::where('contact_id', $contact->id)
                ->where('type', $validated['type'])
                ->update(['is_primary' => false]);
        }

        $method = ContactMethod::create(array_merge($validated, [
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
        ]));

        // Dispatch updated event
        app(\App\Services\EventBus\TransactionalOutboxInterface::class)->store(
            new ContactCommunicationUpdatedEvent($contact, $contact->communication_preferences ?? [])
        );

        return response()->json([
            'message' => 'Contact method added successfully.',
            'data' => $method,
        ], 201);
    }

    public function updateMethod(Request $request, string $id, string $methodId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'value' => 'sometimes|required|string|max:255',
            'label' => 'nullable|string|max:50',
            'is_primary' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ]);

        $method = ContactMethod::where('contact_id', $contact->id)->where('id', $methodId)->firstOrFail();

        if ($validated['is_primary'] ?? false) {
            ContactMethod::where('contact_id', $contact->id)
                ->where('type', $method->type)
                ->update(['is_primary' => false]);
        }

        $method->update($validated);

        // Dispatch updated event
        app(\App\Services\EventBus\TransactionalOutboxInterface::class)->store(
            new ContactCommunicationUpdatedEvent($contact, $contact->communication_preferences ?? [])
        );

        return response()->json([
            'message' => 'Contact method updated successfully.',
            'data' => $method,
        ]);
    }

    public function destroyMethod(string $id, string $methodId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        ContactMethod::where('contact_id', $contact->id)->where('id', $methodId)->delete();

        // Dispatch updated event
        app(\App\Services\EventBus\TransactionalOutboxInterface::class)->store(
            new ContactCommunicationUpdatedEvent($contact, $contact->communication_preferences ?? [])
        );

        return response()->json([
            'message' => 'Contact method removed successfully.',
        ]);
    }

    // Manage relationships
    public function storeRelationship(Request $request, string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'related_contact_id' => 'required|uuid|exists:crm_contacts,id',
            'type' => 'required|string|in:manager,assistant,colleague,executive,decision_maker,influencer,technical_contact,legal_contact,finance_contact,emergency_contact',
        ]);

        // Prevent circular graphs!
        if (ContactRelationship::wouldCreateCircularGraph($contact->id, $validated['related_contact_id'])) {
            return response()->json([
                'message' => 'Circular relationship graph detected. This relationship cannot be established to avoid loops.',
            ], 422);
        }

        $orgId = app(TenantContext::class)->getTenantId() ?? $contact->organization_id;

        $relationship = ContactRelationship::create(array_merge($validated, [
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
        ]));

        // Dispatch event
        app(\App\Services\EventBus\TransactionalOutboxInterface::class)->store(
            new ContactRelationshipUpdatedEvent($contact, $relationship, 'created')
        );

        // Notify owner about important relationship changes (e.g. manager assigned)
        $owner = $contact->owner;
        if ($owner && in_array($relationship->type, ['manager', 'decision_maker'])) {
            $related = Contact::find($relationship->related_contact_id);
            $owner->notify(new \App\Notifications\EnterpriseNotification(
                "Important Contact Relationship Assigned",
                "Contact {$contact->full_name} has been linked to {$related->full_name} as {$relationship->type}.",
                'info',
                'crm',
                'normal',
                $contact->organization_id
            ));
        }

        return response()->json([
            'message' => 'Contact relationship established successfully.',
            'data' => $relationship,
        ], 201);
    }

    public function updateRelationship(Request $request, string $id, string $relationshipId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'type' => 'required|string|in:manager,assistant,colleague,executive,decision_maker,influencer,technical_contact,legal_contact,finance_contact,emergency_contact',
        ]);

        $relationship = ContactRelationship::where('contact_id', $contact->id)->where('id', $relationshipId)->firstOrFail();
        $relationship->update($validated);

        // Dispatch event
        app(\App\Services\EventBus\TransactionalOutboxInterface::class)->store(
            new ContactRelationshipUpdatedEvent($contact, $relationship, 'updated')
        );

        return response()->json([
            'message' => 'Contact relationship updated successfully.',
            'data' => $relationship,
        ]);
    }

    public function destroyRelationship(string $id, string $relationshipId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $relationship = ContactRelationship::where('contact_id', $contact->id)->where('id', $relationshipId)->firstOrFail();
        $relationship->delete();

        // Dispatch event
        app(\App\Services\EventBus\TransactionalOutboxInterface::class)->store(
            new ContactRelationshipUpdatedEvent($contact, $relationship, 'deleted')
        );

        return response()->json([
            'message' => 'Contact relationship removed successfully.',
        ]);
    }

    // Duplicate Detection Report
    public function detectDuplicates(Request $request, ?string $id = null): JsonResponse
    {
        $detector = app(\App\Domain\CRM\Services\ContactDuplicateDetector::class);

        if ($id) {
            $contact = $this->service->getContact($id);
            if (!$contact) {
                return response()->json(['message' => 'Contact not found.'], 404);
            }
            Gate::authorize('view', $contact);

            $duplicates = $detector->findDuplicates([
                'email' => $contact->email,
                'phone' => $contact->phone,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company_id' => $contact->company_id,
            ], $contact->id);

            return response()->json(['data' => $duplicates]);
        }

        Gate::authorize('viewAny', Contact::class);
        $scanned = $detector->scanDuplicates();

        return response()->json(['data' => $scanned]);
    }

    // Merge Wizard Endpoint
    public function merge(Request $request): JsonResponse
    {
        Gate::authorize('update', Contact::class);

        $validated = $request->validate([
            'master_id' => 'required|uuid|exists:crm_contacts,id',
            'duplicate_ids' => 'required|array',
            'duplicate_ids.*' => 'required|uuid|exists:crm_contacts,id',
            'override_data' => 'nullable|array',
        ]);

        $mergeService = app(\App\Domain\CRM\Services\ContactMergeService::class);
        $master = $mergeService->merge(
            $validated['master_id'],
            $validated['duplicate_ids'],
            $validated['override_data'] ?? []
        );

        return response()->json([
            'message' => 'Contacts merged successfully.',
            'data' => $master,
        ]);
    }

    // Import Preview
    public function importPreview(Request $request): JsonResponse
    {
        Gate::authorize('create', Contact::class);

        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.first_name' => 'required|string|max:255',
            'rows.*.last_name' => 'required|string|max:255',
            'rows.*.email' => 'required|email',
            'rows.*.phone' => 'nullable|string',
        ]);

        $importer = app(\App\Domain\CRM\Services\ContactImportExportService::class);
        $preview = $importer->previewImport($validated['rows']);

        return response()->json(['data' => $preview]);
    }

    // Import Execute
    public function importExecute(Request $request): JsonResponse
    {
        Gate::authorize('create', Contact::class);

        $validated = $request->validate([
            'rows' => 'required|array',
            'ignore_duplicates' => 'nullable|boolean',
        ]);

        $importer = app(\App\Domain\CRM\Services\ContactImportExportService::class);
        $result = $importer->executeImport($validated['rows'], $validated['ignore_duplicates'] ?? false);

        return response()->json([
            'message' => 'Import executed successfully.',
            'data' => $result,
        ]);
    }

    // Address Manager Endpoints
    public function storeAddress(Request $request, string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'type' => 'required|string|in:billing,shipping,office,home,branch,warehouse,emergency,primary',
            'is_primary' => 'nullable|boolean',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'county' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'required|string|max:100',
            'coordinates' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
        ]);

        $orgId = app(TenantContext::class)->getTenantId() ?? $contact->organization_id;

        if ($validated['is_primary'] ?? false) {
            \App\Domain\CRM\Models\ContactAddress::where('contact_id', $contact->id)
                ->update(['is_primary' => false]);
        }

        $address = \App\Domain\CRM\Models\ContactAddress::create(array_merge($validated, [
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
        ]));

        return response()->json([
            'message' => 'Address stored successfully.',
            'data' => $address,
        ], 201);
    }

    public function updateAddress(Request $request, string $id, string $addressId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'type' => 'sometimes|required|string|in:billing,shipping,office,home,branch,warehouse,emergency,primary',
            'is_primary' => 'nullable|boolean',
            'street' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'county' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'sometimes|required|string|max:100',
            'coordinates' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
        ]);

        $address = \App\Domain\CRM\Models\ContactAddress::where('contact_id', $contact->id)->where('id', $addressId)->firstOrFail();

        if ($validated['is_primary'] ?? false) {
            \App\Domain\CRM\Models\ContactAddress::where('contact_id', $contact->id)
                ->update(['is_primary' => false]);
        }

        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully.',
            'data' => $address,
        ]);
    }

    public function destroyAddress(string $id, string $addressId): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        \App\Domain\CRM\Models\ContactAddress::where('contact_id', $contact->id)->where('id', $addressId)->delete();

        return response()->json([
            'message' => 'Address removed successfully.',
        ]);
    }

    // Consent Center Endpoint
    public function storeConsent(Request $request, string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'channel' => 'required|string|in:email,sms,whatsapp,phone',
            'status' => 'required|string|in:granted,revoked,pending',
            'purpose' => 'required|string|in:marketing,transactional,support',
            'notes' => 'nullable|string',
            'source' => 'nullable|string|max:100',
        ]);

        $orgId = app(TenantContext::class)->getTenantId() ?? $contact->organization_id;

        $consent = \App\Domain\CRM\Models\ContactConsent::create(array_merge($validated, [
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'consented_at' => now(),
        ]));

        // Sync with primary attributes
        $statusKey = "{$validated['channel']}_consent";
        if (in_array($statusKey, ['sms_consent', 'whatsapp_consent', 'email_consent'])) {
            $contact->update([
                $statusKey => ($validated['status'] === 'granted')
            ]);
        }

        // Dispatch domain event
        app(\App\Contracts\EventBus::class)->dispatch(new \App\Domain\CRM\Events\ContactConsentChanged(
            $contact,
            $validated['channel'],
            $validated['status'],
            $validated['purpose']
        ));

        return response()->json([
            'message' => 'Consent record captured successfully.',
            'data' => $consent,
        ], 201);
    }
}
