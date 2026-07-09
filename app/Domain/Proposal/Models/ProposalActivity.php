<?php

namespace App\Domain\Proposal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ProposalActivity extends Model
{
    protected $table = 'proposal_activities';

    protected $fillable = [
        'proposal_id',
        'user_id',
        'activity',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
