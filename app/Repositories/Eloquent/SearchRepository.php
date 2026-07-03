<?php

namespace App\Repositories\Eloquent;

use App\Models\SearchIndex;
use App\Repositories\SearchRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchRepository implements SearchRepositoryInterface
{
    /**
     * Search using PostgreSQL Full-Text search across the unified search index table.
     */
    public function searchFullText(
        string $query,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 20,
        int $offset = 0
    ): Collection {
        if (empty(trim($query))) {
            return collect();
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite Fallback for testing environments
            $searchQuery = SearchIndex::query();

            if ($organizationId) {
                $searchQuery->where('organization_id', $organizationId);
            }

            if (!empty($modules)) {
                $searchQuery->whereIn('module', $modules);
            }

            $searchQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', '%' . $query . '%')
                  ->orWhere('description', 'like', '%' . $query . '%')
                  ->orWhere('content', 'like', '%' . $query . '%');
            });

            return $searchQuery->limit($limit)->offset($offset)->get()->map(function ($item) {
                $item->score = 1.0;
                $item->highlight = $item->description;
                return $item;
            });
        }

        // Production PostgreSQL Full-Text Search Engine
        $dbQuery = SearchIndex::query();

        if ($organizationId) {
            $dbQuery->where('organization_id', $organizationId);
        }

        if (!empty($modules)) {
            $dbQuery->whereIn('module', $modules);
        }

        // Match using plainto_tsquery
        $dbQuery->whereRaw("searchable_text @@ plainto_tsquery('english', ?)", [$query]);

        // Select items, ranking score, and dynamic keyword highlights
        $dbQuery->select('search_indexes.*')
            ->selectRaw("ts_rank_cd(searchable_text, plainto_tsquery('english', ?)) as score", [$query])
            ->selectRaw("ts_headline('english', coalesce(description, content, ''), plainto_tsquery('english', ?), 'StartSel=<mark class=\"bg-yellow-200 text-slate-900 rounded px-1 font-semibold\">, StopSel=</mark>, MaxWords=30, MinWords=15, ShortWord=3') as highlight", [$query]);

        return $dbQuery->orderBy('score', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Search titles using trigrams or prefix matching for super-fast autocomplete.
     */
    public function searchAutocomplete(
        string $query,
        ?string $organizationId = null,
        int $limit = 10
    ): Collection {
        if (empty(trim($query))) {
            return collect();
        }

        $dbQuery = SearchIndex::query();

        if ($organizationId) {
            $dbQuery->where('organization_id', $organizationId);
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return $dbQuery->where('title', 'like', '%' . $query . '%')
                ->limit($limit)
                ->get();
        }

        // For PostgreSQL, use a fast trigram similarity ordering combined with prefix ILIKE
        return $dbQuery->where(function ($q) use ($query) {
                $q->where('title', 'ilike', $query . '%')
                  ->orWhereRaw("similarity(title, ?) > 0.15", [$query]);
            })
            ->select('id', 'organization_id', 'module', 'title', 'url')
            ->selectRaw("similarity(title, ?) as trgm_score", [$query])
            ->orderBy('trgm_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search indices using pgvector cosine similarity. Fallback gracefully if pgvector is missing.
     */
    public function searchSemantic(
        array $embedding,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 10
    ): Collection {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support pgvector, fallback to standard listing
            $q = SearchIndex::query()->limit($limit);
            if ($organizationId) $q->where('organization_id', $organizationId);
            if (!empty($modules)) $q->whereIn('module', $modules);
            return $q->get()->map(function($item) {
                $item->score = 0.99;
                $item->highlight = $item->description;
                return $item;
            });
        }

        // Check if pgvector is installed and active
        $hasPgVector = false;
        try {
            $hasPgVector = DB::selectOne("SELECT extname FROM pg_extension WHERE extname = 'vector'") !== null;
        } catch (\Throwable $e) {
            // Log warning or ignore
        }

        if (!$hasPgVector) {
            Log::warning('Semantic Search requested but pgvector is not active in PostgreSQL database.');
            // Fallback to latest records matching filters
            $q = SearchIndex::query()->orderBy('created_at', 'desc')->limit($limit);
            if ($organizationId) $q->where('organization_id', $organizationId);
            if (!empty($modules)) $q->whereIn('module', $modules);
            return $q->get()->map(function($item) {
                $item->score = 0.50;
                $item->highlight = $item->description;
                return $item;
            });
        }

        // Convert PHP float array to PostgreSQL vector literal syntax "[val1,val2,...]"
        $vectorString = '[' . implode(',', $embedding) . ']';

        $dbQuery = SearchIndex::query();

        if ($organizationId) {
            $dbQuery->where('organization_id', $organizationId);
        }

        if (!empty($modules)) {
            $dbQuery->whereIn('module', $modules);
        }

        // pgvector Cosine Distance operator is <=>
        // Cosine similarity = 1 - Cosine Distance
        return $dbQuery->select('search_indexes.*')
            ->selectRaw("(1 - (embedding <=> ?::vector)) as score", [$vectorString])
            ->whereNotNull('embedding')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Insert or update a search index entry.
     */
    public function updateIndex(string $searchableType, string $searchableId, array $data): SearchIndex
    {
        return SearchIndex::updateOrCreate(
            [
                'searchable_type' => $searchableType,
                'searchable_id' => $searchableId,
            ],
            [
                'organization_id' => $data['organization_id'] ?? null,
                'module' => $data['module'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'] ?? null,
                'url' => $data['url'] ?? null,
                'permission_required' => $data['permission_required'] ?? null,
                'embedding' => $data['embedding'] ?? null,
            ]
        );
    }

    /**
     * Delete a search index entry.
     */
    public function deleteFromIndex(string $searchableType, string $searchableId): bool
    {
        return SearchIndex::where('searchable_type', $searchableType)
            ->where('searchable_id', $searchableId)
            ->delete() > 0;
    }
}
