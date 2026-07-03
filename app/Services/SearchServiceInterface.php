<?php

namespace App\Services;

use App\Services\DTO\SearchResultDto;
use Illuminate\Support\Collection;

interface SearchServiceInterface
{
    /**
     * Search the system globally. Filters results automatically by tenant and permissions.
     *
     * @param string $query Standard user text query
     * @param array $modules Filter by specific modules (e.g. ['crm', 'cms'])
     * @param string|null $organizationId Active organization context
     * @param int $limit Max results to fetch
     * @param int $offset Offset pagination
     * @return Collection Collection of SearchResultDto objects
     */
    public function search(
        string $query,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 20,
        int $offset = 0
    ): Collection;

    /**
     * Fast sub-second autocomplete query for UI search bar suggestions.
     *
     * @param string $query Prefix or partial text
     * @param string|null $organizationId Active organization context
     * @param int $limit Suggestions limit
     * @return Collection Collection of lightweight SearchResultDto suggestions
     */
    public function autocomplete(
        string $query,
        ?string $organizationId = null,
        int $limit = 10
    ): Collection;

    /**
     * Semantic Search using embeddings and similarity metrics.
     *
     * @param array $embedding Vector array of floating point numbers
     * @param array $modules Filter by specific modules
     * @param string|null $organizationId Active organization context
     * @param int $limit Suggestions limit
     * @return Collection Collection of semantic SearchResultDto results
     */
    public function searchSemantic(
        array $embedding,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 10
    ): Collection;

    /**
     * Synchronize a specific searchable model with the global search index.
     *
     * @param SearchableInterface $model
     * @return void
     */
    public function indexModel(SearchableInterface $model): void;

    /**
     * Delete a searchable model's entry from the global search index.
     *
     * @param SearchableInterface $model
     * @return void
     */
    public function deindexModel(SearchableInterface $model): void;

    /**
     * Admin tool: Reindex all models implementing SearchableInterface.
     * Returns count of successfully indexed items.
     *
     * @return int
     */
    public function reindexAll(): int;
}
