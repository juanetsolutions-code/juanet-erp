<?php

namespace App\Services;

use App\Models\Organization;

class TenantContext
{
    protected ?Organization $tenant = null;

    /**
     * Set the current active tenant organization.
     */
    public function setTenant(Organization $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current active tenant organization.
     */
    public function getTenant(): ?Organization
    {
        return $this->tenant;
    }

    /**
     * Get the current active tenant organization ID.
     */
    public function getTenantId(): ?string
    {
        return $this->tenant?->id;
    }
}
