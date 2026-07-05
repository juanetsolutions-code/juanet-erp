<?php

namespace App\Services\Crm;

use App\Contracts\EventBus;
use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\VisitorSession;
use App\Domain\CRM\Models\VisitorPageView;
use App\Domain\CRM\Models\VisitorBehaviorProfile;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Events\BehaviorProfileUpdated;
use App\Domain\CRM\Events\EngagementScoreChanged;
use App\Domain\CRM\Events\PurchaseIntentDetected;
use App\Domain\CRM\Events\ServiceInterestUpdated;
use App\Domain\CRM\Events\ContentInterestUpdated;
use App\Domain\CRM\Events\VisitorValueEstimated;
use App\Domain\CRM\Events\BehaviorClassificationChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VisitorBehaviorService
{
    protected EventBus $eventBus;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Analyze a visitor's activity and update their rolling behavioral profile.
     */
    public function analyze(string $visitorId, ?string $correlationId = null): VisitorBehaviorProfile
    {
        $correlationId = $correlationId ?? (string) Str::uuid();

        // 1. Fetch visitor with all sessions and page views
        $visitor = Visitor::with(['sessions', 'pageViews', 'leads'])->find($visitorId);
        if (!$visitor) {
            throw new \InvalidArgumentException("Visitor not found: {$visitorId}");
        }

        $tenantId = $visitor->organization_id;

        // 2. Resolve/Create Behavior Profile
        $profile = VisitorBehaviorProfile::where('visitor_id', $visitorId)->first();
        $isNewProfile = false;
        
        if (!$profile) {
            $isNewProfile = true;
            $profile = new VisitorBehaviorProfile([
                'id' => (string) Str::uuid7(),
                'visitor_id' => $visitorId,
                'organization_id' => $tenantId,
                'engagement_score' => 0,
                'purchase_intent' => 'Low Intent',
                'service_interests' => [],
                'product_interests' => [],
                'content_intelligence' => [],
                'customer_value' => [],
                'score_history' => [],
                'timeline_summary' => '',
            ]);
        }

        // Capture previous values for event triggering
        $previousScore = $profile->engagement_score;
        $previousIntent = $profile->purchase_intent;
        $previousServices = $profile->service_interests ?? [];
        $previousProducts = $profile->product_interests ?? [];
        $previousContent = $profile->content_intelligence ?? [];
        $previousValue = $profile->customer_value ?? [];

        // 3. Perform Behavior Analyses
        $engagementScore = $this->calculateEngagementScore($visitor);
        $serviceInterests = $this->detectServiceInterests($visitor);
        $productInterests = $this->detectProductInterests($visitor);
        $contentIntelligence = $this->measureContentIntelligence($visitor);
        $purchaseIntent = $this->detectPurchaseIntent($visitor, $engagementScore, $serviceInterests, $productInterests);
        $customerValue = $this->estimateCustomerValue($visitor, $purchaseIntent, $engagementScore, $serviceInterests);
        $timelineSummary = $this->generateTimelineSummary($visitor, $purchaseIntent, $engagementScore);

        // 4. Update Profile State
        $profile->engagement_score = $engagementScore;
        $profile->purchase_intent = $purchaseIntent;
        $profile->service_interests = $serviceInterests;
        $profile->product_interests = $productInterests;
        $profile->content_intelligence = $contentIntelligence;
        $profile->customer_value = $customerValue;
        $profile->timeline_summary = $timelineSummary;

        // Track Score History
        $scoreHistory = $profile->score_history ?? [];
        if (empty($scoreHistory) || $previousScore !== $engagementScore) {
            $scoreHistory[] = [
                'score' => $engagementScore,
                'timestamp' => now()->toIso8601String(),
            ];
            $profile->score_history = $scoreHistory;
        }

        $profile->save();

        // 5. Fire Domain Events via EventBus
        $this->fireEvents(
            $profile,
            $previousScore,
            $engagementScore,
            $previousIntent,
            $purchaseIntent,
            $previousServices,
            $serviceInterests,
            $previousContent,
            $contentIntelligence,
            $previousValue,
            $customerValue,
            $correlationId
        );

        // 6. Observability - Structured Logging
        Log::info('Visitor Behavior Profile Updated', [
            'visitor_uuid' => $visitorId,
            'behavior_profile_uuid' => $profile->id,
            'organization_id' => $tenantId,
            'engagement_score' => $engagementScore,
            'intent_level' => $purchaseIntent,
            'correlation_id' => $correlationId,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $profile;
    }

    /**
     * Normalized Engagement Score (0-100).
     */
    protected function calculateEngagementScore(Visitor $visitor): int
    {
        $score = 0;

        // Factor 1: Session Duration (1 point per 30 seconds of total duration, up to 15 points)
        $totalDuration = $visitor->sessions->sum('duration') ?? 0;
        $score += min(15, intval($totalDuration / 30));

        // Factor 2: Returning Visits (+10 points if returning, +3 points per additional session, up to 25 points total)
        $sessionCount = $visitor->sessions->count();
        if ($sessionCount > 1) {
            $score += 10;
            $score += min(15, ($sessionCount - 1) * 3);
        }

        // Factor 3: Page Views (+2 points per view, up to 20 points)
        $pageViewsCount = $visitor->pageViews->count();
        $score += min(20, $pageViewsCount * 2);

        // Factor 4: CTA Interactions (+10 points per CTA clicked, up to 20 points)
        $ctaCount = 0;
        foreach ($visitor->pageViews as $pv) {
            $ctaCount += count($pv->cta_clicks ?? []);
        }
        $score += min(20, $ctaCount * 10);

        // Factor 5: Portfolio/Service Views (+5 points per service page, up to 15 points)
        $servicePageViews = $visitor->pageViews->filter(function ($pv) {
            return str_contains(strtolower($pv->url), '/services') || str_contains(strtolower($pv->page_title), 'service');
        })->count();
        $score += min(15, $servicePageViews * 5);

        // Factor 6: Pricing Page Visits (+10 points if pricing page is visited)
        $hasVisitedPricing = $visitor->pageViews->contains(function ($pv) {
            return str_contains(strtolower($pv->url), '/pricing') || str_contains(strtolower($pv->page_title), 'pricing');
        });
        if ($hasVisitedPricing) {
            $score += 10;
        }

        // Factor 7: Marketplace browsing (+5 points per product view, up to 15 points)
        $marketplacePageViews = $visitor->pageViews->filter(function ($pv) {
            return str_contains(strtolower($pv->url), '/marketplace') || str_contains(strtolower($pv->url), '/product');
        })->count();
        $score += min(15, $marketplacePageViews * 5);

        // Factor 8: Download / Link Interactions (+5 points per download interaction, up to 10 points)
        $downloadCount = 0;
        foreach ($visitor->pageViews as $pv) {
            $downloadCount += count($pv->downloads ?? []);
            $downloadCount += count($pv->outbound_links ?? []);
        }
        $score += min(10, $downloadCount * 5);

        return min(100, max(0, $score));
    }

    /**
     * Service Interest Detection (Multiple interests with confidence percentages).
     */
    protected function detectServiceInterests(Visitor $visitor): array
    {
        $interestMapping = [
            'Website Development' => ['website', 'web-development', 'web dev'],
            'Enterprise SaaS' => ['saas', 'enterprise', 'b2b-saas'],
            'CRM' => ['crm', 'customer-relationship'],
            'Marketplace' => ['marketplace', 'shop', 'ecommerce'],
            'Branding' => ['branding', 'brand', 'logo'],
            'UI/UX' => ['ui', 'ux', 'design', 'layout'],
            'Automation' => ['automation', 'workflow', 'automate'],
            'Artificial Intelligence' => ['ai', 'artificial', 'intelligence', 'gemini', 'openai'],
            'Mobile Apps' => ['mobile', 'app', 'android', 'ios'],
            'Cloud Infrastructure' => ['cloud', 'infrastructure', 'aws', 'gcp', 'devops'],
            'Support Services' => ['support', 'help', 'contact', 'service-desk'],
        ];

        $counts = [];
        $totalMatches = 0;

        foreach ($visitor->pageViews as $pv) {
            $textToSearch = strtolower($pv->url . ' ' . $pv->page_title);
            foreach ($interestMapping as $service => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($textToSearch, $kw)) {
                        $counts[$service] = ($counts[$service] ?? 0) + 1;
                        $totalMatches++;
                        break; // Move to next service mapping
                    }
                }
            }
        }

        $interests = [];
        foreach ($counts as $service => $count) {
            // Calculate a deterministic confidence percentage
            $baseConfidence = 30;
            $multiplier = 15;
            $confidence = min(100, $baseConfidence + ($count * $multiplier));
            $interests[$service] = $confidence;
        }

        arsort($interests);
        return $interests;
    }

    /**
     * Product Interest Engine.
     */
    protected function detectProductInterests(Visitor $visitor): array
    {
        $productsMapping = [
            'Laravel Boilerplates' => ['boilerplate', 'laravel-boilerplate'],
            'UI Kits' => ['ui-kit', 'uikit'],
            'Templates' => ['template', 'theme'],
            'Prompt Libraries' => ['prompt', 'prompts'],
            'Brand Assets' => ['brand-asset', 'brand-kit', 'logos'],
            'Developer Tools' => ['dev-tool', 'tool', 'cli'],
        ];

        $interests = [];

        foreach ($visitor->pageViews as $pv) {
            $textToSearch = strtolower($pv->url . ' ' . $pv->page_title);
            foreach ($productsMapping as $product => $keywords) {
                $matched = false;
                foreach ($keywords as $kw) {
                    if (str_contains($textToSearch, $kw)) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    if (!isset($interests[$product])) {
                        $interests[$product] = [
                            'viewed' => true,
                            'bookmarked' => false,
                            'revisited' => false,
                            'abandoned' => true, // default until purchased
                            'count' => 1,
                        ];
                    } else {
                        $interests[$product]['count']++;
                        $interests[$product]['revisited'] = true;
                    }
                }
            }
        }

        // Check if bookmarked (e.g. if CTA click was for bookmarking product)
        foreach ($visitor->pageViews as $pv) {
            $ctas = $pv->cta_clicks ?? [];
            foreach ($ctas as $cta) {
                $label = strtolower($cta['label'] ?? '');
                $ctaId = strtolower($cta['cta_id'] ?? '');
                if (str_contains($label, 'bookmark') || str_contains($label, 'favorite') || str_contains($ctaId, 'bookmark')) {
                    foreach ($productsMapping as $product => $keywords) {
                        foreach ($keywords as $kw) {
                            if (str_contains(strtolower($pv->url), $kw)) {
                                if (isset($interests[$product])) {
                                    $interests[$product]['bookmarked'] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        // If the visitor converted to a lead, or made direct purchases, we would update purchase state
        if ($visitor->leads->isNotEmpty()) {
            foreach ($interests as $product => &$data) {
                $data['abandoned'] = false;
            }
        }

        return $interests;
    }

    /**
     * Content Intelligence (Blog engagement analysis).
     */
    protected function measureContentIntelligence(Visitor $visitor): array
    {
        $blogViews = $visitor->pageViews->filter(function ($pv) {
            return str_contains(strtolower($pv->url), '/blog') || str_contains(strtolower($pv->page_title), 'blog');
        });

        if ($blogViews->isEmpty()) {
            return [
                'favorite_topics' => [],
                'reading_depth' => 0,
                'reading_frequency' => 0,
                'most_valuable_articles' => [],
                'estimated_expertise_level' => 'Beginner',
                'preferred_content_categories' => [],
            ];
        }

        // 1. Extract topics & categories from URLs/titles
        $topicsCount = [];
        $categoriesCount = [];
        $articles = [];

        foreach ($blogViews as $pv) {
            $urlParts = explode('/', parse_url($pv->url, PHP_URL_PATH));
            $slug = end($urlParts);

            // Extract topic tokens
            $tokens = explode('-', str_replace('_', '-', strtolower($slug)));
            foreach ($tokens as $token) {
                if (strlen($token) > 3 && !in_array($token, ['blog', 'post', 'article', 'with', 'your', 'from', 'that', 'this'])) {
                    $topicsCount[$token] = ($topicsCount[$token] ?? 0) + 1;
                }
            }

            // Categorize
            if (str_contains($pv->url, '/tech') || str_contains(strtolower($pv->page_title), 'tech')) {
                $categoriesCount['Technology'] = ($categoriesCount['Technology'] ?? 0) + 1;
            } elseif (str_contains($pv->url, '/business') || str_contains(strtolower($pv->page_title), 'business')) {
                $categoriesCount['Business'] = ($categoriesCount['Business'] ?? 0) + 1;
            } elseif (str_contains($pv->url, '/design') || str_contains(strtolower($pv->page_title), 'design')) {
                $categoriesCount['Design'] = ($categoriesCount['Design'] ?? 0) + 1;
            } else {
                $categoriesCount['Development'] = ($categoriesCount['Development'] ?? 0) + 1;
            }

            // Collect articles for finding most valuable
            $articles[] = [
                'url' => $pv->url,
                'page_title' => $pv->page_title,
                'scroll_depth' => $pv->scroll_depth ?? 50,
                'time_on_page' => $pv->time_on_page ?? 10,
            ];
        }

        arsort($topicsCount);
        arsort($categoriesCount);

        $favoriteTopics = array_slice(array_keys($topicsCount), 0, 5);
        $preferredCategories = array_slice(array_keys($categoriesCount), 0, 3);

        // 2. Average scroll depth
        $avgScrollDepth = round($blogViews->avg('scroll_depth') ?? 50.0, 2);

        // 3. Reading Frequency (articles read per session on average)
        $totalSessions = max(1, $visitor->sessions->count());
        $readingFrequency = round($blogViews->count() / $totalSessions, 2);

        // 4. Most valuable articles
        usort($articles, function ($a, $b) {
            return ($b['scroll_depth'] * $b['time_on_page']) <=> ($a['scroll_depth'] * $a['time_on_page']);
        });
        $mostValuable = array_slice($articles, 0, 3);

        // 5. Estimated expertise level
        $count = $blogViews->count();
        $expertise = 'Beginner';
        if ($count >= 6) {
            $expertise = 'Advanced';
        } elseif ($count >= 3) {
            $expertise = 'Intermediate';
        }

        return [
            'favorite_topics' => $favoriteTopics,
            'reading_depth' => $avgScrollDepth,
            'reading_frequency' => $readingFrequency,
            'most_valuable_articles' => $mostValuable,
            'estimated_expertise_level' => $expertise,
            'preferred_content_categories' => $preferredCategories,
        ];
    }

    /**
     * Purchase Intent Detection (Deterministic).
     */
    protected function detectPurchaseIntent(Visitor $visitor, int $score, array $services, array $products): string
    {
        // Rule: Existing Customer / Repeat Customer
        if ($visitor->leads->isNotEmpty()) {
            return $visitor->sessions->count() > 1 ? 'Repeat Customer' : 'Existing Customer';
        }

        // Rule: Enterprise Buyer (High score, plus high corporate service interest)
        if ($score >= 75 && (isset($services['Enterprise SaaS']) || isset($services['Cloud Infrastructure']))) {
            return 'Enterprise Buyer';
        }

        // Rule: High Purchase Intent
        if ($score >= 80) {
            return 'High Purchase Intent';
        }

        // Rule: Service Buyer
        if ($score >= 55 && !empty($services) && (current($services) > 50)) {
            return 'Service Buyer';
        }

        // Rule: Product Buyer
        if ($score >= 55 && !empty($products)) {
            return 'Product Buyer';
        }

        // Rule: Comparison Stage
        $hasPricingView = $visitor->pageViews->contains(function ($pv) {
            return str_contains(strtolower($pv->url), '/pricing') || str_contains(strtolower($pv->page_title), 'pricing');
        });
        if ($score >= 45 && $hasPricingView) {
            return 'Comparison Stage';
        }

        // Rule: Considering
        if ($score >= 35) {
            return 'Considering';
        }

        // Rule: Researching
        if ($score >= 15) {
            return 'Researching';
        }

        return 'Low Intent';
    }

    /**
     * Customer Value Estimation (Deterministic).
     */
    protected function estimateCustomerValue(Visitor $visitor, string $intent, int $score, array $services): array
    {
        // 1. Probabilities
        $enterpriseProb = 0.05;
        $smeProb = 0.30;
        $startupProb = 0.45;
        $agencyProb = 0.20;

        // Influence of intent on probabilities
        if ($intent === 'Enterprise Buyer') {
            $enterpriseProb = 0.85;
            $smeProb = 0.10;
            $startupProb = 0.03;
            $agencyProb = 0.02;
        } elseif ($intent === 'Service Buyer') {
            $smeProb = 0.60;
            $startupProb = 0.20;
            $enterpriseProb = 0.10;
            $agencyProb = 0.10;
        } elseif ($intent === 'Product Buyer') {
            $startupProb = 0.60;
            $agencyProb = 0.30;
            $smeProb = 0.08;
            $enterpriseProb = 0.02;
        }

        // 2. Potential Deal Size
        $dealSize = 1200.00; // Baseline
        if ($intent === 'Enterprise Buyer') {
            $dealSize = 25000.00;
        } elseif ($intent === 'Service Buyer') {
            $dealSize = 7500.00;
        } elseif ($intent === 'Product Buyer') {
            $dealSize = 350.00;
        } elseif ($intent === 'Comparison Stage') {
            $dealSize = 2500.00;
        }

        // Multiply by engagement factor
        $engagementFactor = 1.0 + ($score / 100.0);
        $dealSize = round($dealSize * $engagementFactor, 2);

        // 3. Estimated LTV
        $ltv = round($dealSize * 1.8, 2);

        // 4. Confidence Score (0.0 - 1.0)
        $sessionCount = $visitor->sessions->count();
        $confidence = min(0.98, 0.2 + ($score * 0.006) + ($sessionCount * 0.04));

        return [
            'estimated_deal_size' => $dealSize,
            'enterprise_probability' => $enterpriseProb,
            'sme_probability' => $smeProb,
            'startup_probability' => $startupProb,
            'agency_probability' => $agencyProb,
            'estimated_lifetime_value' => $ltv,
            'confidence_score' => round($confidence, 2),
        ];
    }

    /**
     * Generate chronological human-readable summary of visitor behavior.
     */
    protected function generateTimelineSummary(Visitor $visitor, string $intent, int $score): string
    {
        $summary = "Visitor with {$score}/100 engagement score is classified as '{$intent}'. ";
        $summary .= "First seen on " . $visitor->first_seen_at->toFormattedDateString() . " in " . ($visitor->city ?? 'Unknown') . ". ";
        $summary .= "Has completed " . $visitor->sessions->count() . " session(s) with " . $visitor->pageViews->count() . " page view(s).";
        return $summary;
    }

    /**
     * Dispatch atomic and composite events.
     */
    protected function fireEvents(
        VisitorBehaviorProfile $profile,
        int $prevScore, int $newScore,
        string $prevIntent, string $newIntent,
        array $prevServices, array $newServices,
        array $prevContent, array $newContent,
        array $prevValue, array $newValue,
        string $correlationId
    ): void {
        // Base profile updated event
        $this->eventBus->dispatch(new BehaviorProfileUpdated($profile, correlationId: $correlationId));

        // Score changed
        if ($prevScore !== $newScore) {
            $this->eventBus->dispatch(new EngagementScoreChanged($profile, $prevScore, $newScore, correlationId: $correlationId));
        }

        // Classification changed
        if ($prevIntent !== $newIntent) {
            $this->eventBus->dispatch(new BehaviorClassificationChanged($profile, $prevIntent, $newIntent, correlationId: $correlationId));
            $this->eventBus->dispatch(new PurchaseIntentDetected($profile, $newIntent, correlationId: $correlationId));
        }

        // Services changed
        if ($prevServices != $newServices) {
            $this->eventBus->dispatch(new ServiceInterestUpdated($profile, $newServices, correlationId: $correlationId));
        }

        // Content changed
        if ($prevContent != $newContent) {
            $this->eventBus->dispatch(new ContentInterestUpdated($profile, $newContent, correlationId: $correlationId));
        }

        // Value estimated
        if ($prevValue != $newValue) {
            $this->eventBus->dispatch(new VisitorValueEstimated($profile, $newValue, correlationId: $correlationId));
        }
    }
}
