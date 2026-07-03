<?php

namespace App\Services;

use App\Repositories\SearchRepositoryInterface;
use App\Services\DTO\SearchResultDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SearchService implements SearchServiceInterface
{
    protected SearchRepositoryInterface $searchRepository;

    public function __construct(SearchRepositoryInterface $searchRepository)
    {
        $this->searchRepository = $searchRepository;
    }

    /**
     * Search the system globally. Filters results automatically by tenant and permissions.
     */
    public function search(
        string $query,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 20,
        int $offset = 0
    ): Collection {
        $results = $this->searchRepository->searchFullText($query, $modules, $organizationId, $limit * 2, $offset);

        return $this->filterAndMapResults($results, $organizationId)->take($limit);
    }

    /**
     * Fast sub-second autocomplete query for UI search bar suggestions.
     */
    public function autocomplete(
        string $query,
        ?string $organizationId = null,
        int $limit = 10
    ): Collection {
        $results = $this->searchRepository->searchAutocomplete($query, $organizationId, $limit * 2);

        return $this->filterAndMapResults($results, $organizationId)->take($limit);
    }

    /**
     * Semantic Search using embeddings and similarity metrics.
     */
    public function searchSemantic(
        array $embedding,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 10
    ): Collection {
        $results = $this->searchRepository->searchSemantic($embedding, $modules, $organizationId, $limit * 2);

        return $this->filterAndMapResults($results, $organizationId)->take($limit);
    }

    /**
     * Synchronize a specific searchable model with the global search index.
     */
    public function indexModel(SearchableInterface $model): void
    {
        $searchableData = $model->toSearchableArray();

        $this->searchRepository->updateIndex(
            get_class($model),
            (string) $model->id,
            [
                'organization_id' => $model->getOrganizationId(),
                'module' => $model->getSearchableModule(),
                'title' => $searchableData['title'],
                'description' => $searchableData['description'] ?? null,
                'content' => $searchableData['content'] ?? null,
                'url' => $model->getSearchUrl(),
                'permission_required' => $model->getSearchPermission(),
                'embedding' => $searchableData['embedding'] ?? null,
            ]
        );
    }

    /**
     * Delete a searchable model's entry from the global search index.
     */
    public function deindexModel(SearchableInterface $model): void
    {
        $this->searchRepository->deleteFromIndex(get_class($model), (string) $model->id);
    }

    /**
     * Admin tool: Reindex all models implementing SearchableInterface.
     */
    public function reindexAll(): int
    {
        $searchableClasses = config('search.searchable_models', [
            \App\Models\Notification::class,
            \App\Models\SearchablePlaceholder::class,
        ]);

        $totalIndexed = 0;

        foreach ($searchableClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            // Chunk database rows to prevent memory exhaustion
            $class::chunk(100, function ($models) use (&$totalIndexed) {
                foreach ($models as $model) {
                    if ($model instanceof SearchableInterface) {
                        $this->indexModel($model);
                        $totalIndexed++;
                    }
                }
            });
        }

        return $totalIndexed;
    }

    /**
     * Enforces tenant-isolation and role-permission based gating filters on search results.
     */
    protected function filterAndMapResults(Collection $results, ?string $organizationId): Collection
    {
        $user = auth()->user();

        return $results->filter(function ($item) use ($user, $organizationId) {
            // 1. Strict Tenant Isolation
            if ($organizationId !== null && $item->organization_id !== $organizationId) {
                return false;
            }

            // 2. Permission-Aware Gating
            if ($item->permission_required !== null) {
                if (!$user) {
                    return false;
                }
                
                // If the user has the required permission context in active tenant, permit entry
                return $user->hasPermission($item->permission_required, $organizationId);
            }

            return true;
        })->map(function ($item) {
            return SearchResultDto::fromModel(
                $item,
                (float) ($item->score ?? 1.0),
                $item->highlight ?? $item->description
            );
        });
    }
}
