<?php

namespace App\Repositories\Eloquent;

use App\Models\StoredFile;
use App\Repositories\FileRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FileRepository implements FileRepositoryInterface
{
    protected \App\Services\TenantContext $tenantContext;

    public function __construct(\App\Services\TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    public function find(string $id): ?StoredFile
    {
        return StoredFile::find($id);
    }

    public function create(array $data): StoredFile
    {
        // Auto-assign active tenant if not explicitly set
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = $this->tenantContext->getTenantId();
        }
        return StoredFile::create($data);
    }

    public function update(string $id, array $data): ?StoredFile
    {
        $file = $this->find($id);
        if ($file) {
            $file->update($data);
        }
        return $file;
    }

    public function delete(string $id): bool
    {
        $file = $this->find($id);
        if ($file) {
            return (bool) $file->delete();
        }
        return false;
    }

    public function getByOrganization(?string $orgId = null, ?string $userId = null, ?string $category = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        $query = StoredFile::query();

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        } else {
            $query->whereNull('organization_id');
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getByUser(string $userId, ?string $orgId = null, ?string $category = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        $query = StoredFile::where('user_id', $userId);

        if ($orgId !== null) {
            $query->where('organization_id', $orgId);
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getExpiredTemporaryFiles(): Collection
    {
        return StoredFile::where('is_temporary', true)
            ->where('expires_at', '<', Carbon::now())
            ->get();
    }

    public function search(string $query, ?string $orgId = null, ?string $userId = null): Collection
    {
        $orgId = $orgId ?? $this->tenantContext->getTenantId();
        $dbQuery = StoredFile::where('name', 'like', "%{$query}%");

        if ($orgId !== null) {
            $dbQuery->where('organization_id', $orgId);
        }

        if ($userId !== null) {
            $dbQuery->where('user_id', $userId);
        }

        return $dbQuery->orderBy('created_at', 'desc')->get();
    }
}
