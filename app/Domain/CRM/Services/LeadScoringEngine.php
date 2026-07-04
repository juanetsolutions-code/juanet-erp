<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Models\LeadActivity;
use Illuminate\Support\Facades\DB;

class LeadScoringEngine
{
    /**
     * Calculate and save the score of a lead based on demographic and behavioral indicators.
     */
    public function updateScore(Lead $lead): Lead
    {
        $oldScore = $lead->score;

        $demographic = $this->calculateDemographicScore($lead);
        $behavioral = $this->calculateBehavioralScore($lead);
        $manual = $this->getManualOffset($lead);

        $totalScore = $demographic['total'] + $behavioral['total'] + $manual;

        // Ensure score stays within reasonable bounds (0 to 100 or higher)
        $totalScore = max(0, $totalScore);

        $aiRecommendation = $this->generateAiPlaceholder($totalScore, $lead);

        $breakdown = [
            'demographic' => $demographic,
            'behavioral' => $behavioral,
            'manual' => $manual,
            'ai_prediction' => $aiRecommendation,
        ];

        DB::transaction(function () use ($lead, $totalScore, $breakdown, $oldScore) {
            $lead->score = $totalScore;
            $lead->score_breakdown = $breakdown;
            $lead->save();

            // Record Timeline Activity if score changes
            if ($oldScore !== $totalScore) {
                LeadActivity::create([
                    'organization_id' => $lead->organization_id,
                    'lead_id' => $lead->id,
                    'user_id' => null,
                    'type' => 'workflow',
                    'description' => "Lead score recalculated. Old: {$oldScore}, New: {$totalScore}.",
                    'properties' => [
                        'old_score' => $oldScore,
                        'new_score' => $totalScore,
                        'breakdown' => $breakdown,
                    ],
                ]);
            }
        });

        return $lead;
    }

    /**
     * Calculate demographic score.
     */
    protected function calculateDemographicScore(Lead $lead): array
    {
        $score = 0;
        $reasons = [];

        // 1. Email domain check
        if (!empty($lead->email)) {
            $domain = substr(strrchr($lead->email, "@"), 1);
            $freeDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'mail.com'];
            
            if (in_array(strtolower($domain), $freeDomains)) {
                $score += 5;
                $reasons[] = 'Commercial free email domain (+5)';
            } else {
                $score += 20;
                $reasons[] = "Corporate/Enterprise domain [{$domain}] (+20)";
            }
        }

        // 2. Phone number check
        if (!empty($lead->phone)) {
            $score += 10;
            $reasons[] = 'Phone number provided (+10)';
        }

        // 3. Company check (direct company_id, or company name in custom fields)
        if ($lead->company_id || !empty($lead->company?->name)) {
            $score += 15;
            $reasons[] = 'Associated company profile exists (+15)';
        }

        // 4. Job Title check (if contact associated has a high-value title, or in custom fields)
        $jobTitle = $lead->contact?->job_title;
        if (empty($jobTitle) && is_array($lead->custom_fields)) {
            $jobTitle = $lead->custom_fields['job_title'] ?? '';
        }

        if (!empty($jobTitle)) {
            $titleUpper = strtoupper($jobTitle);
            $decisionMakerKeywords = ['CEO', 'CTO', 'CFO', 'VP', 'VICE PRESIDENT', 'DIRECTOR', 'FOUNDER', 'PRESIDENT', 'PARTNER'];
            
            $isDecisionMaker = false;
            foreach ($decisionMakerKeywords as $keyword) {
                if (str_contains($titleUpper, $keyword)) {
                    $isDecisionMaker = true;
                    break;
                }
            }

            if ($isDecisionMaker) {
                $score += 25;
                $reasons[] = "Decision maker job title [{$jobTitle}] (+25)";
            } else {
                $score += 10;
                $reasons[] = "Manager/Specialist job title [{$jobTitle}] (+10)";
            }
        }

        return [
            'total' => $score,
            'details' => $reasons,
        ];
    }

    /**
     * Calculate behavioral and engagement score based on activities.
     */
    protected function calculateBehavioralScore(Lead $lead): array
    {
        $score = 0;
        $reasons = [];

        // 1. Lead source scoring
        $sourceName = $lead->leadSource?->name;
        if ($sourceName) {
            $sourceLower = strtolower($sourceName);
            if (str_contains($sourceLower, 'referral') || str_contains($sourceLower, 'direct')) {
                $score += 20;
                $reasons[] = "High-intent lead source [{$sourceName}] (+20)";
            } else if (str_contains($sourceLower, 'website') || str_contains($sourceLower, 'inbound')) {
                $score += 15;
                $reasons[] = "Organic lead source [{$sourceName}] (+15)";
            } else {
                $score += 5;
                $reasons[] = "Standard lead source [{$sourceName}] (+5)";
            }
        }

        // 2. Activity count scoring
        $activityCount = LeadActivity::where('lead_id', $lead->id)->count();
        if ($activityCount > 0) {
            $points = min(30, $activityCount * 5); // 5 points per activity, capped at 30
            $score += $points;
            $reasons[] = "Recorded {$activityCount} interactions (+{$points})";
        }

        // 3. Status-based engagement signals
        $status = strtolower($lead->status);
        if ($status === 'meeting_scheduled') {
            $score += 20;
            $reasons[] = 'Engagement: Meeting scheduled (+20)';
        } else if ($status === 'proposal_sent' || $status === 'negotiation') {
            $score += 35;
            $reasons[] = 'Engagement: Under commercial proposal/negotiation (+35)';
        }

        return [
            'total' => $score,
            'details' => $reasons,
        ];
    }

    /**
     * Retrieve any manual offset score defined on custom fields.
     */
    protected function getManualOffset(Lead $lead): int
    {
        if (is_array($lead->custom_fields) && isset($lead->custom_fields['manual_score_offset'])) {
            return (int) $lead->custom_fields['manual_score_offset'];
        }
        return 0;
    }

    /**
     * AI Prediction Placeholder generator.
     */
    protected function generateAiPlaceholder(int $totalScore, Lead $lead): array
    {
        if ($totalScore >= 70) {
            return [
                'conversion_probability' => 'High',
                'probability_percent' => rand(78, 96),
                'recommendation' => 'Critical prospect. Immediate follow-up required. Deliver custom proposal and schedule a decision call.',
                'action_item' => 'Send Follow-Up Proposal',
            ];
        }

        if ($totalScore >= 40) {
            return [
                'conversion_probability' => 'Medium',
                'probability_percent' => rand(41, 69),
                'recommendation' => 'Nurture prospect. Share localized customer case studies or invite to educational product webinars.',
                'action_item' => 'Nurture via case study email',
            ];
        }

        return [
            'conversion_probability' => 'Low',
            'probability_percent' => rand(8, 39),
            'recommendation' => 'Low-intent score. Keep in long-term drip marketing campaign for general educational updates.',
            'action_item' => 'Add to monthly newsletter drip',
        ];
    }
}
