<?php

namespace App\Services;

interface VirusScannerInterface
{
    /**
     * Scan a file for viruses.
     *
     * @return array{status: string, result: string} Status can be 'clean', 'infected', or 'skipped'
     */
    public function scan(string $filePath, string $disk = 'local'): array;
}
