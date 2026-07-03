<?php

namespace App\Services;

interface ThumbnailGeneratorInterface
{
    /**
     * Generate a thumbnail for a file.
     *
     * @return string|null Path to the generated thumbnail file
     */
    public function generate(string $filePath, string $disk = 'local', int $width = 150, int $height = 150): ?string;
}
