<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, HasUuidV7, HasOptimisticLocking, Auditable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'is_system',
        'version',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the organization that owns the role (null if system-wide/global role).
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
                    ->withTimestamps();
    }

    /**
     * Users that have been assigned this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
                    ->withPivot('organization_id')
                    ->withTimestamps();
    }

    /**
     * Scope a query to only include system-wide global roles.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Scope a query to only include organization-specific roles.
     */
    public function scopeForTenant($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
