<?php

namespace App\Domain\Proposal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class ProposalComment extends Model
{
    protected $table = 'proposal_comments';

    protected $fillable = [
        'proposal_id',
        'user_id',
        'content',
        'parent_id'
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProposalComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ProposalComment::class, 'parent_id');
    }
}
