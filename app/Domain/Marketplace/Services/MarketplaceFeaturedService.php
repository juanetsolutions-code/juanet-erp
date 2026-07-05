<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use Illuminate\Support\Collection;

class MarketplaceFeaturedService
{
    protected MarketplaceProductRepositoryInterface $productRepo;

    public function __construct(MarketplaceProductRepositoryInterface $productRepo)
    {
        $this->productRepo = $productRepo;
    }

    public function getFeaturedProducts(int $limit = 10): Collection
    {
        return $this->productRepo->getFeatured($limit);
    }
}
