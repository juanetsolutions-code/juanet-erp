<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Contact;
use App\Contracts\EventBus;
use App\Domain\CRM\Events\ContactHealthChanged;

class ContactHealthService
{
    protected EventBus $eventBus;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Calculate and save the health score, status, and breakdown for a contact.
     */
    public function calculate(Contact $contact): array
    {
        $base = 60; // Base score
        $breakdown = [
            'base_baseline' => $base,
            'engagement' => 0,
            'responsiveness' => 0,
            'meeting_frequency' => 0,
            'sales_influence' => 0,
            'relationship_strength' => 0,
            'profile_completeness' => 0,
            'outstanding_tasks' => 0,
        ];

        // 1. Engagement (Completed activities count)
        $completedCount = $contact->activities()->where('is_completed', true)->count();
        $engagementPoints = min($completedCount * 4, 20);
        $breakdown['engagement'] = $engagementPoints;

        // 2. Meeting Frequency
        $meetingsCount = $contact->activities()
            ->where('is_completed', true)
            ->where('type', 'meeting')
            ->count();
        $meetingPoints = min($meetingsCount * 6, 15);
        $breakdown['meeting_frequency'] = $meetingPoints;

        // 3. Responsiveness (Verified contact methods and consent status)
        $verifiedCount = $contact->contactMethods()->where('is_verified', true)->count();
        $responsivenessPoints = min($verifiedCount * 5, 10);
        if ($contact->gdpr_consent_status === 'granted') {
            $responsivenessPoints += 5;
        }
        $breakdown['responsiveness'] = $responsivenessPoints;

        // 4. Sales Influence (Buying role power)
        $influencePoints = 0;
        if ($contact->is_decision_maker || in_array(strtolower($contact->decision_maker_level ?? ''), ['c-level', 'vp', 'director'])) {
            $influencePoints += 10;
        }
        if ($contact->is_influencer || strtolower($contact->buying_influence ?? '') === 'champion') {
            $influencePoints += 5;
        }
        $breakdown['sales_influence'] = min($influencePoints, 15);

        // 5. Relationship Strength (Connected graph count & manager assigned)
        $relCount = $contact->relationships()->count();
        $relPoints = min($relCount * 4, 10);
        if ($contact->manager_id) {
            $relPoints = min($relPoints + 5, 15);
        }
        $breakdown['relationship_strength'] = $relPoints;

        // 6. Profile Completeness
        $completenessPoints = 0;
        if ($contact->preferred_language && $contact->timezone) {
            $completenessPoints += 3;
        }
        if ($contact->birthday || $contact->anniversary) {
            $completenessPoints += 2;
        }
        if ($contact->linkedin_url || $contact->twitter_url) {
            $completenessPoints += 3;
        }
        if (!empty($contact->custom_fields)) {
            $completenessPoints += 2;
        }
        $breakdown['profile_completeness'] = $completenessPoints;

        // Deductions for outstanding tasks
        $overdueCount = $contact->activities()
            ->where('is_completed', false)
            ->where('due_at', '<', now())
            ->count();
        $deductions = $overdueCount * 6;
        $breakdown['outstanding_tasks'] = -$deductions;

        // Net calculation
        $score = $base 
            + $breakdown['engagement'] 
            + $breakdown['meeting_frequency'] 
            + $breakdown['responsiveness'] 
            + $breakdown['sales_influence'] 
            + $breakdown['relationship_strength'] 
            + $breakdown['profile_completeness'] 
            - $deductions;

        // Restrict score to bounds [0, 100]
        $score = max(0, min(100, $score));

        // Determine health status
        $status = 'Healthy';
        if ($score < 40) {
            $status = 'Critical';
        } elseif ($score < 75) {
            $status = 'Warning';
        }

        // Detect Dormant status (no completed activity in the last 30 days)
        $lastActivity = $contact->activities()
            ->where('is_completed', true)
            ->orderBy('completed_at', 'desc')
            ->first();

        if ($lastActivity && $lastActivity->completed_at && $lastActivity->completed_at->lt(now()->subDays(30))) {
            $status = 'Dormant';
        }

        // Save back to contact model
        $oldScore = $contact->health_score;
        $oldStatus = $contact->health_status;

        $contact->update([
            'health_score' => $score,
            'health_status' => $status,
            'health_breakdown' => $breakdown,
        ]);

        // Dispatch health changed event if significant change
        if ($oldScore !== $score || $oldStatus !== $status) {
            $this->eventBus->dispatch(new ContactHealthChanged(
                $contact,
                $score,
                $status,
                $oldScore,
                $oldStatus
            ));
        }

        return [
            'score' => $score,
            'status' => $status,
            'breakdown' => $breakdown,
        ];
    }
}
