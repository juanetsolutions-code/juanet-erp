<?php

namespace App\Domain\Proposal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Proposal extends Model
{
    protected $table = 'proposals';

    protected $fillable = [
        'lead_id',
        'client_id',
        'organization_id',
        'title',
        'status',
        'total_amount',
        'expires_at'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'expires_at' => 'date'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ProposalSection::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProposalRevision::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProposalActivity::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
