<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageOptimizationService implements ImageOptimizationServiceInterface
{
    /**
     * Optimize an image at a given path.
     */
    public function optimize(string $filePath, string $disk = 'local', int $quality = 80): bool
    {
        if (!extension_loaded('gd')) {
            Log::warning('GD extension is not loaded. Skipping image optimization.');
            return false;
        }

        try {
            $diskStorage = Storage::disk($disk);
            if (!$diskStorage->exists($filePath)) {
                return false;
            }

            $realPath = $diskStorage->path($filePath);
            $mimeType = mime_content_type($realPath);

            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $image = @imagecreatefromjpeg($realPath);
                    if ($image) {
                        imagejpeg($image, $realPath, $quality);
                        imagedestroy($image);
                        return true;
                    }
                    break;

                case 'image/png':
                    $image = @imagecreatefrompng($realPath);
                    if ($image) {
                        // Disable alpha blending, keep transparency
                        imagealphablending($image, false);
                        imagesavealpha($image, true);
                        // PNG compression ranges from 0 (none) to 9
                        $pngQuality = max(0, min(9, (int)round((100 - $quality) / 10)));
                        imagepng($image, $realPath, $pngQuality);
                        imagedestroy($image);
                        return true;
                    }
                    break;

                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = @imagecreatefromwebp($realPath);
                        if ($image) {
                            imagewebp($image, $realPath, $quality);
                            imagedestroy($image);
                            return true;
                        }
                    }
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('Error optimizing image: ' . $e->getMessage());
        }

        return false;
    }
}
