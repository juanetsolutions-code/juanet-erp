<?php

namespace App\Domain\Notification\Repositories;

namespace App\Domain\Notification\Repositories\Eloquent;

use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Repositories\NotificationTemplateRepositoryInterface;
use App\Services\TenantContext;
use Illuminate\Support\Collection;

class NotificationTemplateRepository implements NotificationTemplateRepositoryInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?NotificationTemplate
    {
        return NotificationTemplate::find($id);
    }

    public function findByName(string $name, ?string $organizationId = null): ?NotificationTemplate
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        
        $query = NotificationTemplate::where('name', $name);
        
        if ($organizationId !== null) {
            $query->where(function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                  ->orWhereNull('organization_id');
            });
        } else {
            $query->whereNull('organization_id');
        }

        return $query->first();
    }

    public function create(array $data): NotificationTemplate
    {
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = $this->tenantContext->getTenantId();
        }
        return NotificationTemplate::create($data);
    }

    public function update(string $id, array $data): ?NotificationTemplate
    {
        $template = $this->find($id);
        if ($template) {
            $template->update($data);
        }
        return $template;
    }

    public function delete(string $id): bool
    {
        $template = $this->find($id);
        if ($template) {
            return $template->delete();
        }
        return false;
    }

    public function getAll(?string $organizationId = null): Collection
    {
        $organizationId = $organizationId ?? $this->tenantContext->getTenantId();
        $query = NotificationTemplate::query();

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId)->orWhereNull('organization_id');
        } else {
            $query->whereNull('organization_id');
        }

        return $query->get();
    }
}
