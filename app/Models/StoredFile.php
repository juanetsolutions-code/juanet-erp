<?php

namespace App\Models;

use App\Traits\HasOptimisticLocking;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StoredFile extends Model
{
    use HasUuidV7, HasOptimisticLocking;

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'path',
        'disk',
        'mime_type',
        'size',
        'category',
        'visibility',
        'is_temporary',
        'expires_at',
        'virus_scan_status',
        'virus_scan_result',
        'hash',
        'version',
    ];

    protected $casts = [
        'is_temporary' => 'boolean',
        'expires_at' => 'datetime',
        'size' => 'integer',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dynamic public or private URL of the stored file.
     */
    public function getUrl(): string
    {
        if ($this->visibility === 'public') {
            return Storage::disk($this->disk)->url($this->path);
        }

        // For private, you would typically generate a signed URL
        return route('api.files.download', ['id' => $this->id]);
    }

    /**
     * Check if the file is an image.
     */
    public function isImage(): bool
    {
        return $this->category === 'image';
    }

    /**
     * Check if the file is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Helper to get a human-readable file size format.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
