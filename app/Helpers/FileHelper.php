<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class FileHelper
{
    /**
     * Format raw byte sizes to highly legible abbreviations.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Parse human-readable shorthand (e.g., '10M', '2G', '500K') back to absolute bytes integer.
     */
    public static function parseToBytes(string $shorthand): int
    {
        $shorthand = trim($shorthand);
        if (empty($shorthand)) {
            return 0;
        }

        $lastChar = strtolower($shorthand[strlen($shorthand) - 1]);
        $val = (int) $shorthand;

        switch ($lastChar) {
            'g':
                $val *= 1024 * 1024 * 1024;
                break;
            'm':
                $val *= 1024 * 1024;
                break;
            'k':
                $val *= 1024;
                break;
        }

        return $val;
    }

    /**
     * Sanitize and generate a safe filename, preserving the extension.
     */
    public static function makeSafeFilename(string $originalName, ?string $prefix = null): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filenameOnly = pathinfo($originalName, PATHINFO_FILENAME);

        $slugged = Str::slug($filenameOnly, '_');
        if (empty($slugged)) {
            $slugged = 'file_' . uniqid();
        }

        // Add a timestamp and unique suffix to avoid overlaps
        $uniqueName = ($prefix ? $prefix . '_' : '') . $slugged . '_' . time() . '_' . Str::random(4);
        
        return $extension ? $uniqueName . '.' . strtolower($extension) : $uniqueName;
    }

    /**
     * Map common file extensions into high-level categories.
     */
    public static function getCategory(string $filenameOrExtension): string
    {
        $extension = strtolower(pathinfo($filenameOrExtension, PATHINFO_EXTENSION) ?: $filenameOrExtension);

        $categories = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'tiff'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'rtf'],
            'archive' => ['zip', 'rar', 'tar', 'gz', '7z', 'bz2'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'],
            'video' => ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'],
            'code' => ['json', 'xml', 'yaml', 'yml', 'php', 'js', 'ts', 'html', 'css', 'py', 'sh'],
        ];

        foreach ($categories as $cat => $exts) {
            if (in_array($extension, $exts)) {
                return $cat;
            }
        }

        return 'other';
    }
}
