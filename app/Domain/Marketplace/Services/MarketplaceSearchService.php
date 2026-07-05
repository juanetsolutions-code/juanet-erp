<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Contracts\MarketplaceProductRepositoryInterface;
use Illuminate\Support\Collection;

class MarketplaceSearchService
{
    protected MarketplaceProductRepositoryInterface $productRepo;

    public function __construct(MarketplaceProductRepositoryInterface $productRepo)
    {
        $this->productRepo = $productRepo;
    }

    public function search(array $filters): Collection
    {
        return $this->productRepo->search($filters);
    }
}
