<?php

namespace App\Domain\Contract\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $fillable = [
        'organization_id',
        'client_id',
        'title',
        'document_url',
        'status'
    ];

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
