<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class IdempotentKey extends Model
{
    use HasUuidV7;

    protected $table = 'idempotent_keys';

    protected $fillable = [
        'key',
        'status',
        'result',
        'expires_at',
    ];

    protected $casts = [
        'result' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
