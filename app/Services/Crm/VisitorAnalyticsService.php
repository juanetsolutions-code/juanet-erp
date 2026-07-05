<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;
use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\VisitorSession;
use App\Domain\CRM\Models\VisitorPageView;
use App\Domain\CRM\Models\Lead;
use Carbon\Carbon;

class VisitorAnalyticsService
{
    /**
     * Get a comprehensive analytics report for the dashboard.
     */
    public function getDashboardMetrics(?string $tenantId = null): array
    {
        return [
            'visitors_today' => $this->getVisitorsToday($tenantId),
            'returning_visitors' => $this->getReturningVisitorsCount($tenantId),
            'average_session_duration' => $this->getAverageSessionDuration($tenantId),
            'top_landing_pages' => $this->getTopLandingPages($tenantId),
            'top_exit_pages' => $this->getTopExitPages($tenantId),
            'most_viewed_services' => $this->getMostViewedServices($tenantId),
            'highest_converting_blog_articles' => $this->getHighestConvertingBlogArticles($tenantId),
            'highest_converting_marketplace_products' => $this->getHighestConvertingMarketplaceProducts($tenantId),
            'top_conversion_sources' => $this->getTopConversionSources($tenantId),
            'conversion_funnel' => $this->getConversionFunnel($tenantId),
        ];
    }

    public function getVisitorsToday(?string $tenantId = null): int
    {
        $query = Visitor::whereDate('first_seen_at', Carbon::today());
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        return $query->count();
    }

    public function getReturningVisitorsCount(?string $tenantId = null): int
    {
        $query = VisitorSession::where('returning_visitor', true);
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        return $query->count();
    }

    public function getAverageSessionDuration(?string $tenantId = null): float
    {
        $query = VisitorSession::query();
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        return (float) ($query->avg('duration') ?? 0.0);
    }

    public function getTopLandingPages(?string $tenantId = null, int $limit = 5): array
    {
        $query = VisitorSession::select('landing_page', DB::raw('count(*) as count'));
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        return $query->groupBy('landing_page')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTopExitPages(?string $tenantId = null, int $limit = 5): array
    {
        $query = VisitorSession::select('exit_page', DB::raw('count(*) as count'));
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        return $query->groupBy('exit_page')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getMostViewedServices(?string $tenantId = null, int $limit = 5): array
    {
        // Filters page views for /services URLs
        $query = VisitorPageView::select('url', 'page_title', DB::raw('count(*) as count'))
            ->where(function($q) {
                $q->where('url', 'like', '%/services%')
                  ->orWhere('page_title', 'like', '%Service%');
            });

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query->groupBy('url', 'page_title')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getHighestConvertingBlogArticles(?string $tenantId = null, int $limit = 5): array
    {
        // Identify converted visitors (who have an associated lead in crm_leads)
        // Check which blog URLs they viewed prior to conversion
        $convertedVisitorIds = Lead::whereNotNull('visitor_id');
        if ($tenantId) {
            $convertedVisitorIds->where('organization_id', $tenantId);
        }
        $visitorIds = $convertedVisitorIds->pluck('visitor_id')->toArray();

        if (empty($visitorIds)) {
            return [];
        }

        return VisitorPageView::select('url', 'page_title', DB::raw('count(distinct visitor_id) as conversions'))
            ->whereIn('visitor_id', $visitorIds)
            ->where(function($q) {
                $q->where('url', 'like', '%/blog%')
                  ->orWhere('page_title', 'like', '%Blog%');
            })
            ->groupBy('url', 'page_title')
            ->orderByDesc('conversions')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getHighestConvertingMarketplaceProducts(?string $tenantId = null, int $limit = 5): array
    {
        $convertedVisitorIds = Lead::whereNotNull('visitor_id');
        if ($tenantId) {
            $convertedVisitorIds->where('organization_id', $tenantId);
        }
        $visitorIds = $convertedVisitorIds->pluck('visitor_id')->toArray();

        if (empty($visitorIds)) {
            return [];
        }

        return VisitorPageView::select('url', 'page_title', DB::raw('count(distinct visitor_id) as conversions'))
            ->whereIn('visitor_id', $visitorIds)
            ->where(function($q) {
                $q->where('url', 'like', '%/marketplace%')
                  ->orWhere('url', 'like', '%/product%')
                  ->orWhere('page_title', 'like', '%Product%')
                  ->orWhere('page_title', 'like', '%Marketplace%');
            })
            ->groupBy('url', 'page_title')
            ->orderByDesc('conversions')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTopConversionSources(?string $tenantId = null, int $limit = 5): array
    {
        $query = Lead::select('crm_leads.crm_lead_metadata->>utm_source as utm_source', DB::raw('count(*) as count'))
            ->whereNotNull('visitor_id');

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query->groupBy('utm_source')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getConversionFunnel(?string $tenantId = null): array
    {
        $visitorsQuery = Visitor::query();
        $sessionsQuery = VisitorSession::query();
        $ctaQuery = VisitorPageView::whereNotNull('cta_clicks')->whereRaw("jsonb_array_length(cta_clicks) > 0");
        $leadsQuery = Lead::whereNotNull('visitor_id');

        if ($tenantId) {
            $visitorsQuery->where('organization_id', $tenantId);
            $sessionsQuery->where('organization_id', $tenantId);
            $ctaQuery->where('organization_id', $tenantId);
            $leadsQuery->where('organization_id', $tenantId);
        }

        $totalVisitors = $visitorsQuery->count();
        $totalSessions = $sessionsQuery->count();
        $ctaClicks = $ctaQuery->distinct('visitor_id')->count('visitor_id');
        $convertedLeads = $leadsQuery->count();

        return [
            [
                'stage' => '1_visitors',
                'label' => 'Total Visitors',
                'count' => $totalVisitors,
                'percentage' => 100.0,
            ],
            [
                'stage' => '2_sessions',
                'label' => 'Total Sessions',
                'count' => $totalSessions,
                'percentage' => $totalVisitors > 0 ? round(($totalSessions / $totalVisitors) * 100, 2) : 0.0,
            ],
            [
                'stage' => '3_cta_clicks',
                'label' => 'CTA Clicks (Engaged)',
                'count' => $ctaClicks,
                'percentage' => $totalVisitors > 0 ? round(($ctaClicks / $totalVisitors) * 100, 2) : 0.0,
            ],
            [
                'stage' => '4_conversions',
                'label' => 'Lead Conversions',
                'count' => $convertedLeads,
                'percentage' => $totalVisitors > 0 ? round(($convertedLeads / $totalVisitors) * 100, 2) : 0.0,
            ],
        ];
    }

    public function getTopInterestedServices(?string $tenantId = null, int $limit = 5): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::query();
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        $profiles = $query->get();
        $aggregated = [];
        foreach ($profiles as $profile) {
            $interests = $profile->service_interests ?? [];
            foreach ($interests as $service => $confidence) {
                if ($confidence > 0) {
                    $aggregated[$service] = ($aggregated[$service] ?? 0) + $confidence;
                }
            }
        }
        arsort($aggregated);
        $result = [];
        foreach (array_slice($aggregated, 0, $limit) as $service => $score) {
            $result[] = ['service' => $service, 'aggregate_confidence_score' => $score];
        }
        return $result;
    }

    public function getHighestIntentVisitors(?string $tenantId = null, int $limit = 10): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::with('visitor')
            ->orderByRaw("CASE 
                WHEN purchase_intent = 'Enterprise Buyer' THEN 10
                WHEN purchase_intent = 'High Purchase Intent' THEN 9
                WHEN purchase_intent = 'Service Buyer' THEN 8
                WHEN purchase_intent = 'Product Buyer' THEN 7
                WHEN purchase_intent = 'Comparison Stage' THEN 6
                WHEN purchase_intent = 'Considering' THEN 5
                WHEN purchase_intent = 'Researching' THEN 4
                ELSE 1
            END DESC")
            ->orderByDesc('engagement_score');

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query->limit($limit)->get()->toArray();
    }

    public function getMostEngagedVisitors(?string $tenantId = null, int $limit = 10): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::with('visitor')
            ->orderByDesc('engagement_score');

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query->limit($limit)->get()->toArray();
    }

    public function getEnterpriseProspectList(?string $tenantId = null, int $limit = 10): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::with('visitor')
            ->where(function($q) {
                $q->where('purchase_intent', 'Enterprise Buyer')
                  ->orWhereRaw("CAST(customer_value->>'enterprise_probability' AS DECIMAL) >= 0.5");
            });

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query->limit($limit)->get()->toArray();
    }

    public function getProductInterestTrends(?string $tenantId = null): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::query();
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        $profiles = $query->get();
        $trends = [];
        foreach ($profiles as $profile) {
            $products = $profile->product_interests ?? [];
            foreach ($products as $product => $data) {
                if (!isset($trends[$product])) {
                    $trends[$product] = [
                        'product' => $product,
                        'views' => 0,
                        'bookmarks' => 0,
                        'abandonments' => 0,
                    ];
                }
                $trends[$product]['views'] += $data['count'] ?? 1;
                if ($data['bookmarked'] ?? false) {
                    $trends[$product]['bookmarks']++;
                }
                if ($data['abandoned'] ?? false) {
                    $trends[$product]['abandonments']++;
                }
            }
        }
        return array_values($trends);
    }

    public function getContentEngagementTrends(?string $tenantId = null): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::query();
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        $profiles = $query->get();
        $totalProfiles = $profiles->count();
        if ($totalProfiles === 0) {
            return [];
        }

        $categories = [];
        $topics = [];
        $totalReadingDepth = 0;
        $beginnerCount = 0;
        $intermediateCount = 0;
        $advancedCount = 0;

        foreach ($profiles as $profile) {
            $ci = $profile->content_intelligence ?? [];
            if (empty($ci)) continue;

            $totalReadingDepth += $ci['reading_depth'] ?? 0;
            $level = $ci['estimated_expertise_level'] ?? 'Beginner';
            if ($level === 'Advanced') $advancedCount++;
            elseif ($level === 'Intermediate') $intermediateCount++;
            else $beginnerCount++;

            foreach ($ci['preferred_content_categories'] ?? [] as $cat) {
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }
            foreach ($ci['favorite_topics'] ?? [] as $topic) {
                $topics[$topic] = ($topics[$topic] ?? 0) + 1;
            }
        }

        return [
            'average_reading_depth' => round($totalReadingDepth / $totalProfiles, 2),
            'expertise_levels' => [
                'Beginner' => $beginnerCount,
                'Intermediate' => $intermediateCount,
                'Advanced' => $advancedCount,
            ],
            'top_categories' => $categories,
            'top_topics' => $topics,
        ];
    }

    public function getConversionPredictionSummary(?string $tenantId = null): array
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::query();
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        $profiles = $query->get();
        $total = $profiles->count();
        if ($total === 0) {
            return ['total_predictions' => 0, 'average_confidence' => 0.0];
        }

        $byIntent = [];
        $totalConfidence = 0;
        $totalDealSize = 0;

        foreach ($profiles as $profile) {
            $intent = $profile->purchase_intent ?? 'Low Intent';
            $byIntent[$intent] = ($byIntent[$intent] ?? 0) + 1;

            $cv = $profile->customer_value ?? [];
            $totalConfidence += $cv['confidence_score'] ?? 0;
            $totalDealSize += $cv['estimated_deal_size'] ?? 0;
        }

        return [
            'total_predictions' => $total,
            'intent_distribution' => $byIntent,
            'average_confidence' => round($totalConfidence / $total, 2),
            'estimated_pipeline_value' => round($totalDealSize, 2),
        ];
    }

    public function getAverageVisitorEngagement(?string $tenantId = null): float
    {
        $query = \App\Domain\CRM\Models\VisitorBehaviorProfile::query();
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        return (float) round($query->avg('engagement_score') ?? 0.0, 2);
    }

    public function getReturningVisitorIntelligence(?string $tenantId = null): array
    {
        $query = Visitor::where('total_sessions', '>', 1);
        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }
        $totalReturning = $query->count();

        $profilesQuery = \App\Domain\CRM\Models\VisitorBehaviorProfile::query();
        if ($tenantId) {
            $profilesQuery->where('organization_id', $tenantId);
        }
        $profiles = $profilesQuery->whereIn('visitor_id', $query->pluck('id'))->get();
        
        $avgReturningScore = $profiles->avg('engagement_score') ?? 0.0;
        $highIntentReturningCount = $profiles->filter(function($p) {
            return in_array($p->purchase_intent, ['High Purchase Intent', 'Enterprise Buyer', 'Service Buyer']);
        })->count();

        return [
            'total_returning_visitors' => $totalReturning,
            'average_engagement_score' => round($avgReturningScore, 2),
            'high_intent_returning_visitors' => $highIntentReturningCount,
        ];
    }
}
