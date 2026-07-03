<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class CollectionHelper
{
    /**
     * Paginate a Laravel Support Collection manually.
     */
    public static function paginate(Collection $collection, int $perPage = 15, ?int $page = null, array $options = []): LengthAwarePaginator
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $offset = ($page * $perPage) - $perPage;

        $paginatedItems = $collection->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $paginatedItems,
            $collection->count(),
            $perPage,
            $page,
            array_merge([
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ], $options)
        );
    }

    /**
     * Distribute elements of a collection into a specific number of equal buckets/groups.
     */
    public static function distribute(Collection $collection, int $numberOfBuckets): Collection
    {
        if ($numberOfBuckets <= 0) {
            throw new \InvalidArgumentException('Number of buckets must be greater than zero.');
        }

        $buckets = collect();
        for ($i = 0; $i < $numberOfBuckets; $i++) {
            $buckets->put($i, collect());
        }

        $collection->values()->each(function ($item, $index) use ($buckets, $numberOfBuckets) {
            $bucketIndex = $index % $numberOfBuckets;
            $buckets->get($bucketIndex)->push($item);
        });

        return $buckets;
    }
}
