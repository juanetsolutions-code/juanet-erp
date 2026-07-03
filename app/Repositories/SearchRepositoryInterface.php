<?php

namespace App\Repositories;

use App\Models\SearchIndex;
use Illuminate\Support\Collection;

interface SearchRepositoryInterface
{
    /**
     * Search using PostgreSQL Full-Text search across the unified search index table.
     *
     * @param string $query Standard user text query
     * @param array $modules Filter by specific modules (e.g. ['crm', 'cms'])
     * @param string|null $organizationId Tenant organization context ID
     * @param int $limit Maximum results to retrieve
     * @param int $offset Offset pagination count
     * @return Collection Collection of matching SearchIndex rows with scores and highlights
     */
    public function searchFullText(
        string $query,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 20,
        int $offset = 0
    ): Collection;

    /**
     * Search titles using trigrams or prefix matching for super-fast autocomplete.
     *
     * @param string $query User prefix or trigram
     * @param string|null $organizationId Tenant organization context ID
     * @param int $limit Maximum results to return
     * @return Collection Collection of simplified SearchIndex suggestions
     */
    public function searchAutocomplete(
        string $query,
        ?string $organizationId = null,
        int $limit = 10
    ): Collection;

    /**
     * Search indices using pgvector cosine similarity. Fallback gracefully if pgvector is missing.
     *
     * @param array $embedding Dimensional float array representing the query embedding
     * @param array $modules Filter by specific modules
     * @param string|null $organizationId Tenant organization context ID
     * @param int $limit Maximum results to retrieve
     * @return Collection Collection of SearchIndex records with cosine similarity score
     */
    public function searchSemantic(
        array $embedding,
        array $modules = [],
        ?string $organizationId = null,
        int $limit = 10
    ): Collection;

    /**
     * Insert or update a search index entry.
     *
     * @param string $searchableType Class name of searchable model
     * @param string $searchableId Primary key of searchable model
     * @param array $data Attributes list
     * @return SearchIndex The created or updated SearchIndex model
     */
    public function updateIndex(string $searchableType, string $searchableId, array $data): SearchIndex;

    /**
     * Delete a search index entry.
     *
     * @param string $searchableType Class name of searchable model
     * @param string $searchableId Primary key of searchable model
     * @return bool True if deleted successfully
     */
    public function deleteFromIndex(string $searchableType, string $searchableId): bool;
}
