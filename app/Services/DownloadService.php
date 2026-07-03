<?php

namespace App\Services;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadService implements DownloadServiceInterface
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Resolve and return file response for downloading.
     */
    public function download(StoredFile $file, string $userId, ?string $organizationId = null): StreamedResponse|BinaryFileResponse
    {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();

        // 1. Tenant and ownership isolation check
        if ($file->organization_id !== null) {
            // Tenant isolated file. Check if the user has access to this organization/tenant
            if ($file->organization_id !== $orgId) {
                // Check if user is a member of this organization as fallback
                $isMember = \DB::table('organization_members')
                    ->where('organization_id', $file->organization_id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$isMember) {
                    Log::warning("Unauthorized file access attempt: File [{$file->id}], User [{$userId}]");
                    throw new AccessDeniedHttpException("You do not have permission to download files from this organization.");
                }
            }
        } else {
            // System-level file. If private, only the owner can download.
            if ($file->visibility === 'private' && $file->user_id !== $userId) {
                Log::warning("Unauthorized private file access attempt: File [{$file->id}], User [{$userId}]");
                throw new AccessDeniedHttpException("You do not have permission to access this private file.");
            }
        }

        // 2. Malware and infected check
        if ($file->virus_scan_status === 'infected') {
            Log::critical("Blocked download of infected file: File [{$file->id}], Name [{$file->name}], User [{$userId}]");
            throw new AccessDeniedHttpException("Security Alert: This file has been flagged as INFECTED by malware scanner and is quarantined.");
        }

        // 3. Retrieve and output stream
        return $this->resolveResponse($file);
    }

    /**
     * Download an infected/unscanned file if specifically requested by admins.
     */
    public function downloadInfectedForce(StoredFile $file): StreamedResponse|BinaryFileResponse
    {
        return $this->resolveResponse($file);
    }

    /**
     * Helper to resolve the correct storage stream or direct binary response.
     */
    protected function resolveResponse(StoredFile $file): StreamedResponse|BinaryFileResponse
    {
        $disk = Storage::disk($file->disk);

        if (!$disk->exists($file->path)) {
            Log::error("File record exists in DB, but missing physically from disk: File [{$file->id}], Path [{$file->path}], Disk [{$file->disk}]");
            throw new NotFoundHttpException("The requested file does not exist on storage disk.");
        }

        // Check if the disk supports local paths (e.g. local / public disk)
        try {
            $realPath = $disk->path($file->path);
            return response()->download($realPath, $file->name, [
                'Content-Type' => $file->mime_type,
            ]);
        } catch (\InvalidArgumentException $e) {
            // S3, MinIO, or cloud driver that doesn't support local path
            return response()->streamDownload(function () use ($disk, $file) {
                echo $disk->get($file->path);
            }, $file->name, [
                'Content-Type' => $file->mime_type,
            ]);
        }
    }
}
