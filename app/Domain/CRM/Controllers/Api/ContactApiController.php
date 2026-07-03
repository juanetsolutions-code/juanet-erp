<?php

namespace App\Domain\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Services\ContactService;
use App\Domain\CRM\Requests\CreateContactRequest;
use App\Domain\CRM\Resources\ContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

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

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $includes = explode(',', $request->input('include', ''));
        if (in_array('company', $includes)) {
            $query->with('company');
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

        $contact = $this->service->createContact($request->validated());

        return response()->json([
            'message' => 'Contact created successfully.',
            'data' => new ContactResource($contact),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $contact = $this->service->getContact($id);
        if (!$contact) {
            return response()->json(['message' => 'Contact not found.'], 404);
        }

        Gate::authorize('view', $contact);

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
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|uuid|exists:crm_companies,id',
            'job_title' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
        ]);

        $updated = $this->service->updateContact($id, $validated);

        return response()->json([
            'message' => 'Contact updated successfully.',
            'data' => new ContactResource($updated),
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
}
