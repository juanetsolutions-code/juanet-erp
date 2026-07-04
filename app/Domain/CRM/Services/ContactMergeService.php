<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\ContactMethod;
use App\Domain\CRM\Models\ContactAddress;
use App\Domain\CRM\Models\ContactConsent;
use App\Domain\CRM\Models\ContactRelationship;
use App\Domain\CRM\Models\ContactCompanyAssociation;
use App\Contracts\EventBus;
use App\Domain\CRM\Events\ContactMerged;
use Illuminate\Support\Facades\DB;

class ContactMergeService
{
    protected EventBus $eventBus;
    protected ContactHealthService $healthService;

    public function __construct(EventBus $eventBus, ContactHealthService $healthService)
    {
        $this->eventBus = $eventBus;
        $this->healthService = $healthService;
    }

    /**
     * Merge multiple duplicate contacts into a master contact.
     */
    public function merge(string $masterId, array $duplicateIds, array $overrideData = []): Contact
    {
        return DB::transaction(function () use ($masterId, $duplicateIds, $overrideData) {
            $master = Contact::findOrFail($masterId);
            $duplicates = Contact::whereIn('id', $duplicateIds)->get();

            if ($duplicates->isEmpty()) {
                throw new \InvalidArgumentException("No valid duplicate contacts found to merge.");
            }

            // 1. Update master contact with overrides (e.g. if we want to pick fields from duplicates)
            if (!empty($overrideData)) {
                $master->update($overrideData);
            }

            // 2. Re-associate related contact methods
            ContactMethod::whereIn('contact_id', $duplicateIds)
                ->update(['contact_id' => $master->id]);

            // Demote duplicate primary flags so we don't have multiple primary methods of same type
            $types = ['email', 'phone'];
            foreach ($types as $type) {
                $hasPrimary = ContactMethod::where('contact_id', $master->id)
                    ->where('type', $type)
                    ->where('is_primary', true)
                    ->exists();

                if ($hasPrimary) {
                    ContactMethod::where('contact_id', $master->id)
                        ->where('type', $type)
                        ->where('is_primary', false) // fallback safety
                        ->update(['is_primary' => false]);
                }
            }

            // 3. Re-associate related addresses
            ContactAddress::whereIn('contact_id', $duplicateIds)
                ->update(['contact_id' => $master->id]);

            // Demote duplicate primary addresses
            ContactAddress::where('contact_id', $master->id)
                ->where('is_primary', true)
                ->skip(1)
                ->take(100)
                ->update(['is_primary' => false]);

            // 4. Re-associate GDPR Consent History
            ContactConsent::whereIn('contact_id', $duplicateIds)
                ->update(['contact_id' => $master->id]);

            // 5. Re-associate Company Associations
            foreach ($duplicates as $duplicate) {
                ContactCompanyAssociation::where('contact_id', $duplicate->id)
                    ->get()
                    ->each(function ($assoc) use ($master) {
                        $exists = ContactCompanyAssociation::where('contact_id', $master->id)
                            ->where('company_id', $assoc->company_id)
                            ->exists();

                        if ($exists) {
                            $assoc->delete(); // discard duplicate associations
                        } else {
                            $assoc->update(['contact_id' => $master->id]);
                        }
                    });
            }

            // 6. Re-associate Relationships (Graph edges)
            ContactRelationship::whereIn('contact_id', $duplicateIds)
                ->update(['contact_id' => $master->id]);

            ContactRelationship::whereIn('related_contact_id', $duplicateIds)
                ->update(['related_contact_id' => $master->id]);

            // Discard circular or self-referential relationships formed after merge
            ContactRelationship::where('contact_id', $master->id)
                ->where('related_contact_id', $master->id)
                ->delete();

            // 7. Re-associate Timeline Activities
            DB::table('crm_activities')
                ->whereIn('loggable_id', $duplicateIds)
                ->where('loggable_type', Contact::class)
                ->update(['loggable_id' => $master->id]);

            // 8. Merge custom fields (JSONB deep merge)
            $mergedCustomFields = $master->custom_fields ?? [];
            foreach ($duplicates as $duplicate) {
                if (is_array($duplicate->custom_fields)) {
                    $mergedCustomFields = array_merge($duplicate->custom_fields, $mergedCustomFields);
                }
            }
            $master->update(['custom_fields' => $mergedCustomFields]);

            // 9. Dispatch Merge Domain Event
            $this->eventBus->dispatch(new ContactMerged($master, $duplicateIds));

            // 10. Recalculate health score for master contact after activities and methods are merged
            $this->healthService->calculate($master);

            // 11. Soft-delete duplicate contacts
            Contact::whereIn('id', $duplicateIds)->delete();

            return $master;
        });
    }
}
