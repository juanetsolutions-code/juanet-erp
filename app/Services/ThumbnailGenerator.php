<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ThumbnailGenerator implements ThumbnailGeneratorInterface
{
    /**
     * Generate a thumbnail for a file.
     */
    public function generate(string $filePath, string $disk = 'local', int $width = 150, int $height = 150): ?string
    {
        if (!extension_loaded('gd')) {
            Log::warning('GD extension is not loaded. Cannot generate thumbnail.');
            return null;
        }

        try {
            $diskStorage = Storage::disk($disk);
            if (!$diskStorage->exists($filePath)) {
                return null;
            }

            $realPath = $diskStorage->path($filePath);
            $mimeType = mime_content_type($realPath);

            // Determine directory and thumbnail path
            $dir = dirname($filePath);
            $filename = basename($filePath);
            $thumbPath = $dir . '/thumbnails/thumb_' . $filename;

            // Make sure thumbnails directory exists
            $thumbDir = dirname($realPath) . '/thumbnails';
            if (!file_exists($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            $sourceImage = null;

            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = @imagecreatefromjpeg($realPath);
                    break;
                case 'image/png':
                    $sourceImage = @imagecreatefrompng($realPath);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $sourceImage = @imagecreatefromwebp($realPath);
                    }
                    break;
            }

            if (!$sourceImage) {
                return null; // Unsupported or not an image
            }

            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            // Create target blank canvas
            $targetImage = imagecreatetruecolor($width, $height);

            // Preserve alpha transparency for PNGs / WebPs
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefilledrectangle($targetImage, 0, 0, $width, $height, $transparent);
            }

            // Crop to center
            $ratio = max($width / $origWidth, $height / $origHeight);
            $cropWidth = (int)($width / $ratio);
            $cropHeight = (int)($height / $ratio);
            $cropX = (int)(($origWidth - $cropWidth) / 2);
            $cropY = (int)(($origHeight - $cropHeight) / 2);

            imagecopyresampled(
                $targetImage, $sourceImage,
                0, 0,
                $cropX, $cropY,
                $width, $height,
                $cropWidth, $cropHeight
            );

            // Save thumbnail matching original format
            $thumbFullPath = $thumbDir . '/thumb_' . $filename;

            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($targetImage, $thumbFullPath, 80);
                    break;
                case 'image/png':
                    imagepng($targetImage, $thumbFullPath, 6);
                    break;
                case 'image/webp':
                    imagewebp($targetImage, $thumbFullPath, 80);
                    break;
            }

            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            return $thumbPath;

        } catch (\Throwable $e) {
            Log::error('Error generating thumbnail: ' . $e->getMessage());
        }

        return null;
    }
}
