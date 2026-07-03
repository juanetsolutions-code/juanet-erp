<?php

namespace App\Models;

use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasUuidV7;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'owner_id',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get the cast value of the setting.
     */
    public function getCastValue(): mixed
    {
        $raw = $this->value;
        if ($raw === null) {
            return null;
        }

        if ($this->is_encrypted) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable $e) {
                // If decryption fails, return the raw value as fallback
            }
        }

        return match ($this->type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($raw, true),
            'integer', 'int' => (int) $raw,
            'float' => (float) $raw,
            default => $raw,
        };
    }

    /**
     * Set and cast the value of the setting before storing.
     */
    public function setCastValue(mixed $value, string $type, bool $encrypt = false): void
    {
        $this->type = $type;
        $this->is_encrypted = $encrypt;

        $serialized = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };

        if ($encrypt) {
            $serialized = Crypt::encryptString($serialized);
        }

        $this->value = $serialized;
    }
}
