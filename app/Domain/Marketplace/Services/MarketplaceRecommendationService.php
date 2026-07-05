<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use Illuminate\Support\Collection;

class MarketplaceRecommendationService
{
    protected MarketplaceProductRepositoryInterface $productRepo;

    public function __construct(MarketplaceProductRepositoryInterface $productRepo)
    {
        $this->productRepo = $productRepo;
    }

    public function getRecommendationsForProduct(string $productId, int $limit = 4): Collection
    {
        $all = $this->productRepo->search();
        $target = $this->productRepo->find($productId);

        if (!$target) {
            return $this->productRepo->getTrending($limit);
        }

        // Recommend products from the same category first, excluding the target itself
        return $all->filter(function ($p) use ($target) {
                return $p->id !== $target->id && $p->category_id === $target->category_id;
            })
            ->take($limit)
            ->values();
    }
}
