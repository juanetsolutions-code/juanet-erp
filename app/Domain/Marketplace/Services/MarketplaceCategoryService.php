<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Contracts\MarketplaceCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class MarketplaceCategoryService
{
    protected MarketplaceCategoryRepositoryInterface $categoryRepo;

    public function __construct(MarketplaceCategoryRepositoryInterface $categoryRepo)
    {
        $this->categoryRepo = $categoryRepo;
    }

    public function getAllCategories(): Collection
    {
        return $this->categoryRepo->getAll();
    }

    public function getCategoryBySlug(string $slug)
    {
        return $this->categoryRepo->findBySlug($slug);
    }
}
