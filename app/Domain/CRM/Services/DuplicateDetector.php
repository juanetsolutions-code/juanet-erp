<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateDetector
{
    /**
     * Find potential duplicates of a lead based on Email, Phone, and Company similarities.
     */
    public function findDuplicates(Lead $lead): Collection
    {
        return Lead::where('organization_id', $lead->organization_id)
            ->where('id', '!=', $lead->id)
            ->where(function ($query) use ($lead) {
                // Exact email match
                if (!empty($lead->email)) {
                    $query->orWhere('email', $lead->email);
                }
                // Exact phone match
                if (!empty($lead->phone)) {
                    $query->orWhere('phone', $lead->phone);
                }
                // Name match
                if (!empty($lead->name)) {
                    $query->orWhere('name', 'like', "%{$lead->name}%");
                }
            })
            ->get();
    }

    /**
     * Audit lead and flag if potential duplicates are found.
     */
    public function flagDuplicates(Lead $lead): Lead
    {
        $duplicates = $this->findDuplicates($lead);

        if ($duplicates->isNotEmpty()) {
            DB::transaction(function () use ($lead, $duplicates) {
                $primaryDuplicate = $duplicates->first();

                $lead->duplicate_status = 'potential';
                $lead->duplicate_of_id = $primaryDuplicate->id;
                $lead->save();

                LeadActivity::create([
                    'organization_id' => $lead->organization_id,
                    'lead_id' => $lead->id,
                    'user_id' => null,
                    'type' => 'workflow',
                    'description' => "Potential duplicate detected. Matching lead: '{$primaryDuplicate->name}' ({$primaryDuplicate->email}).",
                    'properties' => [
                        'duplicate_lead_id' => $primaryDuplicate->id,
                        'duplicate_name' => $primaryDuplicate->name,
                        'duplicate_email' => $primaryDuplicate->email,
                    ],
                ]);
            });
        } else {
            $lead->duplicate_status = 'none';
            $lead->duplicate_of_id = null;
            $lead->save();
        }

        return $lead;
    }

    /**
     * Merge a duplicate lead into a primary lead, preserving chosen field values and consolidating history.
     */
    public function mergeLeads(Lead $primary, Lead $duplicate, array $overrideFields = [], ?string $mergedBy = null): Lead
    {
        if ($primary->organization_id !== $duplicate->organization_id) {
            throw new \InvalidArgumentException("Cannot merge leads belonging to different organizations.");
        }

        DB::transaction(function () use ($primary, $duplicate, $overrideFields, $mergedBy) {
            // Keep track of old state for audit
            $oldValues = $primary->toArray();

            // 1. Merge core properties (only override with duplicate fields if specified)
            $mergeableFields = ['phone', 'lead_source_id', 'company_id', 'contact_id', 'user_id', 'custom_fields'];
            $updatedData = [];

            foreach ($mergeableFields as $field) {
                if (isset($overrideFields[$field]) && $overrideFields[$field] === 'duplicate') {
                    $updatedData[$field] = $duplicate->$field;
                } else {
                    $updatedData[$field] = $primary->$field ?? $duplicate->$field;
                }
            }

            // Consolidate custom fields arrays
            $primaryCustom = is_array($primary->custom_fields) ? $primary->custom_fields : [];
            $duplicateCustom = is_array($duplicate->custom_fields) ? $duplicate->custom_fields : [];
            $updatedData['custom_fields'] = array_merge($duplicateCustom, $primaryCustom);

            $primary->update($updatedData);

            // 2. Reparent all Activities/Timeline from duplicate to primary
            LeadActivity::where('lead_id', $duplicate->id)->update([
                'lead_id' => $primary->id,
            ]);

            // 3. Mark duplicate as fully merged and soft delete it
            $duplicate->duplicate_status = 'duplicate';
            $duplicate->duplicate_of_id = $primary->id;
            $duplicate->status = 'archived';
            $duplicate->save();
            $duplicate->delete(); // Soft delete

            // 4. Record merge audit logs on primary lead's timeline
            LeadActivity::create([
                'organization_id' => $primary->organization_id,
                'lead_id' => $primary->id,
                'user_id' => $mergedBy,
                'type' => 'edit',
                'description' => "Merged duplicate lead '{$duplicate->name}' ({$duplicate->email}) into this profile.",
                'properties' => [
                    'merged_lead_id' => $duplicate->id,
                    'merged_name' => $duplicate->name,
                    'merged_email' => $duplicate->email,
                    'old_values' => $oldValues,
                ],
            ]);
        });

        return $primary;
    }
}
