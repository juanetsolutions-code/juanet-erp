<?php

namespace App\Domain\Proposal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ProposalRevision extends Model
{
    protected $table = 'proposal_revisions';

    protected $fillable = [
        'proposal_id',
        'version',
        'content',
        'created_by'
    ];

    protected $casts = [
        'content' => 'array'
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
