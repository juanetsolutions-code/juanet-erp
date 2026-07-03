<?php

namespace App\Observers;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StoredFileObserver
{
    /**
     * Handle the StoredFile "deleted" event.
     */
    public function deleted(StoredFile $storedFile): void
    {
        try {
            if ($storedFile->path && Storage::disk($storedFile->disk)->exists($storedFile->path)) {
                Storage::disk($storedFile->disk)->delete($storedFile->path);
                Log::info("StoredFileObserver: Deleted physical file from storage disk.", [
                    'file_id' => $storedFile->id,
                    'disk' => $storedFile->disk,
                    'path' => $storedFile->path
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("StoredFileObserver: Failed to delete physical file from disk during model delete: " . $e->getMessage(), [
                'file_id' => $storedFile->id,
                'disk' => $storedFile->disk,
                'path' => $storedFile->path
            ]);
        }
    }
}
