<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuidV7, HasOptimisticLocking, Auditable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'status',
        'version',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the memberships of the user across organizations.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * Get the organizations the user belongs to.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
                    ->withPivot(['is_owner', 'status'])
                    ->withTimestamps();
    }

    /**
     * Roles belonging to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
                    ->withPivot('organization_id')
                    ->withTimestamps();
    }

    /**
     * Get the user's roles within a specific organization context.
     */
    public function rolesInOrganization(string $organizationId): BelongsToMany
    {
        return $this->roles()->wherePivot('organization_id', $organizationId);
    }

    /**
     * Check if the user has a specific permission in a specific organization context.
     */
    public function hasPermission(string $permissionSlug, ?string $organizationId = null): bool
    {
        if (!$organizationId) {
            return false;
        }

        // Fetch all roles of the user inside this organization, including their permissions
        $roles = $this->rolesInOrganization($organizationId)->with('permissions')->get();

        foreach ($roles as $role) {
            if ($role->permissions->contains('slug', $permissionSlug)) {
                return true;
            }
        }

        return false;
    }
}
