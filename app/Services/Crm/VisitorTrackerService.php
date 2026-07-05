<?php

namespace App\Services\Crm;

use App\Contracts\EventBus;
use App\Domain\CRM\Models\Visitor;
use App\Domain\CRM\Models\VisitorSession;
use App\Domain\CRM\Models\VisitorPageView;
use App\Domain\CRM\Models\Lead;
use App\Domain\CRM\Events\VisitorCreated;
use App\Domain\CRM\Events\SessionStarted;
use App\Domain\CRM\Events\PageViewed;
use App\Domain\CRM\Events\CTAButtonClicked;
use App\Domain\CRM\Events\VisitorConverted;
use App\Domain\CRM\Events\SessionEnded;
use App\Domain\CRM\Events\VisitorReturned;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VisitorTrackerService
{
    protected EventBus $eventBus;
    protected TenantContext $tenantContext;

    public function __construct(EventBus $eventBus, TenantContext $tenantContext)
    {
        $this->eventBus = $eventBus;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Track a page view and handle visitor/session lifecycle.
     */
    public function track(Request $request, array $analyticsData = []): array
    {
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();
        $tenantId = $this->tenantContext->getTenantId();

        // 1. Respect Do Not Track (DNT) header or request flag
        $dnt = $request->header('DNT') === '1' || ($analyticsData['dnt'] ?? false);

        // 2. Resolve Cookie Consent
        $cookieConsent = $analyticsData['cookie_consent'] ?? $request->cookie('juanet_cookie_consent') ?? 'accepted';

        // 3. Resolve/Initialize Visitor ID
        $visitorId = $analyticsData['visitor_id'] ?? $request->cookie('juanet_visitor_id');
        $isNewVisitor = false;

        if (!$visitorId || !Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid7();
            $isNewVisitor = true;
        }

        // 4. Resolve/Initialize Session ID
        $sessionId = $analyticsData['session_id'] ?? $request->cookie('juanet_session_id');
        $isNewSession = false;

        if (!$sessionId || !Str::isUuid($sessionId)) {
            $sessionId = (string) Str::uuid7();
            $isNewSession = true;
        }

        // 5. Build/Resolve Device/Geo details
        $userAgent = $request->userAgent() ?? 'Unknown';
        $browser = $analyticsData['browser'] ?? $this->parseBrowser($userAgent);
        $os = $analyticsData['operating_system'] ?? $this->parseOS($userAgent);
        $deviceType = $analyticsData['device_type'] ?? $this->parseDeviceType($userAgent);
        $lang = $analyticsData['preferred_language'] ?? substr($request->server('HTTP_ACCEPT_LANGUAGE', 'en'), 0, 5);

        $country = $dnt ? 'Anonymized' : ($analyticsData['country'] ?? $request->header('CF-IPCountry') ?? 'Kenya');
        $city = $dnt ? 'Anonymized' : ($analyticsData['city'] ?? 'Nairobi');
        $timezone = $analyticsData['timezone'] ?? 'Africa/Nairobi';

        // 6. Campaign & Attribution parsing
        $utm = [
            'utm_source' => $analyticsData['utm_source'] ?? $request->input('utm_source'),
            'utm_medium' => $analyticsData['utm_medium'] ?? $request->input('utm_medium'),
            'utm_campaign' => $analyticsData['utm_campaign'] ?? $request->input('utm_campaign'),
            'utm_term' => $analyticsData['utm_term'] ?? $request->input('utm_term'),
            'utm_content' => $analyticsData['utm_content'] ?? $request->input('utm_content'),
        ];

        // 7. Persist or Update Visitor
        $visitor = Visitor::find($visitorId);
        
        if (!$visitor) {
            $isNewVisitor = true;
            $firstTouch = array_merge($utm, [
                'landing_page' => $analyticsData['url'] ?? $request->fullUrl(),
                'referrer' => $analyticsData['referrer'] ?? $request->header('Referer'),
                'timestamp' => now()->toIso8601String()
            ]);

            $visitor = Visitor::create([
                'id' => $visitorId,
                'organization_id' => $tenantId,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'total_sessions' => 1,
                'total_page_views' => 1,
                'country' => $country,
                'city' => $city,
                'timezone' => $timezone,
                'preferred_language' => $lang,
                'browser' => $browser,
                'operating_system' => $os,
                'device_type' => $deviceType,
                'screen_resolution' => $analyticsData['screen_resolution'] ?? null,
                'viewport' => $analyticsData['viewport'] ?? null,
                'network_type' => $analyticsData['network_type'] ?? null,
                'utm_source' => $utm['utm_source'],
                'utm_medium' => $utm['utm_medium'],
                'utm_campaign' => $utm['utm_campaign'],
                'utm_term' => $utm['utm_term'],
                'utm_content' => $utm['utm_content'],
                'campaign_history' => [$utm],
                'referral_chain' => array_filter([$analyticsData['referrer'] ?? $request->header('Referer')]),
                'first_touch' => $firstTouch,
                'last_touch' => $firstTouch,
                'cookie_consent' => $cookieConsent,
                'do_not_track' => $dnt,
            ]);

            // Structured logging (Observability)
            $this->logActivity($correlationId, 'visitor_created', $visitorId, $sessionId, $tenantId, [
                'country' => $country,
                'browser' => $browser,
                'os' => $os,
            ]);

            // Dispatch Event
            $this->eventBus->dispatch(new VisitorCreated($visitor, correlationId: $correlationId));
        } else {
            // Update last seen
            $visitor->last_seen_at = now();
            $visitor->total_page_views += 1;
            
            // Append Campaign History if new Campaign detected
            if (array_filter($utm)) {
                $history = $visitor->campaign_history ?? [];
                $history[] = $utm;
                $visitor->campaign_history = $history;
                
                // Update active attribution
                $visitor->utm_source = $utm['utm_source'] ?? $visitor->utm_source;
                $visitor->utm_medium = $utm['utm_medium'] ?? $visitor->utm_medium;
                $visitor->utm_campaign = $utm['utm_campaign'] ?? $visitor->utm_campaign;
                $visitor->utm_term = $utm['utm_term'] ?? $visitor->utm_term;
                $visitor->utm_content = $utm['utm_content'] ?? $visitor->utm_content;
            }

            // Append Referral Chain
            $ref = $analyticsData['referrer'] ?? $request->header('Referer');
            if ($ref) {
                $chain = $visitor->referral_chain ?? [];
                if (!in_array($ref, $chain)) {
                    $chain[] = $ref;
                    $visitor->referral_chain = $chain;
                }
            }

            // Update last touch
            $visitor->last_touch = array_merge($utm, [
                'exit_page' => $analyticsData['url'] ?? $request->fullUrl(),
                'timestamp' => now()->toIso8601String()
            ]);

            if ($isNewSession) {
                $visitor->total_sessions += 1;
            }

            $visitor->save();

            // Dispatch Return Event
            if ($isNewSession) {
                $this->eventBus->dispatch(new VisitorReturned($visitor, correlationId: $correlationId));
            }
        }

        // 8. Persist or Update Session
        $session = VisitorSession::find($sessionId);

        if (!$session) {
            $isNewSession = true;
            $session = VisitorSession::create([
                'id' => $sessionId,
                'visitor_id' => $visitorId,
                'organization_id' => $tenantId,
                'start_time' => now(),
                'end_time' => now(),
                'duration' => 0,
                'referrer' => $analyticsData['referrer'] ?? $request->header('Referer'),
                'landing_page' => $analyticsData['url'] ?? $request->fullUrl(),
                'exit_page' => $analyticsData['url'] ?? $request->fullUrl(),
                'pages_visited' => 1,
                'bounce' => true,
                'returning_visitor' => !$isNewVisitor,
            ]);

            // Structured logging (Observability)
            $this->logActivity($correlationId, 'session_started', $visitorId, $sessionId, $tenantId, [
                'referrer' => $session->referrer,
                'landing_page' => $session->landing_page,
            ]);

            // Dispatch Event
            $this->eventBus->dispatch(new SessionStarted($session, correlationId: $correlationId));
        } else {
            // Update session metrics
            $session->end_time = now();
            $session->duration = max(0, now()->diffInSeconds($session->start_time));
            $session->pages_visited += 1;
            $session->exit_page = $analyticsData['url'] ?? $request->fullUrl();
            if ($session->pages_visited > 1) {
                $session->bounce = false;
            }
            $session->save();
        }

        // 9. Persist Page View
        $pageviewId = (string) Str::uuid7();
        $pageView = VisitorPageView::create([
            'id' => $pageviewId,
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'organization_id' => $tenantId,
            'url' => $analyticsData['url'] ?? $request->fullUrl(),
            'route_name' => $analyticsData['route_name'] ?? $request->route()?->getName(),
            'page_title' => $analyticsData['page_title'] ?? 'JUANET Hub',
            'timestamp' => now(),
            'time_on_page' => $analyticsData['time_on_page'] ?? null,
            'scroll_depth' => $analyticsData['scroll_depth'] ?? null,
            'cta_clicks' => $analyticsData['cta_clicks'] ?? [],
            'downloads' => $analyticsData['downloads'] ?? [],
            'outbound_links' => $analyticsData['outbound_links'] ?? [],
        ]);

        // Structured logging (Observability)
        $this->logActivity($correlationId, 'page_viewed', $visitorId, $sessionId, $tenantId, [
            'url' => $pageView->url,
            'title' => $pageView->page_title,
        ]);

        // Dispatch Event
        $this->eventBus->dispatch(new PageViewed($pageView, correlationId: $correlationId));

        try {
            app(VisitorBehaviorService::class)->analyze($visitorId, $correlationId);
        } catch (\Throwable $e) {
            Log::error('Failed to update visitor behavior profile in track', [
                'visitor_id' => $visitorId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'page_view_id' => $pageviewId,
            'is_new_visitor' => $isNewVisitor,
            'is_new_session' => $isNewSession,
        ];
    }

    /**
     * Record a CTA button click.
     */
    public function trackCta(string $pageViewId, array $ctaData, ?string $correlationId = null): void
    {
        $pageView = VisitorPageView::find($pageViewId);
        if (!$pageView) {
            Log::warning('Page view not found for CTA tracking', ['page_view_id' => $pageViewId]);
            return;
        }

        $clicks = $pageView->cta_clicks ?? [];
        $ctaData['clicked_at'] = $ctaData['clicked_at'] ?? now()->toIso8601String();
        $clicks[] = $ctaData;
        $pageView->cta_clicks = $clicks;
        $pageView->save();

        // Structured logging (Observability)
        $this->logActivity(
            $correlationId ?? (string) Str::uuid(),
            'cta_clicked',
            $pageView->visitor_id,
            $pageView->session_id,
            $pageView->organization_id,
            $ctaData
        );

        // Dispatch Event
        $this->eventBus->dispatch(new CTAButtonClicked($pageView, $ctaData, correlationId: $correlationId));

        try {
            app(VisitorBehaviorService::class)->analyze($pageView->visitor_id, $correlationId);
        } catch (\Throwable $e) {
            Log::error('Failed to update visitor behavior profile in trackCta', [
                'visitor_id' => $pageView->visitor_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Associate a Visitor journey when they convert to a Lead.
     */
    public function associateLead(string $visitorId, Lead $lead, ?string $correlationId = null): void
    {
        $visitor = Visitor::find($visitorId);
        if (!$visitor) {
            Log::warning('Visitor not found for conversion association', ['visitor_id' => $visitorId]);
            return;
        }

        // Link on lead record
        $lead->visitor_id = $visitorId;
        
        // Enrich Lead metadata with Campaign / Attribution details
        $meta = $lead->crm_lead_metadata ?? [];
        $meta['visitor_id'] = $visitorId;
        $meta['utm_source'] = $visitor->utm_source ?? $meta['utm_source'] ?? null;
        $meta['utm_medium'] = $visitor->utm_medium ?? $meta['utm_medium'] ?? null;
        $meta['utm_campaign'] = $visitor->utm_campaign ?? $meta['utm_campaign'] ?? null;
        $meta['utm_term'] = $visitor->utm_term ?? $meta['utm_term'] ?? null;
        $meta['utm_content'] = $visitor->utm_content ?? $meta['utm_content'] ?? null;
        $meta['first_touch'] = $visitor->first_touch;
        $meta['last_touch'] = $visitor->last_touch;
        $meta['conversion_attribution'] = [
            'total_sessions' => $visitor->total_sessions,
            'total_page_views' => $visitor->total_page_views,
            'country' => $visitor->country,
            'city' => $visitor->city,
            'device_type' => $visitor->device_type,
            'browser' => $visitor->browser,
            'operating_system' => $visitor->operating_system,
        ];

        try {
            $behaviorService = app(VisitorBehaviorService::class);
            $profile = $behaviorService->analyze($visitorId, $correlationId);

            $meta['behavior_profile'] = [
                'id' => $profile->id,
                'engagement_score' => $profile->engagement_score,
                'purchase_intent' => $profile->purchase_intent,
                'service_interests' => $profile->service_interests,
                'product_interests' => $profile->product_interests,
                'content_interests' => $profile->content_intelligence,
                'estimated_deal_size' => $profile->customer_value['estimated_deal_size'] ?? null,
                'customer_value_prediction' => $profile->customer_value,
                'timeline_summary' => $profile->timeline_summary,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to attach visitor behavior profile to lead', [
                'visitor_id' => $visitorId,
                'error' => $e->getMessage(),
            ]);
        }

        $lead->crm_lead_metadata = $meta;
        $lead->save();

        // Structured logging (Observability)
        $this->logActivity(
            $correlationId ?? (string) Str::uuid(),
            'visitor_converted',
            $visitorId,
            null,
            $visitor->organization_id,
            ['lead_id' => $lead->id, 'email' => $lead->email]
        );

        // Dispatch Event
        $this->eventBus->dispatch(new VisitorConverted($visitor, $lead, correlationId: $correlationId));
    }

    /**
     * Terminate / Close a browsing session.
     */
    public function endSession(string $sessionId, ?string $correlationId = null): void
    {
        $session = VisitorSession::find($sessionId);
        if (!$session) {
            return;
        }

        $session->end_time = now();
        $session->duration = max(0, now()->diffInSeconds($session->start_time));
        if ($session->pages_visited > 1) {
            $session->bounce = false;
        }
        $session->save();

        // Structured logging (Observability)
        $this->logActivity(
            $correlationId ?? (string) Str::uuid(),
            'session_ended',
            $session->visitor_id,
            $sessionId,
            $session->organization_id,
            ['duration' => $session->duration, 'pages_visited' => $session->pages_visited]
        );

        // Dispatch Event
        $this->eventBus->dispatch(new SessionEnded($session, correlationId: $correlationId));

        try {
            app(VisitorBehaviorService::class)->analyze($session->visitor_id, $correlationId);
        } catch (\Throwable $e) {
            Log::error('Failed to update visitor behavior profile in endSession', [
                'visitor_id' => $session->visitor_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Anonymize visitor records (GDPR compliance).
     */
    public function anonymize(string $visitorId): void
    {
        $visitor = Visitor::find($visitorId);
        if (!$visitor) return;

        $visitor->update([
            'country' => 'Anonymized',
            'city' => 'Anonymized',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
            'campaign_history' => null,
            'referral_chain' => null,
            'first_touch' => null,
            'last_touch' => null,
            'anonymized_at' => now(),
        ]);
    }

    /**
     * Helper for structured logging.
     */
    protected function logActivity(string $correlationId, string $eventType, ?string $visitorId, ?string $sessionId, ?string $organizationId, array $data = []): void
    {
        Log::info("Visitor Intelligence: {$eventType}", [
            'visitor_uuid' => $visitorId,
            'session_uuid' => $sessionId,
            'organization_id' => $organizationId,
            'correlation_id' => $correlationId,
            'timestamp' => now()->toIso8601String(),
            'event_type' => $eventType,
            'context' => $data,
        ]);
    }

    /**
     * Simple parsing of browser from User-Agent.
     */
    protected function parseBrowser(string $userAgent): string
    {
        if (preg_match('/MSIE/i', $userAgent) && !preg_match('/Opera/i', $userAgent)) return 'MSIE';
        if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
        if (preg_match('/Safari/i', $userAgent)) return 'Safari';
        if (preg_match('/Opera/i', $userAgent)) return 'Opera';
        if (preg_match('/Netscape/i', $userAgent)) return 'Netscape';
        return 'Unknown';
    }

    /**
     * Simple parsing of OS from User-Agent.
     */
    protected function parseOS(string $userAgent): string
    {
        if (preg_match('/windows|win32/i', $userAgent)) return 'Windows';
        if (preg_match('/macintosh|mac os x/i', $userAgent)) return 'macOS';
        if (preg_match('/linux/i', $userAgent)) return 'Linux';
        if (preg_match('/android/i', $userAgent)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'iOS';
        return 'Unknown';
    }

    /**
     * Simple parsing of device type from User-Agent.
     */
    protected function parseDeviceType(string $userAgent): string
    {
        if (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) return 'Tablet';
        if (preg_match('/mobile|phone|android|iphone|ipod|blackberry|opera mini/i', $userAgent)) return 'Mobile';
        return 'Desktop';
    }
}
