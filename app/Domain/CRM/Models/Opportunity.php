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
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        // Extended Phase 4.6 attributes
        'opportunity_number',
        'description',
        'source',
        'expected_close_date',
        'actual_close_date',
        'estimated_revenue',
        'weighted_revenue',
        'win_probability',
        'currency',
        'forecast_category',
        'competitor',
        'lost_reason',
        'won_reason',
        'sales_team',
        
        // AI Readiness Placeholders
        'ai_confidence',
        'ai_win_probability_prediction',
        'ai_next_best_action',
        'ai_deal_health',
        'ai_risk_detection',
        'ai_upsell_recommendations',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'close_date' => 'date',
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
        'estimated_revenue' => 'decimal:2',
        'weighted_revenue' => 'decimal:2',
        'win_probability' => 'integer',
        'ai_confidence' => 'decimal:2',
        'ai_win_probability_prediction' => 'decimal:2',
        'ai_upsell_recommendations' => 'array',
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

    public function products(): HasMany
    {
        return $this->hasMany(OpportunityProduct::class, 'opportunity_id');
    }

    /**
     * Calculate pricing totals and recurrence figures
     */
    public function calculatePricingSummary(): array
    {
        $products = $this->products;
        
        $subtotal = 0.0;
        $discounts = 0.0;
        $tax = 0.0;
        $grandTotal = 0.0;
        $recurringTotal = 0.0;
        $projectedMrr = 0.0;

        foreach ($products as $p) {
            $base = $p->unit_price * $p->quantity;
            $subtotal += $base;
            $discounts += (float) $p->discount;
            $tax += (float) $p->tax;
            $grandTotal += (float) $p->subtotal;

            if ($p->recurring_billing_flag) {
                $itemSubtotal = (float) $p->subtotal;
                $recurringTotal += $itemSubtotal;
                
                $interval = strtolower($p->subscription_interval ?? 'monthly');
                if ($interval === 'annual' || $interval === 'yearly') {
                    $projectedMrr += $itemSubtotal / 12.0;
                } else {
                    $projectedMrr += $itemSubtotal;
                }
            }
        }

        $projectedArr = $projectedMrr * 12.0;
        $weightedForecast = $grandTotal * ($this->win_probability / 100.0);

        return [
            'subtotal' => round($subtotal, 2),
            'discounts' => round($discounts, 2),
            'tax' => round($tax, 2),
            'recurring_total' => round($recurringTotal, 2),
            'grand_total' => round($grandTotal, 2),
            'weighted_forecast' => round($weightedForecast, 2),
            'projected_mrr' => round($projectedMrr, 2),
            'projected_arr' => round($projectedArr, 2),
        ];
    }

    /**
     * Recalculate totals and persist them
     */
    public function recalculateTotals(): void
    {
        $summary = $this->calculatePricingSummary();
        
        $this->amount = $summary['grand_total'];
        $this->estimated_revenue = $summary['grand_total'];
        $this->weighted_revenue = $summary['weighted_forecast'];
        
        $customFields = $this->custom_fields ?? [];
        $customFields['financial_summary'] = $summary;
        $this->custom_fields = $customFields;
        
        $this->save();
    }

    // SearchableInterface implementation
    public function toSearchableArray(): array
    {
        $companyName = $this->company ? $this->company->name : '';
        $contactName = $this->contact ? ($this->contact->first_name . ' ' . $this->contact->last_name) : '';
        $productNames = $this->products->pluck('product_name')->implode(', ');
        $tagsList = $this->tags->pluck('name')->implode(', ');
        $pipelineName = $this->pipeline ? $this->pipeline->name : '';
        $stageName = $this->stage ? $this->stage->name : '';

        return [
            'title' => $this->name,
            'description' => "CRM Opportunity #{$this->opportunity_number}: {$this->name}. Company: {$companyName}. Contact: {$contactName}. Pipeline: {$pipelineName}. Stage: {$stageName}. Amount: {$this->amount}. Status: {$this->status}.",
            'content' => "Opportunity Name: {$this->name}. Number: {$this->opportunity_number}. Company: {$companyName}. Contact: {$contactName}. Products: {$productNames}. Tags: {$tagsList}. Pipeline: {$pipelineName}. Stage: {$stageName}. Status: {$this->status}. Source: {$this->source}. Competitor: {$this->competitor}.",
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
