<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Traits\Searchable;
use App\Services\SearchableInterface;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model implements SearchableInterface
{
    use HasUuidV7, HasOptimisticLocking, Auditable, Searchable, SoftDeletes;

    protected $table = 'crm_companies';

    protected $fillable = [
        'organization_id',
        'industry_id',
        'name',
        'domain',
        'phone',
        'website',
        'address',
        'custom_fields',
        'lock_version',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'company_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'company_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'company_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'crm_taggables', 'taggable_id', 'tag_id')
            ->wherePivot('taggable_type', self::class);
    }

    // SearchableInterface implementation
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->name,
            'description' => "CRM Company profile: {$this->name} ({$this->domain}).",
            'content' => "Company Name: {$this->name}. Domain: {$this->domain}. Phone: {$this->phone}. Website: {$this->website}. Address: {$this->address}.",
        ];
    }

    public function getSearchableModule(): string
    {
        return 'crm';
    }

    public function getSearchPermission(): ?string
    {
        return 'view_companies';
    }

    public function getSearchUrl(): ?string
    {
        return "/crm/companies/{$this->id}";
    }

    public function getOrganizationId(): ?string
    {
        return $this->organization_id;
    }
}
