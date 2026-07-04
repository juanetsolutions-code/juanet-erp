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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Company extends Model implements SearchableInterface
{
    use HasUuidV7, HasOptimisticLocking, Auditable, Searchable, SoftDeletes;

    protected $table = 'crm_companies';

    protected $fillable = [
        'organization_id',
        'industry_id',
        'name',
        'trading_name',
        'registration_number',
        'tax_number',
        'industry_classification',
        'company_size',
        'annual_revenue',
        'employees_count',
        'parent_id',
        'status',
        'user_id',
        'territory',
        'timezone',
        'preferred_language',
        'currency',
        'domain',
        'phone',
        'website',
        'address',
        'social_media_profiles',
        'custom_fields',
        'health_score',
        'health_status',
        'health_breakdown',
        'lock_version',
    ];

    protected $casts = [
        'annual_revenue' => 'decimal:2',
        'employees_count' => 'integer',
        'social_media_profiles' => 'array',
        'custom_fields' => 'array',
        'health_score' => 'integer',
        'health_breakdown' => 'array',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subsidiaries(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CompanyLocation::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(\App\Domain\CRM\Activities\Models\Activity::class, 'loggable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(\App\Domain\CRM\Activities\Models\ActivityNote::class, 'notable');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'crm_taggables', 'taggable_id', 'tag_id')
            ->wherePivot('taggable_type', self::class);
    }

    public function recalculateHealthScore(): int
    {
        $score = 70; // Base baseline
        
        $breakdown = [
            'base_baseline' => 70,
            'engagement' => 0,
            'opportunities' => 0,
            'outstanding_tasks' => 0,
            'ai_readiness' => 10, // Placeholder
        ];

        // 1. Engagement: count completed activities
        $completedCount = $this->activities()->where('is_completed', true)->count();
        $engagementPoints = min($completedCount * 3, 15);
        $score += $engagementPoints;
        $breakdown['engagement'] = $engagementPoints;

        // 2. Opportunities: count open or won
        $openCount = $this->opportunities()->where('status', 'open')->count();
        $wonCount = $this->opportunities()->where('status', 'won')->count();
        $oppPoints = min(($openCount * 5) + ($wonCount * 10), 20);
        $score += $oppPoints;
        $breakdown['opportunities'] = $oppPoints;

        // 3. Outstanding tasks: deduct for pending overdue activities
        $overdueCount = $this->activities()
            ->where('is_completed', false)
            ->where('due_at', '<', now())
            ->count();
        $deductions = $overdueCount * 5;
        $score = max(0, $score - $deductions);
        $breakdown['outstanding_tasks'] = -$deductions;

        // Ensure 0-100 bounds
        $score = min($score, 100);

        // Determine status
        $status = 'Healthy';
        if ($score < 50) {
            $status = 'Critical';
        } elseif ($score < 80) {
            $status = 'Warning';
        }

        $this->update([
            'health_score' => $score,
            'health_status' => $status,
            'health_breakdown' => $breakdown,
        ]);

        return $score;
    }

    // SearchableInterface implementation
    public function toSearchableArray(): array
    {
        $locationsString = $this->locations->map(function ($loc) {
            return "{$loc->name} ({$loc->type}): {$loc->address}, {$loc->city}, {$loc->state}, {$loc->country} {$loc->postal_code}";
        })->implode('; ');

        $customFieldsString = is_array($this->custom_fields) 
            ? collect($this->custom_fields)->map(fn($v, $k) => is_array($v) ? json_encode($v) : "$k: $v")->implode(', ')
            : '';

        return [
            'title' => $this->name,
            'description' => "CRM Company: {$this->name} " . ($this->trading_name ? "({$this->trading_name}) " : "") . " - {$this->status}. Health: {$this->health_status} ({$this->health_score}).",
            'content' => "Company Name: {$this->name}. Trading Name: {$this->trading_name}. Domain: {$this->domain}. Reg: {$this->registration_number}. Tax: {$this->tax_number}. Size: {$this->company_size}. Industry Class: {$this->industry_classification}. Revenue: {$this->annual_revenue}. Employees: {$this->employees_count}. Phone: {$this->phone}. Website: {$this->website}. Main Address: {$this->address}. Locations: {$locationsString}. Custom Fields: {$customFieldsString}.",
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
