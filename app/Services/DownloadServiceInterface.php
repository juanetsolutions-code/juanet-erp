<?php

namespace App\Services;

use App\Models\StoredFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DownloadServiceInterface
{
    /**
     * Resolve and return file response for downloading.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function download(StoredFile $file, string $userId, ?string $organizationId = null): StreamedResponse|BinaryFileResponse;

    /**
     * Download an infected/unscanned file if specifically requested by admins.
     */
    public function downloadInfectedForce(StoredFile $file): StreamedResponse|BinaryFileResponse;
}
