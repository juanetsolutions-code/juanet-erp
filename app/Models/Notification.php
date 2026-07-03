<?php

namespace App\Models;

use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use App\Traits\Searchable;
use App\Services\SearchableInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model implements SearchableInterface
{
    use HasUuidV7, HasOptimisticLocking, Searchable;

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'body',
        'type',
        'category',
        'priority',
        'is_read',
        'data',
        'version',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->body,
            'content' => "Notification category: {$this->category}. Priority Level: {$this->priority}. Detailed content: {$this->body}.",
        ];
    }

    public function getSearchableModule(): string
    {
        return 'notifications';
    }

    public function getSearchPermission(): ?string
    {
        return null; // Publicly available to the owner user
    }

    public function getSearchUrl(): ?string
    {
        return "/notifications";
    }

    public function getOrganizationId(): ?string
    {
        return $this->organization_id;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
