<?php

namespace App\Services;

interface SearchableInterface
{
    /**
     * Map model attributes to search index format.
     * Must return an array with:
     * - 'title' (string)
     * - 'description' (string, optional)
     * - 'content' (string, optional)
     * - 'embedding' (array of floats, optional pgvector payload)
     *
     * @return array
     */
    public function toSearchableArray(): array;

    /**
     * Return the module name representing this record (e.g. 'crm', 'cms', etc.)
     *
     * @return string
     */
    public function getSearchableModule(): string;

    /**
     * Return the permission name (e.g. 'view leads') required to view this search result.
     * Returns null if public/available to all authenticated team members.
     *
     * @return string|null
     */
    public function getSearchPermission(): ?string;

    /**
     * Return the view deep-link URL for this record.
     *
     * @return string|null
     */
    public function getSearchUrl(): ?string;

    /**
     * Get the tenant organization ID if applicable.
     *
     * @return string|null
     */
    public function getOrganizationId(): ?string;
}
