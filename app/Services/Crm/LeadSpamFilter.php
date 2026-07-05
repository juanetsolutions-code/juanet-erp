<?php

namespace App\Services\Crm;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadSpamFilter
{
    /**
     * Analyze request and return a structured array with score, is_spam, and reasons.
     */
    public function analyze(Request $request): array
    {
        $score = 0;
        $reasons = [];
        $isObviousBot = false;

        // 1. Honeypot Hidden Fields
        // Check standard honeypot fields that bots often auto-fill
        $honeypotFields = ['honeypot', 'hp_field', 'website_url', 'subscribe_newsletter', 'email_confirm_field'];
        foreach ($honeypotFields as $field) {
            if ($request->filled($field)) {
                $score += 100;
                $isObviousBot = true;
                $reasons[] = "Honeypot field '{$field}' was filled.";
            }
        }

        // 2. Submission Timestamp & Minimum Completion Time
        $formTimestamp = $request->input('form_timestamp');
        if ($formTimestamp) {
            // Decrypt or decode if it is base64 encoded or obfuscated, but support plain unix timestamp
            if (is_string($formTimestamp) && !is_numeric($formTimestamp)) {
                if (base64_decode($formTimestamp, true) !== false) {
                    $formTimestamp = (int) base64_decode($formTimestamp);
                } else {
                    $formTimestamp = (int) $formTimestamp;
                }
            }

            $elapsed = time() - (int)$formTimestamp;

            if ($elapsed < 3) {
                $score += 60;
                $isObviousBot = true;
                $reasons[] = "Form submitted too fast ({$elapsed} seconds). Minimum is 3 seconds.";
            } elseif ($elapsed > 7200) {
                // If form has been idle for more than 2 hours, it's slightly suspicious but not a hard reject
                $score += 15;
                $reasons[] = "Form idle for too long ({$elapsed} seconds).";
            }
        } else {
            // Missing timestamp is a minor spam indicator, but not obvious bot to preserve backward compatibility
            $score += 10;
            $reasons[] = "Missing form submission timestamp.";
        }

        // 3. Duplicate Submission Detection (Rate limiting / identical payload)
        $email = strtolower(trim($request->input('email', '')));
        if ($email) {
            $cacheKey = 'lead_submit_hash_' . md5($email . '_' . $request->input('message', ''));
            if (Cache::has($cacheKey)) {
                $score += 80;
                $reasons[] = "Duplicate submission detected within short window.";
            } else {
                // Cache submission for 30 seconds to prevent double clicks / spam bursts
                Cache::put($cacheKey, true, 30);
            }
        }

        // 4. Optional Cloudflare Turnstile Verification
        $turnstileResponse = $request->input('turnstile_token') ?? $request->input('cf-turnstile-response');
        $turnstileSecret = env('TURNSTILE_SECRET_KEY') ?? config('services.turnstile.secret');
        if ($turnstileResponse && $turnstileSecret) {
            try {
                $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $turnstileSecret,
                    'response' => $turnstileResponse,
                    'remoteip' => $request->ip(),
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    if (!($result['success'] ?? false)) {
                        $score += 90;
                        $reasons[] = "Cloudflare Turnstile token validation failed.";
                    }
                } else {
                    Log::warning("Turnstile API request failed, ignoring validation.");
                }
            } catch (\Throwable $e) {
                Log::error("Error verifying Turnstile token: " . $e->getMessage());
            }
        }

        // 5. Text Pattern Spam Scoring (gibberish, spam keywords, temporary email domains)
        $message = strtolower($request->input('message') ?? $request->input('scope') ?? $request->input('details') ?? '');
        $company = strtolower($request->input('company', ''));
        $name = strtolower($request->input('name', ''));

        // Keywords indicating spam/backlink sell/SEO marketing
        $spamKeywords = [
            'casino', 'viagra', 'porn', 'cryptocurrency', 'bitcoin', 'backlinks', 
            'seo services', 'buy traffic', 'seo rankings', 'guest posting', 
            'pills', 'lottery winner', 'unsolicited pitch', 'make money fast'
        ];

        foreach ($spamKeywords as $keyword) {
            if (str_contains($message, $keyword) || str_contains($company, $keyword) || str_contains($name, $keyword)) {
                $score += 25;
                $reasons[] = "Contains spam keyword: '{$keyword}'.";
            }
        }

        // Temporary/disposable email domains
        $disposableDomains = ['mailinator.com', 'yopmail.com', 'trashmail.com', 'tempmail.com', 'guerrillamail.com', '10minutemail.com'];
        $emailDomain = substr(strrchr($email, "@"), 1);
        if (in_array($emailDomain, $disposableDomains)) {
            $score += 40;
            $reasons[] = "Disposable email domain detected: '{$emailDomain}'.";
        }

        // Check if name contains URLs or external links
        if (preg_match('/https?:\/\/[^\s]+/', $name)) {
            $score += 50;
            $isObviousBot = true;
            $reasons[] = "Lead name contains a URL.";
        }

        // Check for multiple URLs in the message (bots love linking back to spam sites)
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/', $message);
        if ($urlCount > 2) {
            $score += 30;
            $reasons[] = "Message contains multiple URLs ({$urlCount}).";
        }

        return [
            'score' => min(100, $score),
            'is_spam' => $score >= 50,
            'is_obvious_bot' => $isObviousBot || $score >= 80,
            'reasons' => $reasons,
        ];
    }
}
