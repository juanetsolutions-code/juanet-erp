<?php

namespace App\Services;

use App\Models\StoredFile;
use Illuminate\Http\UploadedFile;

interface UploadServiceInterface
{
    /**
     * Handle single standard file upload.
     */
    public function upload(
        UploadedFile $file,
        string $userId,
        ?string $organizationId = null,
        string $visibility = 'private',
        bool $isTemporary = false,
        ?int $expiryDays = null
    ): StoredFile;

    /**
     * Handle chunks for chunked upload pipeline.
     */
    public function uploadChunk(
        UploadedFile $chunk,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $filename,
        string $userId,
        ?string $organizationId = null
    ): ?StoredFile;

    /**
     * Run virus scan on an existing stored file.
     */
    public function runVirusScan(StoredFile $file): StoredFile;

    /**
     * Run clean up on expired temporary files.
     * Removes the physical files and drops the db records.
     */
    public function cleanupExpiredFiles(): int;
}
