<?php

namespace App\Domain\Marketplace\Contracts;

use Illuminate\Support\Collection;

interface MarketplaceProductRepositoryInterface
{
    public function getFeatured(int $limit = 10): Collection;

    public function getTrending(int $limit = 10): Collection;

    public function getNewest(int $limit = 10): Collection;

    public function getBestSellers(int $limit = 10): Collection;

    public function search(array $criteria = []): Collection;

    public function find(string $id);

    public function findBySlug(string $slug);
}
