<?php

namespace App\Services;

use App\Models\StoredFile;
use App\Repositories\FileRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UploadService implements UploadServiceInterface
{
    protected FileRepositoryInterface $repository;
    protected FileValidatorInterface $validator;
    protected VirusScannerInterface $virusScanner;
    protected ImageOptimizationServiceInterface $optimizer;
    protected ThumbnailGeneratorInterface $thumbnailGenerator;
    protected TenantContext $tenantContext;

    public function __construct(
        FileRepositoryInterface $repository,
        FileValidatorInterface $validator,
        VirusScannerInterface $virusScanner,
        ImageOptimizationServiceInterface $optimizer,
        ThumbnailGeneratorInterface $thumbnailGenerator,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
        $this->virusScanner = $virusScanner;
        $this->optimizer = $optimizer;
        $this->thumbnailGenerator = $thumbnailGenerator;
        $this->tenantContext = $tenantContext;
    }

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
    ): StoredFile {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();

        // 1. Validate the file format, size, mime type
        $validationResult = $this->validator->validate($file);
        $category = $validationResult['category'];
        $mimeType = $validationResult['mime_type'];

        // 2. Compute isolations path & unique file identifier
        $tenantPrefix = $orgId ? "tenants/{$orgId}" : "system";
        $uniqueId = Str::uuid()->toString();
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $targetFilename = "{$uniqueId}.{$extension}";
        
        $relativeFolder = "{$tenantPrefix}/{$category}";
        $disk = $visibility === 'public' ? 'public' : 'local';

        // 3. Store the physical file
        $path = $file->storeAs($relativeFolder, $targetFilename, $disk);

        if (!$path) {
            throw new \RuntimeException("Failed to store file on disk: {$disk}");
        }

        // Calculate SHA-256 hash
        $realPath = Storage::disk($disk)->path($path);
        $fileHash = hash_file('sha256', $realPath) ?: null;

        // 4. Optimize if it's an image
        if ($category === 'image') {
            $this->optimizer->optimize($path, $disk);
            // Optionally regenerate thumbnail
            $this->thumbnailGenerator->generate($path, $disk);
        }

        // 5. Initialize Database Record (marked as pending virus scan)
        $expiresAt = null;
        if ($isTemporary && $expiryDays) {
            $expiresAt = Carbon::now()->addDays($expiryDays);
        }

        $storedFile = $this->repository->create([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'name' => $originalName,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'category' => $category,
            'visibility' => $visibility,
            'is_temporary' => $isTemporary,
            'expires_at' => $expiresAt,
            'virus_scan_status' => 'pending',
            'virus_scan_result' => null,
            'hash' => $fileHash,
        ]);

        // 6. Run Virus scan synchronously
        $storedFile = $this->runVirusScan($storedFile);

        // Security logging
        Log::info("File uploaded: [{$storedFile->id}] Name: {$originalName} by User: {$userId} (Virus Scan: {$storedFile->virus_scan_status})");

        return $storedFile;
    }

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
    ): ?StoredFile {
        $orgId = $organizationId ?? $this->tenantContext->getTenantId();
        $safeUploadId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $uploadId);
        
        $tempDir = "chunks/{$safeUploadId}";
        $chunkName = "chunk_{$chunkIndex}";

        // Save chunk in transient storage
        $chunkPath = $chunk->storeAs($tempDir, $chunkName, 'local');

        if (!$chunkPath) {
            throw new \RuntimeException("Failed to store chunk {$chunkIndex}");
        }

        // If this is the final chunk, assemble the file
        $chunksCount = count(Storage::disk('local')->files($tempDir));
        if ($chunksCount === $totalChunks) {
            // Re-assemble
            $finalTempPath = storage_path("app/temp_{$safeUploadId}");
            $out = fopen($finalTempPath, 'wb');

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFilePath = Storage::disk('local')->path("{$tempDir}/chunk_{$i}");
                $in = fopen($chunkFilePath, 'rb');
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
                fclose($in);
            }
            fclose($out);

            // Wrap in Laravel UploadedFile
            $assembledFile = new UploadedFile(
                $finalTempPath,
                $filename,
                mime_content_type($finalTempPath),
                null,
                true // test mode
            );

            try {
                // Perform standard upload
                $storedFile = $this->upload($assembledFile, $userId, $orgId);
            } finally {
                // Clean up temporary chunks directory & temp assembled file
                Storage::disk('local')->deleteDirectory($tempDir);
                if (file_exists($finalTempPath)) {
                    unlink($finalTempPath);
                }
            }

            return $storedFile;
        }

        return null;
    }

    /**
     * Run virus scan on an existing stored file.
     */
    public function runVirusScan(StoredFile $file): StoredFile
    {
        try {
            $scanResult = $this->virusScanner->scan($file->path, $file->disk);
            
            $file->update([
                'virus_scan_status' => $scanResult['status'],
                'virus_scan_result' => $scanResult['result'],
            ]);

            if ($scanResult['status'] === 'infected') {
                Log::critical("SECURITY INCIDENT: Infected file uploaded! File ID: [{$file->id}], Name: {$file->name}, Result: {$scanResult['result']}");
                // In production, we could delete/quarantine or notify security log
                if (app()->bound(\App\Services\SecurityLogServiceInterface::class)) {
                    app(\App\Services\SecurityLogServiceInterface::class)->log(
                        "File infected signature detected: {$file->name}",
                        'medium',
                        'security',
                        $file->user_id
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to virus scan file {$file->id}: " . $e->getMessage());
            $file->update([
                'virus_scan_status' => 'skipped',
                'virus_scan_result' => 'Scanner error: ' . $e->getMessage(),
            ]);
        }

        return $file;
    }

    /**
     * Run clean up on expired temporary files.
     */
    public function cleanupExpiredFiles(): int
    {
        $expiredFiles = $this->repository->getExpiredTemporaryFiles();
        $count = 0;

        foreach ($expiredFiles as $file) {
            try {
                // Delete physical files & thumbnails
                $diskStorage = Storage::disk($file->disk);
                if ($diskStorage->exists($file->path)) {
                    $diskStorage->delete($file->path);
                }

                $thumbPath = dirname($file->path) . '/thumbnails/thumb_' . basename($file->path);
                if ($diskStorage->exists($thumbPath)) {
                    $diskStorage->delete($thumbPath);
                }

                // Delete db record
                $this->repository->delete($file->id);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed to clean up temporary file {$file->id}: " . $e->getMessage());
            }
        }

        if ($count > 0) {
            Log::info("Automatic cleanup completed. Deleted {$count} expired temporary files.");
        }

        return $count;
    }
}
