<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class LeadImportExportService
{
    protected LeadStateMachine $stateMachine;
    protected LeadScoringEngine $scoringEngine;
    protected DuplicateDetector $duplicateDetector;

    public function __construct(
        LeadStateMachine $stateMachine,
        LeadScoringEngine $scoringEngine,
        DuplicateDetector $duplicateDetector
    ) {
        $this->stateMachine = $stateMachine;
        $this->scoringEngine = $scoringEngine;
        $this->duplicateDetector = $duplicateDetector;
    }

    /**
     * Export leads collection to CSV format.
     */
    public function exportToCsv(Collection $leads): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // CSV headers
        fputcsv($handle, [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Status',
            'Score',
            'Created At',
            'Updated At'
        ]);

        foreach ($leads as $lead) {
            fputcsv($handle, [
                $lead->id,
                $lead->name,
                $lead->email,
                $lead->phone,
                $lead->status,
                $lead->score,
                $lead->created_at?->toIso8601String(),
                $lead->updated_at?->toIso8601String()
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Import leads from CSV data. Supports validation, dry-runs, and duplicate warnings.
     */
    public function importFromCsv(
        string $csvContent,
        string $organizationId,
        ?string $importedBy = null,
        bool $dryRun = false
    ): array {
        $rows = array_map('str_getcsv', explode("\n", trim($csvContent)));
        if (empty($rows)) {
            return ['success' => false, 'message' => 'The uploaded CSV file is empty.'];
        }

        // Parse headers
        $headers = array_shift($rows);
        $headers = array_map(function ($h) {
            return strtolower(trim(str_replace([' ', '_', '-'], '', $h)));
        }, $headers);

        $nameIdx = array_search('name', $headers);
        $emailIdx = array_search('email', $headers);
        $phoneIdx = array_search('phone', $headers);
        $statusIdx = array_search('status', $headers);

        if ($nameIdx === false || $emailIdx === false) {
            return [
                'success' => false,
                'message' => 'Required columns "Name" and "Email" were not found in the CSV headers.'
            ];
        }

        $importBatchId = 'batch_' . Str::uuid()->toString();
        $importedLeads = [];
        $duplicateMatches = [];
        $errors = [];
        $previewRows = [];

        foreach ($rows as $index => $row) {
            if (empty($row) || count($row) < 2) {
                continue;
            }

            $name = trim($row[$nameIdx] ?? '');
            $email = trim($row[$emailIdx] ?? '');
            $phone = trim($row[$phoneIdx ?? -1] ?? '');
            $status = trim($row[$statusIdx ?? -1] ?? 'new');

            // Enforce basic validation
            if (empty($name) || empty($email)) {
                $errors[] = [
                    'row' => $index + 2,
                    'message' => 'Missing required lead name or email address.'
                ];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = [
                    'row' => $index + 2,
                    'message' => "Invalid email address format: '{$email}'."
                ];
                continue;
            }

            // Create temporary lead instance to check for duplicates
            $tempLead = new Lead([
                'organization_id' => $organizationId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: null,
                'status' => $status ?: 'new',
            ]);

            $isDuplicate = Lead::where('organization_id', $organizationId)
                ->where(function ($q) use ($email, $phone) {
                    $q->where('email', $email);
                    if (!empty($phone)) {
                        $q->orWhere('phone', $phone);
                    }
                })
                ->exists();

            if ($dryRun) {
                $previewRows[] = [
                    'row' => $index + 2,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'status' => $status ?: 'new',
                    'is_duplicate' => $isDuplicate,
                    'warning' => $isDuplicate ? 'Duplicate of existing lead' : null,
                ];
            } else {
                try {
                    DB::transaction(function () use (
                        $tempLead,
                        $isDuplicate,
                        $organizationId,
                        $importedBy,
                        $importBatchId,
                        &$importedLeads
                    ) {
                        // Persist lead
                        $tempLead->save();

                        // Set batch property on custom_fields for easy rollback
                        $tempLead->custom_fields = array_merge(
                            is_array($tempLead->custom_fields) ? $tempLead->custom_fields : [],
                            ['import_batch' => $importBatchId]
                        );
                        $tempLead->save();

                        // Create timeline log
                        LeadActivity::create([
                            'organization_id' => $organizationId,
                            'lead_id' => $tempLead->id,
                            'user_id' => $importedBy,
                            'type' => 'creation',
                            'description' => "Imported via CSV. Batch ID: {$importBatchId}.",
                            'properties' => [
                                'import_batch' => $importBatchId,
                            ],
                        ]);

                        // Initial Score
                        $this->scoringEngine->updateScore($tempLead);

                        // Initial Duplicate Check Flagging
                        $this->duplicateDetector->flagDuplicates($tempLead);

                        $importedLeads[] = $tempLead;
                    });
                } catch (Exception $e) {
                    $errors[] = [
                        'row' => $index + 2,
                        'message' => 'Database persistence failure: ' . $e->getMessage()
                    ];
                }
            }
        }

        return [
            'success' => true,
            'dry_run' => $dryRun,
            'batch_id' => $importBatchId,
            'total_processed' => count($rows),
            'imported_count' => count($importedLeads),
            'duplicate_matches_count' => count($duplicateMatches),
            'errors' => $errors,
            'preview' => $previewRows,
        ];
    }

    /**
     * Rollback (delete) all leads successfully created in a specific import batch.
     */
    public function rollbackBatch(string $batchId, string $organizationId): array
    {
        $leadsToRollback = Lead::where('organization_id', $organizationId)
            ->whereJsonContains('custom_fields->import_batch', $batchId)
            ->get();

        $count = 0;

        foreach ($leadsToRollback as $lead) {
            DB::transaction(function () use ($lead, &$count) {
                // Hard-delete leads created in import batch to completely erase trace
                $lead->forceDelete();
                $count++;
            });
        }

        return [
            'success' => true,
            'rolled_back_count' => $count,
            'batch_id' => $batchId
        ];
    }
}
