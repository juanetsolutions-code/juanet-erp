<?php

namespace App\Services;

interface ImageOptimizationServiceInterface
{
    /**
     * Optimize an image at a given path.
     * Reduces filesize, strips metadata, and matches quality target.
     */
    public function optimize(string $filePath, string $disk = 'local', int $quality = 80): bool;
}
