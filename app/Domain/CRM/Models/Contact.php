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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model implements SearchableInterface
{
    use HasUuidV7, HasOptimisticLocking, Auditable, Searchable, SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'organization_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
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

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'contact_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'crm_taggables', 'taggable_id', 'tag_id')
            ->wherePivot('taggable_type', self::class);
    }

    // SearchableInterface implementation
    public function toSearchableArray(): array
    {
        $fullName = $this->full_name;
        return [
            'title' => $fullName,
            'description' => "CRM Contact profile: {$fullName} ({$this->email}). Title: {$this->job_title}.",
            'content' => "Contact Name: {$fullName}. Email: {$this->email}. Phone: {$this->phone}. Job Title: {$this->job_title}.",
        ];
    }

    public function getSearchableModule(): string
    {
        return 'crm';
    }

    public function getSearchPermission(): ?string
    {
        return 'view_contacts';
    }

    public function getSearchUrl(): ?string
    {
        return "/crm/contacts/{$this->id}";
    }

    public function getOrganizationId(): ?string
    {
        return $this->organization_id;
    }
}
