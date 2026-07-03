<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use App\Repositories\FileRepositoryInterface;
use App\Services\UploadServiceInterface;
use App\Services\DownloadServiceInterface;
use App\Services\SignedUrlServiceInterface;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    protected FileRepositoryInterface $repository;
    protected UploadServiceInterface $uploadService;
    protected DownloadServiceInterface $downloadService;
    protected SignedUrlServiceInterface $signedUrlService;
    protected TenantContext $tenantContext;

    public function __construct(
        FileRepositoryInterface $repository,
        UploadServiceInterface $uploadService,
        DownloadServiceInterface $downloadService,
        SignedUrlServiceInterface $signedUrlService,
        TenantContext $tenantContext
    ) {
        $this->repository = $repository;
        $this->uploadService = $uploadService;
        $this->downloadService = $downloadService;
        $this->signedUrlService = $signedUrlService;
        $this->tenantContext = $tenantContext;
    }

    /**
     * List stored files.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();
        $category = $request->input('category');
        $query = $request->input('query');

        if (!empty($query)) {
            $files = $this->repository->search($query, $orgId, $user->id);
        } else {
            $files = $this->repository->getByOrganization($orgId, null, $category);
        }

        // Add formatted urls and sizes
        $files = $files->map(function (StoredFile $file) {
            return array_merge($file->toArray(), [
                'formatted_size' => $file->formatted_size,
                'url' => $file->getUrl(),
                'is_image' => $file->isImage(),
                'thumbnail_url' => $file->isImage() ? Storage::disk($file->disk)->url(dirname($file->path) . '/thumbnails/thumb_' . basename($file->path)) : null,
            ]);
        });

        return response()->json([
            'status' => 'success',
            'data' => $files,
        ]);
    }

    /**
     * Upload a new file (standard or chunked).
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();

        // 1. Is this a chunked upload?
        if ($request->has('chunk_index')) {
            $validated = $request->validate([
                'file' => 'required|file',
                'upload_id' => 'required|string',
                'chunk_index' => 'required|integer|min:0',
                'total_chunks' => 'required|integer|min:1',
                'filename' => 'required|string',
            ]);

            $storedFile = $this->uploadService->uploadChunk(
                $request->file('file'),
                $validated['upload_id'],
                $validated['chunk_index'],
                $validated['total_chunks'],
                $validated['filename'],
                $user->id,
                $orgId
            );

            if ($storedFile) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Chunked upload complete.',
                    'data' => array_merge($storedFile->toArray(), [
                        'formatted_size' => $storedFile->formatted_size,
                        'url' => $storedFile->getUrl(),
                    ]),
                ]);
            }

            return response()->json([
                'status' => 'chunk_success',
                'message' => "Chunk {$validated['chunk_index']} received successfully.",
            ]);
        }

        // 2. Standard Upload
        $request->validate([
            'file' => 'required|file',
            'visibility' => 'sometimes|string|in:private,public',
            'is_temporary' => 'sometimes|boolean',
            'expiry_days' => 'sometimes|integer|min:1',
        ]);

        try {
            $storedFile = $this->uploadService->upload(
                $request->file('file'),
                $user->id,
                $orgId,
                $request->input('visibility', 'private'),
                $request->boolean('is_temporary', false),
                $request->input('expiry_days') ? (int)$request->input('expiry_days') : null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'File uploaded successfully.',
                'data' => array_merge($storedFile->toArray(), [
                    'formatted_size' => $storedFile->formatted_size,
                    'url' => $storedFile->getUrl(),
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a file.
     */
    public function download(string $id): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        $user = Auth::user();
        $file = $this->repository->find($id);

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found.',
            ], 404);
        }

        try {
            return $this->downloadService->download($file, $user->id);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * Download a file with a signed URL.
     */
    public function downloadSigned(string $id, Request $request): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        $signature = $request->query('signature');
        $expires = (int)$request->query('expires');

        if (!$signature || !$expires) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or missing signature credentials.',
            ], 400);
        }

        if (!$this->signedUrlService->verify($id, $signature, $expires)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This signed URL has either expired or is corrupted.',
            ], 403);
        }

        $file = $this->repository->find($id);
        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found.',
            ], 404);
        }

        // When downloading with a valid signed URL, the user's explicit authorization is pre-verified.
        // We bypass standard ownership checking, but still prevent downloading infected items.
        if ($file->virus_scan_status === 'infected') {
            return response()->json([
                'status' => 'error',
                'message' => 'Quarantined file download is blocked for security.',
            ], 403);
        }

        try {
            return $this->downloadService->downloadInfectedForce($file);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate signed URL for a file.
     */
    public function generateSignedUrl(string $id, Request $request): JsonResponse
    {
        $file = $this->repository->find($id);

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found.',
            ], 404);
        }

        $minutes = $request->input('expires', 60);
        $url = $this->signedUrlService->generate($file, (int)$minutes);

        return response()->json([
            'status' => 'success',
            'signed_url' => $url,
            'expires_at' => Carbon::now()->addMinutes($minutes)->toIso8601String(),
        ]);
    }

    /**
     * Delete file physically and from DB.
     */
    public function destroy(string $id): JsonResponse
    {
        $file = $this->repository->find($id);

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found.',
            ], 404);
        }

        // Physically delete from Storage
        try {
            $diskStorage = Storage::disk($file->disk);
            if ($diskStorage->exists($file->path)) {
                $diskStorage->delete($file->path);
            }

            // Also delete thumbnail if any
            $thumbPath = dirname($file->path) . '/thumbnails/thumb_' . basename($file->path);
            if ($diskStorage->exists($thumbPath)) {
                $diskStorage->delete($thumbPath);
            }
        } catch (\Throwable $e) {
            // Keep deleting DB record even if physical file fails
        }

        $this->repository->delete($id);

        return response()->json([
            'status' => 'success',
            'message' => 'File deleted successfully.',
        ]);
    }

    /**
     * Run / re-run a security virus scan.
     */
    public function scan(string $id): JsonResponse
    {
        $file = $this->repository->find($id);

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found.',
            ], 404);
        }

        $updatedFile = $this->uploadService->runVirusScan($file);

        return response()->json([
            'status' => 'success',
            'message' => 'Virus scan completed.',
            'data' => [
                'status' => $updatedFile->virus_scan_status,
                'result' => $updatedFile->virus_scan_result,
            ],
        ]);
    }

    /**
     * Web storage explorer index.
     */
    public function webIndex()
    {
        $user = Auth::user();
        $orgId = $this->tenantContext->getTenantId();

        $files = $this->repository->getByOrganization($orgId);

        return view('storage.index', [
            'files' => $files,
            'currentTenant' => $this->tenantContext->getTenant(),
        ]);
    }
}
