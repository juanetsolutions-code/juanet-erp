<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Contact;
use Illuminate\Support\Facades\DB;
use App\Services\TenantContext;

class ContactDuplicateDetector
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Find potential duplicates for a given contact or raw input data.
     */
    public function findDuplicates(array $data, ?string $excludeContactId = null): array
    {
        $orgId = $this->tenantContext->getTenantId();
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        $companyId = $data['company_id'] ?? null;

        $candidates = Contact::query()
            ->when($orgId, function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->when($excludeContactId, function ($q) use ($excludeContactId) {
                $q->where('id', '!=', $excludeContactId);
            })
            ->get();

        $duplicates = [];

        foreach ($candidates as $candidate) {
            $confidence = 0;
            $matchReasons = [];

            // 1. Direct Email Match (100% confidence)
            if ($email && strtolower($candidate->email) === strtolower($email)) {
                $confidence += 85;
                $matchReasons[] = 'Email Match';
            }

            // 2. Direct Phone Match
            if ($phone && preg_replace('/\D/', '', $candidate->phone) === preg_replace('/\D/', '', $phone)) {
                $confidence += 75;
                $matchReasons[] = 'Phone Match';
            }

            // 3. Name Similarity using Levenshtein distance
            if ($firstName && $lastName) {
                $candidateFullName = strtolower($candidate->first_name . ' ' . $candidate->last_name);
                $inputFullName = strtolower($firstName . ' ' . $lastName);

                $lev = levenshtein($candidateFullName, $inputFullName);
                $maxLen = max(strlen($candidateFullName), strlen($inputFullName));
                
                if ($maxLen > 0) {
                    $similarity = 1 - ($lev / $maxLen);
                    if ($similarity > 0.8) {
                        $confidence += 40;
                        $matchReasons[] = 'Name Similarity (' . round($similarity * 100) . '%)';
                    }
                }
            }

            // 4. Company Match helper
            if ($companyId && $candidate->company_id === $companyId) {
                $confidence += 15;
                $matchReasons[] = 'Same Company';
            }

            // Final confidence formatting
            $finalConfidence = min(100, $confidence);

            if ($finalConfidence >= 50) {
                $duplicates[] = [
                    'contact' => $candidate,
                    'confidence' => $finalConfidence,
                    'match_reasons' => $matchReasons,
                ];
            }
        }

        // Sort by confidence descending
        usort($duplicates, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $duplicates;
    }

    /**
     * Scan the entire contact list for duplicate pairs.
     */
    public function scanDuplicates(): array
    {
        $orgId = $this->tenantContext->getTenantId();
        $contacts = Contact::query()
            ->when($orgId, function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->get();

        $scannedPairs = [];
        $visited = [];

        foreach ($contacts as $contact) {
            $visited[$contact->id] = true;
            $duplicates = $this->findDuplicates([
                'email' => $contact->email,
                'phone' => $contact->phone,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company_id' => $contact->company_id,
            ], $contact->id);

            foreach ($duplicates as $dup) {
                $dupContact = $dup['contact'];
                if (isset($visited[$dupContact->id])) {
                    continue; // Avoid duplicate pairs in report
                }

                $scannedPairs[] = [
                    'primary' => $contact,
                    'duplicate' => $dupContact,
                    'confidence' => $dup['confidence'],
                    'match_reasons' => $dup['match_reasons'],
                ];
            }
        }

        return $scannedPairs;
    }
}
