<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use App\Domain\Marketplace\Contracts\MarketplaceCategoryRepositoryInterface;

class MarketplaceHomepageService
{
    protected MarketplaceProductRepositoryInterface $productRepo;
    protected MarketplaceCategoryRepositoryInterface $categoryRepo;

    public function __construct(
        MarketplaceProductRepositoryInterface $productRepo,
        MarketplaceCategoryRepositoryInterface $categoryRepo
    ) {
        $this->productRepo = $productRepo;
        $this->categoryRepo = $categoryRepo;
    }

    public function getHomepageData(): array
    {
        return [
            'categories' => $this->categoryRepo->getAll(),
            'featured_products' => $this->productRepo->getFeatured(6),
            'trending_products' => $this->productRepo->getTrending(4),
            'newest_products' => $this->productRepo->getNewest(4),
            'best_sellers' => $this->productRepo->getBestSellers(4),
        ];
    }
}
