<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use App\Traits\Searchable;
use App\Services\SearchableInterface;
use Illuminate\Database\Eloquent\Model;

class SearchablePlaceholder extends Model implements SearchableInterface
{
    use HasUuidV7, Searchable;

    protected $table = 'crm_leads';

    protected $fillable = ['organization_id', 'name', 'email', 'status'];

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->name,
            'description' => "Lead contact profile for {$this->name} ({$this->email}). Status: {$this->status}.",
            'content' => "CRM Lead record name: {$this->name}. Associated email address: {$this->email}. Stage progress tracker is currently marked as: {$this->status}.",
        ];
    }

    public function getSearchableModule(): string
    {
        return 'general';
    }

    public function getSearchPermission(): ?string
    {
        return 'view_leads';
    }

    public function getSearchUrl(): ?string
    {
        return "/placeholder/{$this->id}";
    }

    public function getOrganizationId(): ?string
    {
        return $this->organization_id;
    }
}
