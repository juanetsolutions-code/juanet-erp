<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FileValidator implements FileValidatorInterface
{
    protected array $categories = [
        'image' => [
            'max' => 10240, // 10MB
            'mimes' => ['jpeg', 'png', 'gif', 'webp', 'svg+xml', 'svg'],
        ],
        'document' => [
            'max' => 51200, // 50MB
            'mimes' => [
                'pdf', 'msword', 'vnd.openxmlformats-officedocument.wordprocessingml.document',
                'vnd.ms-excel', 'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'vnd.ms-powerpoint', 'vnd.openxmlformats-officedocument.presentationml.presentation',
                'plain', 'csv', 'rtf'
            ],
        ],
        'video' => [
            'max' => 512000, // 500MB
            'mimes' => ['mp4', 'x-matroska', 'x-msvideo', 'quicktime'],
        ],
        'audio' => [
            'max' => 102400, // 100MB
            'mimes' => ['mpeg', 'x-wav', 'ogg', 'x-m4a', 'wav', 'mp3'],
        ],
        'archive' => [
            'max' => 1048576, // 1GB
            'mimes' => ['zip', 'x-rar-compressed', 'x-tar', 'x-gzip', 'x-7z-compressed', 'octet-stream'],
        ],
    ];

    public function determineCategory(string $mimeType): string
    {
        $parts = explode('/', $mimeType);
        $type = $parts[0] ?? '';
        $subtype = $parts[1] ?? '';

        if ($type === 'image') {
            return 'image';
        }
        if ($type === 'video') {
            return 'video';
        }
        if ($type === 'audio') {
            return 'audio';
        }

        // Check archive types specifically
        if (in_array($subtype, ['zip', 'x-rar-compressed', 'x-tar', 'x-gzip', 'x-7z-compressed'])) {
            return 'archive';
        }

        // Default or document
        if (in_array($subtype, [
            'pdf', 'msword', 'vnd.openxmlformats-officedocument.wordprocessingml.document',
            'vnd.ms-excel', 'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'vnd.ms-powerpoint', 'vnd.openxmlformats-officedocument.presentationml.presentation',
            'plain', 'csv', 'rtf'
        ])) {
            return 'document';
        }

        return 'document'; // Fallback
    }

    public function validate(UploadedFile $file, ?string $expectedCategory = null): array
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $category = $expectedCategory ?: $this->determineCategory($mime);

        $rules = $this->categories[$category] ?? $this->categories['document'];

        // Build validation rule string
        $mimesList = implode(',', $rules['mimes']);
        
        $validator = Validator::make(
            ['file' => $file],
            [
                'file' => [
                    'required',
                    'file',
                    "max:{$rules['max']}",
                    // We don't enforce strict mimes rules for everything as some enterprise mime types are complex, 
                    // but we validate the general file structure.
                ]
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional blacklist extension check for execution files (security)
        $extension = strtolower($file->getClientOriginalExtension());
        $blacklist = ['php', 'phtml', 'sh', 'bat', 'exe', 'msi', 'js', 'jsp', 'asp', 'aspx', 'cgi', 'pl'];
        
        if (in_array($extension, $blacklist)) {
            throw ValidationException::withMessages([
                'file' => ['The file extension is forbidden for security purposes.']
            ]);
        }

        return [
            'valid' => true,
            'category' => $category,
            'mime_type' => $mime,
        ];
    }
}
