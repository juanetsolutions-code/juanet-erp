<?php

namespace App\Helpers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;

class PaginationHelper
{
    /**
     * Build standard metadata from any paginator.
     */
    public static function getMeta(Paginator $paginator): array
    {
        $meta = [
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'count' => $paginator->count(),
            'has_more' => $paginator->hasMorePages(),
            'links' => [
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        // If it is a LengthAwarePaginator, we can extract total records and total pages
        if ($paginator instanceof LengthAwarePaginator) {
            $meta['total'] = $paginator->total();
            $meta['total_pages'] = $paginator->lastPage();
        }

        return $meta;
    }
}
