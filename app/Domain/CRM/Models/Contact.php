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
        'middle_name',
        'last_name',
        'preferred_name',
        'email',
        'personal_email',
        'assistant_email',
        'phone',
        'work_phone',
        'mobile_phone',
        'home_phone',
        'assistant_phone',
        'fax',
        'whatsapp',
        'telegram',
        'signal',
        'job_title',
        'department',
        'decision_maker_level',
        'buying_influence',
        'linkedin_url',
        'twitter_url',
        'facebook_url',
        'youtube_url',
        'website',
        'profile_image_url',
        'preferred_language',
        'timezone',
        'birthday',
        'anniversary',
        'gender',
        'nationality',
        'languages',
        'notes',
        'manager_id',
        'buying_role',
        'is_decision_maker',
        'is_influencer',
        'is_technical_contact',
        'tier',
        'segment',
        'lifecycle_stage',
        'classification',
        'status',
        'sms_consent',
        'whatsapp_consent',
        'email_consent',
        'do_not_call',
        'do_not_email',
        'do_not_sms',
        'communication_preferences',
        'gdpr_consent_status',
        'health_score',
        'health_status',
        'health_breakdown',
        'custom_fields',
        'user_id',
        'lock_version',
    ];

    protected $casts = [
        'birthday' => 'date',
        'anniversary' => 'date',
        'languages' => 'array',
        'is_decision_maker' => 'boolean',
        'is_influencer' => 'boolean',
        'is_technical_contact' => 'boolean',
        'sms_consent' => 'boolean',
        'whatsapp_consent' => 'boolean',
        'email_consent' => 'boolean',
        'do_not_call' => 'boolean',
        'do_not_email' => 'boolean',
        'do_not_sms' => 'boolean',
        'communication_preferences' => 'array',
        'health_breakdown' => 'array',
        'custom_fields' => 'array',
        'lock_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} " . ($this->middle_name ? "{$this->middle_name} " : "") . "{$this->last_name}");
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Contact::class, 'manager_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class, 'contact_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ContactConsent::class, 'contact_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'contact_id');
    }

    public function contactMethods(): HasMany
    {
        return $this->hasMany(ContactMethod::class, 'contact_id');
    }

    public function companyAssociations(): HasMany
    {
        return $this->hasMany(ContactCompanyAssociation::class, 'contact_id');
    }

    public function associatedCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'crm_contact_company_associations', 'contact_id', 'company_id')
            ->whereNull('crm_contact_company_associations.deleted_at')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(ContactRelationship::class, 'contact_id');
    }

    public function inverseRelationships(): HasMany
    {
        return $this->hasMany(ContactRelationship::class, 'related_contact_id');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Domain\CRM\Activities\Models\Activity::class, 'loggable');
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
            'responsiveness' => 0,
            'meeting_frequency' => 0,
            'sales_influence' => 0,
            'relationship_strength' => 0,
            'outstanding_tasks' => 0,
        ];

        // 1. Engagement: count completed activities
        $completedCount = $this->activities()->where('is_completed', true)->count();
        $engagementPoints = min($completedCount * 3, 15);
        $score += $engagementPoints;
        $breakdown['engagement'] = $engagementPoints;

        // 2. Meeting Frequency: count meetings specifically
        $meetingsCount = $this->activities()
            ->where('is_completed', true)
            ->where('type', 'meeting')
            ->count();
        $meetingPoints = min($meetingsCount * 5, 15);
        $score += $meetingPoints;
        $breakdown['meeting_frequency'] = $meetingPoints;

        // 3. Responsiveness: verified contact methods or preferred communication filled
        $verifiedMethodsCount = $this->contactMethods()->where('is_verified', true)->count();
        $responsivenessPoints = min($verifiedMethodsCount * 5, 10);
        if ($this->preferred_language && $this->timezone) {
            $responsivenessPoints = min($responsivenessPoints + 5, 15);
        }
        $score += $responsivenessPoints;
        $breakdown['responsiveness'] = $responsivenessPoints;

        // 4. Sales Influence: Based on decision-maker level / buying influence
        $influencePoints = 0;
        if (in_array(strtolower($this->decision_maker_level ?? ''), ['c-level', 'vp', 'director'])) {
            $influencePoints += 10;
        } elseif (strtolower($this->decision_maker_level ?? '') === 'manager') {
            $influencePoints += 5;
        }

        if (in_array(strtolower($this->buying_influence ?? ''), ['decision maker', 'champion'])) {
            $influencePoints += 10;
        } elseif (strtolower($this->buying_influence ?? '') === 'influencer') {
            $influencePoints += 5;
        }
        $influencePoints = min($influencePoints, 20);
        $score += $influencePoints;
        $breakdown['sales_influence'] = $influencePoints;

        // 5. Relationship strength: number of direct relationships
        $relCount = $this->relationships()->count();
        $relPoints = min($relCount * 3, 15);
        $score += $relPoints;
        $breakdown['relationship_strength'] = $relPoints;

        // Deductions: outstanding overdue tasks
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
        $fullName = $this->full_name;
        $associatedString = $this->associatedCompanies->pluck('name')->implode(', ');
        $methodsString = $this->contactMethods->map(fn($m) => "{$m->type} ({$m->label}): {$m->value}")->implode('; ');

        return [
            'title' => $fullName,
            'description' => "CRM Contact: {$fullName} " . ($this->preferred_name ? "({$this->preferred_name}) " : "") . "- {$this->job_title} in {$this->department}. Health: {$this->health_status} ({$this->health_score}).",
            'content' => "Contact Name: {$fullName}. Preferred: {$this->preferred_name}. Email: {$this->email}. Phone: {$this->phone}. Job: {$this->job_title}. Dept: {$this->department}. Level: {$this->decision_maker_level}. Influence: {$this->buying_influence}. Companies: {$associatedString}. Socials: LinkedIn: {$this->linkedin_url}, X: {$this->twitter_url}. Methods: {$methodsString}. Notes: {$this->notes}.",
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
