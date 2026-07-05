<?php

namespace App\Domain\Marketplace\Repositories;

use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use App\Domain\Marketplace\Models\MarketplaceProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class MarketplaceProductRepository implements MarketplaceProductRepositoryInterface
{
    protected array $fallbackProducts = [];

    public function __construct()
    {
        $this->fallbackProducts = [
            [
                'id' => '01900000-1111-0000-0000-000000000001',
                'category_id' => '01900000-0000-0000-0000-000000000001',
                'category_slug' => 'laravel',
                'category_name' => 'Laravel',
                'title' => 'Laravel SaaS Starter Kit',
                'slug' => 'laravel-saas-starter-kit',
                'short_description' => 'Production-ready Laravel boilerplate equipped with multi-tenancy, Stripe/M-PESA billing, and standard OAuth integration.',
                'description' => 'Scale your next SaaS product in days. Features robust multi-tenant Row-Level Security, integrated M-PESA STK Push flows, customizable theme setups, email verification, full notification bus, transactional outbox logging, and an elegant admin dashboard. Highly optimized for high-performance and scalability.',
                'technology' => ['Laravel', 'Alpine.js', 'Tailwind CSS', 'PostgreSQL', 'Redis'],
                'rating' => 4.9,
                'review_count' => 24,
                'price' => 14500,
                'previous_price' => 19500,
                'is_new' => false,
                'is_best_seller' => true,
                'is_featured' => true,
                'thumbnail' => '/images/laravel-saas-kit.png',
                'gallery' => ['/images/laravel-saas-kit-1.png', '/images/laravel-saas-kit-2.png'],
                'features' => ['Multi-tenant Isolation', 'Lipa Na M-PESA STK Push', 'Role-Based Access Control', 'Tailwind & Alpine Frontends', 'Pre-configured Job Queues'],
                'screenshots' => ['Dashboard View', 'Billing Portal', 'User Settings'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000002',
                'category_id' => '01900000-0000-0000-0000-000000000005',
                'category_slug' => 'templates',
                'category_name' => 'Templates',
                'title' => 'Enterprise CRM Boilerplate',
                'slug' => 'enterprise-crm-boilerplate',
                'short_description' => 'Comprehensive sales pipeline and client relations engine with rich contact management, timeline tracking, and AI leads intelligence.',
                'description' => 'A robust, highly modern CRM boilerplate featuring responsive contact sheets, visual kanban pipeline boards, dynamic timeline aggregation, lead duplicate matching systems, and deep visual contact health analytics. Comes with real-time feedback notifications and automated audit trails.',
                'technology' => ['Laravel', 'React', 'Tailwind CSS', 'PostgreSQL', 'D3.js'],
                'rating' => 4.8,
                'review_count' => 15,
                'price' => 22000,
                'previous_price' => 29000,
                'is_new' => true,
                'is_best_seller' => false,
                'is_featured' => true,
                'thumbnail' => '/images/enterprise-crm.png',
                'gallery' => ['/images/enterprise-crm-1.png', '/images/enterprise-crm-2.png'],
                'features' => ['Interactive Kanban Pipeline', 'Detailed Activity Timelines', 'Contact Health Recalculation', 'Duplicate Matching AI', 'CSV Export & Import Tooling'],
                'screenshots' => ['Kanban Pipeline', 'Contact Detail', 'Duplication Merge Modal'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000003',
                'category_id' => '01900000-0000-0000-0000-000000000005',
                'category_slug' => 'templates',
                'category_name' => 'Templates',
                'title' => 'Restaurant POS',
                'slug' => 'restaurant-pos',
                'short_description' => 'Fast, real-time Point of Sale software engineered for modern restaurants, cloud kitchens, and bar operations.',
                'description' => 'Streamline kitchen tickets, customer billing, and table allocations. Features offline-first support, digital waiter pads, receipt printing integrations, automated inventory low-limit alerts, M-PESA quick STK push at checkouts, and high-performance kitchen display systems (KDS).',
                'technology' => ['Vue', 'Laravel', 'Tailwind CSS', 'MySQL', 'WebSockets'],
                'rating' => 4.7,
                'review_count' => 32,
                'price' => 18500,
                'previous_price' => 24000,
                'is_new' => false,
                'is_best_seller' => true,
                'is_featured' => false,
                'thumbnail' => '/images/restaurant-pos.png',
                'gallery' => ['/images/restaurant-pos-1.png'],
                'features' => ['Split Billing Capabilities', 'KDS Kitchen Screen Interface', 'Table Status Syncing', 'Lipa Na M-PESA Checkouts', 'Instant Low Inventory Alerts'],
                'screenshots' => ['Cashier Interface', 'Kitchen Display', 'Receipt Layout'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000004',
                'category_id' => '01900000-0000-0000-0000-000000000005',
                'category_slug' => 'templates',
                'category_name' => 'Templates',
                'title' => 'Property Management System',
                'slug' => 'property-management-system',
                'short_description' => 'Complete digital management system for landlords, realtors, and rental agencies tracking tenants and properties.',
                'description' => 'Empower your property operations. Automate rent payment reminders, track ongoing maintenance work orders, register tenancy contracts, issue automated tax-inclusive invoices, and accept client payments directly with real-time payment ledger reconciliation.',
                'technology' => ['Next.js', 'PostgreSQL', 'Tailwind CSS', 'TypeScript'],
                'rating' => 4.9,
                'review_count' => 18,
                'price' => 25000,
                'previous_price' => null,
                'is_new' => false,
                'is_best_seller' => false,
                'is_featured' => true,
                'thumbnail' => '/images/property-manager.png',
                'gallery' => ['/images/property-manager-1.png'],
                'features' => ['Automated Rent Invoicing', 'Tenant Portal Dashboard', 'Maintenance Work Order Board', 'Financial Performance Ledger', 'Document Cloud Storage'],
                'screenshots' => ['Landlord Board', 'Maintenance Tickets', 'Tenant Invoices'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000005',
                'category_id' => '01900000-0000-0000-0000-000000000005',
                'category_slug' => 'templates',
                'category_name' => 'Templates',
                'title' => 'Hospital Management System',
                'slug' => 'hospital-management-system',
                'short_description' => 'Clinical suite for patient records, appointment booking, lab results, and secure billing operations.',
                'description' => 'Enterprise-grade EHR (Electronic Health Records) and clinic workflow engine. Includes doctor calendars, prescription systems, ward beds allocations, lab requests pipelines, patient portal, and secure GDPR-compliant medical data encryption schemas.',
                'technology' => ['Laravel', 'React', 'Livewire', 'PostgreSQL'],
                'rating' => 5.0,
                'review_count' => 8,
                'price' => 35000,
                'previous_price' => 45000,
                'is_new' => true,
                'is_best_seller' => false,
                'is_featured' => false,
                'thumbnail' => '/images/hospital-erp.png',
                'gallery' => ['/images/hospital-erp-1.png'],
                'features' => ['Secure Electronic Records (EHR)', 'Doctor Scheduling Calendar', 'Prescription Formulation Tool', 'Ward Bed Allocation Board', 'Lab Requests Pipeline'],
                'screenshots' => ['Doctor Console', 'Patient Records', 'Ward Layout'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000006',
                'category_id' => '01900000-0000-0000-0000-000000000005',
                'category_slug' => 'templates',
                'category_name' => 'Templates',
                'title' => 'School ERP',
                'slug' => 'school-erp',
                'short_description' => 'School information system linking admissions, fee collection, timetables, and parents communication.',
                'description' => 'A centralized system for educational institutions. Supports student profiles, automated fee receipt generation, timetable scheduling matrix, report cards preparation, parent portal, SMS gateways, and secure employee payroll management.',
                'technology' => ['Laravel', 'Vue', 'Tailwind CSS', 'MySQL'],
                'rating' => 4.6,
                'review_count' => 14,
                'price' => 28000,
                'previous_price' => null,
                'is_new' => false,
                'is_best_seller' => false,
                'is_featured' => false,
                'thumbnail' => '/images/school-erp.png',
                'gallery' => ['/images/school-erp-1.png'],
                'features' => ['Student Admissions Funnel', 'Termly Fee Billing Hub', 'Timetable Matrix Generator', 'Exam Grades & Report Cards', 'Parent Notification Center'],
                'screenshots' => ['Admissions Desk', 'Fee Collections', 'Timetable Calendar'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000007',
                'category_id' => '01900000-0000-0000-0000-000000000004',
                'category_slug' => 'ai-prompts',
                'category_name' => 'AI Prompts',
                'title' => 'Prompt Library',
                'slug' => 'prompt-library',
                'short_description' => 'Curated directory of highly optimized engineering prompts for AI models including Gemini, GPT-4, and Claude.',
                'description' => 'Unleash the full potential of large language models. Get access to dozens of production-grade, highly structured system instructions, few-shot templates, chain-of-thought prompt templates, and direct API parameters tailored for code generation, copywriting, and business intelligence.',
                'technology' => ['Gemini API', 'ChatGPT', 'JSON', 'Markdown'],
                'rating' => 4.9,
                'review_count' => 45,
                'price' => 3200,
                'previous_price' => 5000,
                'is_new' => true,
                'is_best_seller' => true,
                'is_featured' => true,
                'thumbnail' => '/images/prompt-library.png',
                'gallery' => ['/images/prompt-library-1.png'],
                'features' => ['System Instruction Templates', 'Chain-of-Thought Workflows', 'Few-Shot Classification JSONs', 'Optimized for Gemini & GPT', 'Free Regular Updates'],
                'screenshots' => ['Prompt Details', 'Copy Snippet Screen'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000008',
                'category_id' => '01900000-0000-0000-0000-000000000006',
                'category_slug' => 'brand-assets',
                'category_name' => 'Brand Assets',
                'title' => 'Brand Kit',
                'slug' => 'brand-kit',
                'short_description' => 'A complete visual assets system containing professional logo grids, high-res layouts, color swatches, and slides.',
                'description' => 'Accelerate your agency branding project. Includes fully editable Figma source grids, scalable vector illustrations (SVGs), print-ready corporate document PDFs, key slide templates, typography pairings, and clean color palette palettes tailored for enterprise SaaS look and feels.',
                'technology' => ['Figma', 'SVG', 'PDF', 'Illustrator'],
                'rating' => 4.7,
                'review_count' => 19,
                'price' => 6000,
                'previous_price' => 9000,
                'is_new' => false,
                'is_best_seller' => false,
                'is_featured' => false,
                'thumbnail' => '/images/brand-kit.png',
                'gallery' => ['/images/brand-kit-1.png'],
                'features' => ['Editable Figma Vector File', 'Pre-built Slide Deck Templates', 'Corporate Letterhead PDFs', 'Comprehensive Color Palette', 'High-res Mockup Layouts'],
                'screenshots' => ['Figma Workspace', 'Corporate Slides'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000009',
                'category_id' => '01900000-0000-0000-0000-000000000003',
                'category_slug' => 'react',
                'category_name' => 'React',
                'title' => 'Admin Dashboard',
                'slug' => 'admin-dashboard',
                'short_description' => 'Beautifully styled responsive administrator dashboard built with React, Tailwind, and animated charts.',
                'description' => 'A highly customizable control panel UI kit. Equipped with pixel-perfect bento grids, glassmorphism card panels, animated stats modules, multi-step dynamic forms, rich data tables with filtering, dark and light theme palettes, and modular Lucide icon sets.',
                'technology' => ['React', 'Tailwind CSS', 'D3.js', 'Framer Motion'],
                'rating' => 5.0,
                'review_count' => 27,
                'price' => 8000,
                'previous_price' => 12000,
                'is_new' => false,
                'is_best_seller' => true,
                'is_featured' => true,
                'thumbnail' => '/images/admin-dash.png',
                'gallery' => ['/images/admin-dash-1.png'],
                'features' => ['Responsive Layout Layouts', 'Glassmorphic Card Designs', 'Interactive Chart Modules', 'Staggered Entry Animations', 'Lucide Vector Icons'],
                'screenshots' => ['Main Dashboard', 'Dynamic Data Table', 'Settings Panel'],
            ],
            [
                'id' => '01900000-1111-0000-0000-000000000010',
                'category_id' => '01900000-0000-0000-0000-000000000007',
                'category_slug' => 'automation',
                'category_name' => 'Automation',
                'title' => 'Invoice System',
                'slug' => 'invoice-system',
                'short_description' => 'Automated invoicing app integrating billing items, email PDF mailers, and Daraja M-PESA reconciliation triggers.',
                'description' => 'Never chase invoices manually again. Send beautifully formatted digital invoices, track open status, automate payment reminder emails, generate downloadable PDFs, and reconcile payments instantly using Safaricom Lipa Na M-PESA STK push triggers.',
                'technology' => ['Laravel', 'Tailwind CSS', 'Safaricom Daraja API', 'PDF Library'],
                'rating' => 4.8,
                'review_count' => 11,
                'price' => 9500,
                'previous_price' => 13500,
                'is_new' => true,
                'is_best_seller' => false,
                'is_featured' => false,
                'thumbnail' => '/images/invoice-system.png',
                'gallery' => ['/images/invoice-system-1.png'],
                'features' => ['Lipa Na M-PESA Reconciliation', 'Automated Reminder Mailers', 'Downloadable Digital PDFs', 'Multi-item Invoice Form', 'Detailed Billing Logs'],
                'screenshots' => ['Invoice Builder', 'STK Checkout Push', 'PDF Template'],
            ],
        ];

        // Dynamically enrich fallback products with missing Phase F4.2 fields
        $this->fallbackProducts = array_map(function ($p) {
            $p['framework'] = $p['framework'] ?? (in_array('Laravel', $p['technology'] ?? []) ? 'Laravel' : (in_array('React', $p['technology'] ?? []) ? 'React' : (in_array('Next.js', $p['technology'] ?? []) ? 'Next.js' : (in_array('Vue', $p['technology'] ?? []) ? 'Vue' : 'Tailwind CSS'))));
            $p['version'] = $p['version'] ?? '1.2.0';
            $p['downloads'] = $p['downloads'] ?? rand(50, 450);
            $p['views'] = $p['views'] ?? rand(500, 3000);
            $p['author'] = $p['author'] ?? 'JUANET Core';
            $p['license'] = $p['license'] ?? 'Regular License';
            $p['tags'] = $p['tags'] ?? array_map('strtolower', array_slice($p['technology'] ?? [], 0, 3));
            $p['created_at'] = $p['created_at'] ?? now()->subDays(rand(10, 100))->toIso8601String();
            $p['updated_at'] = $p['updated_at'] ?? now()->subDays(rand(1, 9))->toIso8601String();
            return $p;
        }, $this->fallbackProducts);
    }

    protected function getActiveCollection(): Collection
    {
        try {
            if (Schema::hasTable('marketplace_products') && MarketplaceProduct::count() > 0) {
                return MarketplaceProduct::with('category')->get()->map(function ($p) {
                    // Enrich eloquent models with fallback values if database columns are blank or null
                    $p->framework = $p->framework ?? (in_array('Laravel', $p->technology ?? []) ? 'Laravel' : (in_array('React', $p->technology ?? []) ? 'React' : (in_array('Next.js', $p->technology ?? []) ? 'Next.js' : (in_array('Vue', $p->technology ?? []) ? 'Vue' : 'Tailwind CSS'))));
                    $p->version = $p->version ?? '1.2.0';
                    $p->downloads = $p->downloads ?? rand(50, 450);
                    $p->views = $p->views ?? rand(500, 3000);
                    $p->author = $p->author ?? 'JUANET Core';
                    $p->license = $p->license ?? 'Regular License';
                    $p->tags = $p->tags ?? array_map('strtolower', array_slice($p->technology ?? [], 0, 3));
                    return $p;
                });
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceProduct query failed, utilizing fallback collections.', ['error' => $e->getMessage()]);
        }

        return collect($this->fallbackProducts)->map(function ($p) {
            return (object) $p;
        });
    }

    public function getFeatured(int $limit = 10): Collection
    {
        return $this->getActiveCollection()
            ->filter(fn($p) => !empty($p->is_featured))
            ->take($limit)
            ->values();
    }

    public function getTrending(int $limit = 10): Collection
    {
        // Trending defined as highest reviews or ratings
        return $this->getActiveCollection()
            ->sortByDesc(fn($p) => ($p->rating * 10) + $p->review_count)
            ->take($limit)
            ->values();
    }

    public function getNewest(int $limit = 10): Collection
    {
        return $this->getActiveCollection()
            ->filter(fn($p) => !empty($p->is_new))
            ->take($limit)
            ->values();
    }

    public function getBestSellers(int $limit = 10): Collection
    {
        return $this->getActiveCollection()
            ->filter(fn($p) => !empty($p->is_best_seller))
            ->take($limit)
            ->values();
    }

    public function search(array $criteria = []): Collection
    {
        $products = $this->getActiveCollection();

        // 1. Text Search (title, description, technology)
        if (!empty($criteria['search'])) {
            $query = strtolower($criteria['search']);
            $products = $products->filter(function ($p) use ($query) {
                $techMatch = false;
                if (is_array($p->technology)) {
                    foreach ($p->technology as $tech) {
                        if (str_contains(strtolower($tech), $query)) {
                            $techMatch = true;
                            break;
                        }
                    }
                }
                return str_contains(strtolower($p->title ?? ''), $query) ||
                    str_contains(strtolower($p->short_description ?? ''), $query) ||
                    str_contains(strtolower($p->description ?? ''), $query) ||
                    $techMatch;
            });
        }

        // 2. Category Filter
        if (!empty($criteria['category'])) {
            $catSlug = strtolower($criteria['category']);
            $products = $products->filter(function ($p) use ($catSlug) {
                if (isset($p->category) && is_object($p->category)) {
                    return strtolower($p->category->slug) === $catSlug;
                }
                $categorySlug = $p->category_slug ?? '';
                return strtolower($categorySlug) === $catSlug;
            });
        }

        // 3. Technology Filter
        if (!empty($criteria['technology'])) {
            $techFilter = strtolower($criteria['technology']);
            $products = $products->filter(function ($p) use ($techFilter) {
                if (!is_array($p->technology ?? null)) return false;
                foreach ($p->technology as $tech) {
                    if (strtolower($tech) === $techFilter) return true;
                }
                return false;
            });
        }

        // 3b. Framework Filter
        if (!empty($criteria['framework'])) {
            $fwFilter = strtolower($criteria['framework']);
            $products = $products->filter(function ($p) use ($fwFilter) {
                return strtolower($p->framework ?? '') === $fwFilter;
            });
        }

        // 4. Price Limits
        if (isset($criteria['min_price']) && $criteria['min_price'] !== '') {
            $products = $products->filter(fn($p) => $p->price >= (int) $criteria['min_price']);
        }
        if (isset($criteria['max_price']) && $criteria['max_price'] !== '') {
            $products = $products->filter(fn($p) => $p->price <= (int) $criteria['max_price']);
        }

        // 5. Badges/Flags (New, Sale, Popular, Featured, Free)
        if (!empty($criteria['free'])) {
            $products = $products->filter(fn($p) => $p->price == 0);
        }
        if (!empty($criteria['premium'])) {
            $products = $products->filter(fn($p) => $p->price > 0);
        }
        if (!empty($criteria['on_sale'])) {
            $products = $products->filter(fn($p) => !empty($p->previous_price) && $p->previous_price > $p->price);
        }
        if (!empty($criteria['newest'])) {
            $products = $products->filter(fn($p) => !empty($p->is_new));
        }
        if (!empty($criteria['popular'])) {
            $products = $products->filter(fn($p) => !empty($p->is_best_seller));
        }
        if (!empty($criteria['featured'])) {
            $products = $products->filter(fn($p) => !empty($p->is_featured));
        }

        // 5b. License Type Filter
        if (!empty($criteria['license_type'])) {
            $licFilter = strtolower($criteria['license_type']);
            $products = $products->filter(function ($p) use ($licFilter) {
                return str_contains(strtolower($p->license ?? ''), $licFilter);
            });
        }

        // 5c. Tag Filter
        if (!empty($criteria['tag'])) {
            $tagFilter = strtolower($criteria['tag']);
            $products = $products->filter(function ($p) use ($tagFilter) {
                if (is_array($p->tags ?? null)) {
                    foreach ($p->tags as $t) {
                        if (strtolower($t) === $tagFilter) return true;
                    }
                }
                return false;
            });
        }

        // 6. Rating Filter
        if (!empty($criteria['rating'])) {
            $products = $products->filter(fn($p) => $p->rating >= (float) $criteria['rating']);
        }

        // 7. Sorting
        $sortKey = $criteria['sorting'] ?? $criteria['sort'] ?? 'newest';
        switch ($sortKey) {
            case 'newest':
                $products = $products->sortByDesc(fn($p) => $p->created_at ?? $p->is_new ?? false);
                break;
            case 'oldest':
                $products = $products->sortBy(fn($p) => $p->created_at ?? $p->id);
                break;
            case 'highest_rated':
                $products = $products->sortByDesc(fn($p) => $p->rating ?? 0);
                break;
            case 'lowest_price':
                $products = $products->sortBy(fn($p) => $p->price ?? 0);
                break;
            case 'highest_price':
                $products = $products->sortByDesc(fn($p) => $p->price ?? 0);
                break;
            case 'best_selling':
                $products = $products->sortByDesc(fn($p) => $p->downloads ?? $p->is_best_seller ?? 0);
                break;
            case 'most_popular':
                $products = $products->sortByDesc(fn($p) => $p->views ?? (($p->rating ?? 0) * 10 + ($p->review_count ?? 0)));
                break;
            case 'recently_updated':
                $products = $products->sortByDesc(fn($p) => $p->updated_at ?? $p->created_at ?? 0);
                break;
            case 'alphabetical':
                $products = $products->sortBy(fn($p) => strtolower($p->title ?? ''));
                break;
        }

        return $products->values();
    }

    public function find(string $id)
    {
        try {
            if (Schema::hasTable('marketplace_products') && MarketplaceProduct::count() > 0) {
                return MarketplaceProduct::with('category')->find($id);
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceProduct query failed.', ['error' => $e->getMessage()]);
        }

        $found = collect($this->fallbackProducts)->firstWhere('id', $id);
        return $found ? (object) $found : null;
    }

    public function findBySlug(string $slug)
    {
        try {
            if (Schema::hasTable('marketplace_products') && MarketplaceProduct::count() > 0) {
                return MarketplaceProduct::with('category')->where('slug', $slug)->first();
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceProduct query failed.', ['error' => $e->getMessage()]);
        }

        $found = collect($this->fallbackProducts)->firstWhere('slug', $slug);
        return $found ? (object) $found : null;
    }
}
