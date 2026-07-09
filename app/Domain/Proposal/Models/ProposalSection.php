<?php

namespace App\Domain\Proposal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalSection extends Model
{
    protected $table = 'proposal_sections';

    protected $fillable = [
        'proposal_id',
        'title',
        'content',
        'sort_order'
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
