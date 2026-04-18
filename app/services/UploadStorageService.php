<?php

/**
 * UploadStorageService
 *
 * Purpose:
 * Shared file normalization, validation, and physical storage handling.
 */

require_once __DIR__ . '/../../config/constants.php';

class UploadStorageService
{
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Normalize PHP's uploaded-file bag into a simple list.
     */
    public function normalizeFiles(array $rawFiles): array
    {
        if (!isset($rawFiles['name'], $rawFiles['tmp_name'], $rawFiles['error'], $rawFiles['size'])) {
            throw new InvalidArgumentException('files[] is required.');
        }

        $normalized = [];
        $names = is_array($rawFiles['name']) ? $rawFiles['name'] : [$rawFiles['name']];
        $tmpNames = is_array($rawFiles['tmp_name']) ? $rawFiles['tmp_name'] : [$rawFiles['tmp_name']];
        $errors = is_array($rawFiles['error']) ? $rawFiles['error'] : [$rawFiles['error']];
        $sizes = is_array($rawFiles['size']) ? $rawFiles['size'] : [$rawFiles['size']];

        foreach ($names as $index => $name) {
            $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'original_name' => (string) $name,
                'tmp_name' => (string) ($tmpNames[$index] ?? ''),
                'error' => (int) $error,
                'reported_size' => (int) ($sizes[$index] ?? 0),
            ];
        }

        if (count($normalized) === 0) {
            throw new InvalidArgumentException('At least one file is required.');
        }

        return $normalized;
    }

    /**
     * Validate file count, size, extension, and server-side MIME type.
     */
    public function validateFiles(array $files): array
    {
        if (count($files) > MAX_UPLOAD_FILES_PER_SUBMISSION) {
            throw new InvalidArgumentException(
                'A maximum of ' . MAX_UPLOAD_FILES_PER_SUBMISSION . ' files is allowed per submission.'
            );
        }

        $validated = [];
        $maxBytes = MAX_UPLOAD_SIZE_MB * 1024 * 1024;
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('One or more files failed to upload correctly.');
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');

            if ($tmpName === '' || !is_file($tmpName)) {
                throw new InvalidArgumentException('Uploaded file temp path is missing.');
            }

            $actualSize = filesize($tmpName);

            if ($actualSize === false || $actualSize <= 0) {
                throw new InvalidArgumentException('Uploaded file is empty.');
            }

            if ($actualSize > $maxBytes) {
                throw new InvalidArgumentException(
                    'Each file must be at most ' . MAX_UPLOAD_SIZE_MB . ' MB.'
                );
            }

            $extension = strtolower(pathinfo((string) $file['original_name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ALLOWED_UPLOAD_EXTENSIONS, true)) {
                throw new InvalidArgumentException('Unsupported upload file extension.');
            }

            $mimeType = (string) $finfo->file($tmpName);

            if (!in_array($mimeType, ALLOWED_UPLOAD_MIME_TYPES, true)) {
                throw new InvalidArgumentException('Unsupported upload MIME type.');
            }

            $validated[] = [
                'original_name' => $this->sanitizeDisplayFilename((string) $file['original_name']),
                'tmp_name' => $tmpName,
                'size_bytes' => (int) $actualSize,
                'mime_type' => $mimeType,
                'extension' => self::MIME_TO_EXTENSION[$mimeType] ?? $extension,
            ];
        }

        return $validated;
    }

    /**
     * Store one validated file under storage/uploads and return storage metadata.
     */
    public function storeValidatedFile(array $validatedFile, string $parentType, int $parentId): array
    {
        $now = new DateTimeImmutable();
        $relativeDirectory = sprintf(
            'uploads/%s/%s/%s',
            strtolower($parentType),
            $now->format('Y'),
            $now->format('m')
        );
        $absoluteDirectory = $this->getStorageRootPath() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('Failed to create upload storage directory.');
        }

        $prefixMap = [
            'GREEN_BELT' => 'gb',
            'SITE' => 'site',
            'TASK' => 'task',
        ];

        $fileName = sprintf(
            '%s_%d_%s.%s',
            $prefixMap[$parentType] ?? strtolower($parentType),
            $parentId,
            bin2hex(random_bytes(4)),
            $validatedFile['extension']
        );

        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = $relativeDirectory . '/' . $fileName;

        $this->moveIntoStorage((string) $validatedFile['tmp_name'], $absolutePath);

        return [
            'file_path' => $relativePath,
            'mime_type' => $validatedFile['mime_type'],
            'file_size_bytes' => $validatedFile['size_bytes'],
            'original_file_name' => $validatedFile['original_name'],
        ];
    }

    /**
     * Remove a stored file if it exists.
     */
    public function deleteStoredRelativePath(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }

        $absolutePath = $this->getStorageRootPath() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    private function getStorageRootPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }

    private function moveIntoStorage(string $sourcePath, string $destinationPath): void
    {
        $moved = false;

        if (is_uploaded_file($sourcePath)) {
            $moved = move_uploaded_file($sourcePath, $destinationPath);
        } elseif (is_file($sourcePath)) {
            $moved = @rename($sourcePath, $destinationPath);

            if (!$moved) {
                $moved = @copy($sourcePath, $destinationPath);

                if ($moved) {
                    @unlink($sourcePath);
                }
            }
        }

        if (!$moved) {
            throw new RuntimeException('Failed to move uploaded file into storage.');
        }
    }

    private function sanitizeDisplayFilename(string $originalName): string
    {
        $baseName = basename($originalName);
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $baseName);
        $sanitized = trim((string) $sanitized);

        if ($sanitized === '') {
            return 'upload_file';
        }

        return mb_substr($sanitized, 0, 255);
    }
}
