<?php

namespace App\Domain\Marketplace\Repositories;

use App\Domain\Marketplace\Contracts\MarketplaceCategoryRepositoryInterface;
use App\Domain\Marketplace\Models\MarketplaceCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class MarketplaceCategoryRepository implements MarketplaceCategoryRepositoryInterface
{
    protected array $fallbackCategories = [];

    public function __construct()
    {
        $this->fallbackCategories = [
            [
                'id' => '01900000-0000-0000-0000-000000000001',
                'name' => 'Laravel Development',
                'slug' => 'laravel',
                'icon' => 'terminal',
                'cover_image' => 'from-red-500/10 via-orange-500/5 to-transparent border-red-500/10',
                'product_count' => 4,
                'parent_slug' => null,
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000101',
                'name' => 'Laravel SaaS Starters',
                'slug' => 'laravel-starters',
                'icon' => 'zap',
                'cover_image' => 'from-orange-500/10 via-amber-500/5 to-transparent border-orange-500/10',
                'product_count' => 2,
                'parent_slug' => 'laravel',
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000102',
                'name' => 'Packages & Integrations',
                'slug' => 'laravel-packages',
                'icon' => 'package',
                'cover_image' => 'from-red-600/10 via-rose-500/5 to-transparent border-red-600/10',
                'product_count' => 2,
                'parent_slug' => 'laravel',
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000002',
                'name' => 'Next.js',
                'slug' => 'nextjs',
                'icon' => 'globe',
                'cover_image' => 'from-slate-500/10 via-slate-600/5 to-transparent border-slate-500/10',
                'product_count' => 2,
                'parent_slug' => null,
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000003',
                'name' => 'React',
                'slug' => 'react',
                'icon' => 'code-2',
                'cover_image' => 'from-blue-500/10 via-cyan-500/5 to-transparent border-blue-500/10',
                'product_count' => 3,
                'parent_slug' => null,
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000004',
                'name' => 'AI Prompts',
                'slug' => 'ai-prompts',
                'icon' => 'sparkles',
                'cover_image' => 'from-purple-500/10 via-violet-500/5 to-transparent border-purple-500/10',
                'product_count' => 1,
                'parent_slug' => null,
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000005',
                'name' => 'Website Templates',
                'slug' => 'templates',
                'icon' => 'layout',
                'cover_image' => 'from-emerald-500/10 via-teal-500/5 to-transparent border-emerald-500/10',
                'product_count' => 5,
                'parent_slug' => null,
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000501',
                'name' => 'Admin Dashboards',
                'slug' => 'admin-dashboards',
                'icon' => 'layout-grid',
                'cover_image' => 'from-sky-500/10 via-blue-500/5 to-transparent border-sky-500/10',
                'product_count' => 2,
                'parent_slug' => 'templates',
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000502',
                'name' => 'Business ERPs',
                'slug' => 'business-erp',
                'icon' => 'briefcase',
                'cover_image' => 'from-indigo-500/10 via-indigo-600/5 to-transparent border-indigo-500/10',
                'product_count' => 3,
                'parent_slug' => 'templates',
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000006',
                'name' => 'Brand Assets',
                'slug' => 'brand-assets',
                'icon' => 'palette',
                'cover_image' => 'from-pink-500/10 via-fuchsia-500/5 to-transparent border-pink-500/10',
                'product_count' => 1,
                'parent_slug' => null,
            ],
            [
                'id' => '01900000-0000-0000-0000-000000000007',
                'name' => 'Automation',
                'slug' => 'automation',
                'icon' => 'cpu',
                'cover_image' => 'from-amber-500/10 via-yellow-500/5 to-transparent border-amber-500/10',
                'product_count' => 1,
                'parent_slug' => null,
            ],
        ];
    }

    public function getAll(): Collection
    {
        try {
            if (Schema::hasTable('marketplace_categories') && MarketplaceCategory::count() > 0) {
                return MarketplaceCategory::get();
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceCategory query failed, using static fallback context.', ['error' => $e->getMessage()]);
        }

        return collect($this->fallbackCategories)->map(function ($item) {
            return (object) $item;
        });
    }

    public function findBySlug(string $slug)
    {
        try {
            if (Schema::hasTable('marketplace_categories') && MarketplaceCategory::count() > 0) {
                return MarketplaceCategory::where('slug', $slug)->first();
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceCategory query by slug failed.', ['error' => $e->getMessage()]);
        }

        $found = collect($this->fallbackCategories)->firstWhere('slug', $slug);
        return $found ? (object) $found : null;
    }
}
