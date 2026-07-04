<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Models\ContactMethod;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContactImportExportService
{
    protected TenantContext $tenantContext;
    protected ContactDuplicateDetector $duplicateDetector;

    public function __construct(TenantContext $tenantContext, ContactDuplicateDetector $duplicateDetector)
    {
        $this->tenantContext = $tenantContext;
        $this->duplicateDetector = $duplicateDetector;
    }

    /**
     * Preview an incoming import payload.
     * Performs dry-run validations, detects potential duplicates, and formats report.
     */
    public function previewImport(array $rows): array
    {
        $report = [
            'total_rows' => count($rows),
            'valid_count' => 0,
            'invalid_count' => 0,
            'duplicate_count' => 0,
            'rows' => [],
        ];

        foreach ($rows as $index => $row) {
            // Rules
            $validator = Validator::make($row, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email',
                'phone' => 'nullable|string',
            ]);

            $isInvalid = $validator->fails();
            $errors = $isInvalid ? $validator->errors()->all() : [];

            $potentialDuplicates = [];
            if (!$isInvalid) {
                $potentialDuplicates = $this->duplicateDetector->findDuplicates([
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                ]);
            }

            $isDuplicate = !empty($potentialDuplicates);

            if ($isInvalid) {
                $report['invalid_count']++;
            } elseif ($isDuplicate) {
                $report['duplicate_count']++;
                $report['valid_count']++;
            } else {
                $report['valid_count']++;
            }

            $report['rows'][] = [
                'index' => $index,
                'data' => $row,
                'is_valid' => !$isInvalid,
                'errors' => $errors,
                'is_duplicate' => $isDuplicate,
                'duplicate_candidates' => array_map(function ($dup) {
                    return [
                        'id' => $dup['contact']->id,
                        'name' => $dup['contact']->full_name,
                        'email' => $dup['contact']->email,
                        'confidence' => $dup['confidence'],
                    ];
                }, $potentialDuplicates),
            ];
        }

        return $report;
    }

    /**
     * Execute import within a reversible transaction.
     */
    public function executeImport(array $rows, bool $ignoreDuplicates = false): array
    {
        $orgId = $this->tenantContext->getTenantId();
        $importedIds = [];
        $skippedCount = 0;

        DB::transaction(function () use ($rows, $ignoreDuplicates, $orgId, &$importedIds, &$skippedCount) {
            foreach ($rows as $row) {
                // Dry-run checks if duplicates shouldn't be imported
                if (!$ignoreDuplicates) {
                    $dups = $this->duplicateDetector->findDuplicates([
                        'email' => $row['email'] ?? null,
                        'phone' => $row['phone'] ?? null,
                        'first_name' => $row['first_name'] ?? null,
                        'last_name' => $row['last_name'] ?? null,
                    ]);

                    if (!empty($dups)) {
                        $skippedCount++;
                        continue;
                    }
                }

                // Create the contact
                $contact = Contact::create([
                    'organization_id' => $orgId,
                    'company_id' => $row['company_id'] ?? null,
                    'first_name' => $row['first_name'] ?? null,
                    'middle_name' => $row['middle_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'preferred_name' => $row['preferred_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'job_title' => $row['job_title'] ?? null,
                    'department' => $row['department'] ?? null,
                    'decision_maker_level' => $row['decision_maker_level'] ?? null,
                    'buying_influence' => $row['buying_influence'] ?? null,
                    'preferred_language' => $row['preferred_language'] ?? 'en',
                    'timezone' => $row['timezone'] ?? 'UTC',
                    'custom_fields' => $row['custom_fields'] ?? [],
                ]);

                // Create default primary contact methods
                if ($contact->email) {
                    ContactMethod::create([
                        'organization_id' => $orgId,
                        'contact_id' => $contact->id,
                        'type' => 'email',
                        'value' => $contact->email,
                        'label' => 'work',
                        'is_primary' => true,
                        'is_verified' => true,
                    ]);
                }

                if ($contact->phone) {
                    ContactMethod::create([
                        'organization_id' => $orgId,
                        'contact_id' => $contact->id,
                        'type' => 'phone',
                        'value' => $contact->phone,
                        'label' => 'work',
                        'is_primary' => true,
                        'is_verified' => false,
                    ]);
                }

                $importedIds[] = $contact->id;
            }
        });

        return [
            'imported_count' => count($importedIds),
            'skipped_count' => $skippedCount,
            'imported_ids' => $importedIds,
        ];
    }

    /**
     * Rollback a specific batch of imported contacts.
     */
    public function rollbackImport(array $importedIds): void
    {
        DB::transaction(function () use ($importedIds) {
            Contact::whereIn('id', $importedIds)->forceDelete();
        });
    }

    /**
     * Export contacts with full graph relations and custom fields.
     */
    public function export(array $contactIds, string $format = 'json'): string|array
    {
        $contacts = Contact::whereIn('id', $contactIds)
            ->with(['company', 'addresses', 'contactMethods'])
            ->get();

        $data = $contacts->map(function ($c) {
            return [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'middle_name' => $c->middle_name,
                'last_name' => $c->last_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'job_title' => $c->job_title,
                'department' => $c->department,
                'company' => $c->company ? $c->company->name : null,
                'tier' => $c->tier,
                'segment' => $c->segment,
                'lifecycle_stage' => $c->lifecycle_stage,
                'status' => $c->status,
                'health_score' => $c->health_score,
                'health_status' => $c->health_status,
                'custom_fields' => $c->custom_fields,
                'addresses' => $c->addresses->map(function ($a) {
                    return [
                        'type' => $a->type,
                        'street' => $a->street,
                        'city' => $a->city,
                        'country' => $a->country,
                    ];
                })->toArray(),
            ];
        })->toArray();

        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        if ($format === 'csv') {
            $handle = fopen('php://temp', 'r+');
            if (!empty($data)) {
                // Header
                fputcsv($handle, array_keys($data[0]));
                // Rows
                foreach ($data as $row) {
                    $cleanRow = array_map(function ($v) {
                        return is_array($v) ? json_encode($v) : $v;
                    }, $row);
                    fputcsv($handle, $cleanRow);
                }
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            return $csv;
        }

        return $data;
    }
}
