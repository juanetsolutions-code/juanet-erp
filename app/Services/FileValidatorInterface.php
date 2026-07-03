<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

interface FileValidatorInterface
{
    /**
     * Validate an uploaded file against specified rules or default category constraints.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(UploadedFile $file, ?string $expectedCategory = null): array;

    /**
     * Determine the category (image, document, video, audio, archive) from a mime type.
     */
    public function determineCategory(string $mimeType): string;
}
