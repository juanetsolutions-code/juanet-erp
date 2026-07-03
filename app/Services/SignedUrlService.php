<?php

namespace App\Services;

use App\Models\StoredFile;
use Illuminate\Support\Facades\URL;

class SignedUrlService implements SignedUrlServiceInterface
{
    /**
     * Generate a signed temporary URL for a private file.
     */
    public function generate(StoredFile $file, int $expirationMinutes = 60): string
    {
        $expires = time() + ($expirationMinutes * 60);
        $appKey = config('app.key');

        // Generate HMAC signature of file ID + expiration
        $signature = hash_hmac('sha256', $file->id . '|' . $expires, $appKey);

        return route('api.files.download-signed', [
            'id' => $file->id,
            'expires' => $expires,
            'signature' => $signature
        ]);
    }

    /**
     * Verify if a signed URL signature is valid and has not expired.
     */
    public function verify(string $fileId, string $signature, int $expires): bool
    {
        if (time() > $expires) {
            return false;
        }

        $appKey = config('app.key');
        $expectedSignature = hash_hmac('sha256', $fileId . '|' . $expires, $appKey);

        return hash_equals($expectedSignature, $signature);
    }
}
