<?php

namespace App\Services;

use App\Models\StoredFile;

interface SignedUrlServiceInterface
{
    /**
     * Generate a signed temporary URL for a private file.
     */
    public function generate(StoredFile $file, int $expirationMinutes = 60): string;

    /**
     * Verify if a signed URL signature is valid and has not expired.
     */
    public function verify(string $fileId, string $signature, int $expires): bool;
}
