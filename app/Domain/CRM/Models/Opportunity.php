<?php

namespace App\Domain\CRM\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Traits\Searchable;
use App\Services\SearchableInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Opportunity extends Model implements SearchableInterface
{
    use HasUuidV7, HasOptimisticLocking, Auditable, Searchable, SoftDeletes;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'organization_id',
        'company_id',
        'contact_id',
        'pipeline_id',
        'pipeline_stage_id',
        'user_id',
        'name',
        'amount',
        'close_date',
        'status',
        'custom_fields',
        'lock_version',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'close_date' => 'date',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
            'description' => "CRM Opportunity: {$this->name}. Amount: {$this->amount}. Status: {$this->status}.",
            'content' => "Opportunity Name: {$this->name}. Amount: {$this->amount}. Close Date: {$this->close_date}. Status: {$this->status}.",
        ];
    }

    public function getSearchableModule(): string
    {
        return 'crm';
    }

    public function getSearchPermission(): ?string
    {
        return 'view_opportunities';
    }

    public function getSearchUrl(): ?string
    {
        return "/crm/opportunities/{$this->id}";
    }

    public function getOrganizationId(): ?string
    {
        return $this->organization_id;
    }
}
