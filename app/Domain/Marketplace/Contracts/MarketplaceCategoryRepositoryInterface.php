<?php

namespace App\Domain\Marketplace\Contracts;

use Illuminate\Support\Collection;

interface MarketplaceCategoryRepositoryInterface
{
    public function getAll(): Collection;

    public function findBySlug(string $slug);
}
