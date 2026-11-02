<?php
define('MAX_UPLOAD_SIZE_BYTES', 2 * 1024 * 1024);

function handle_image_upload(array $file, string $targetSubdir, ?string $oldFilePath = null): array {
    if (empty($file['name']) || !isset($file['error'])) {
        return ['success' => false, 'path' => null, 'filename' => null, 'error' => 'No file was submitted for upload.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write upload file to server disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        return [
            'success'  => false,
            'path'     => null,
            'filename' => null,
            'error'    => $uploadErrors[$file['error']] ?? 'Unknown upload error occurred.'
        ];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
        $sizeMB = round($file['size'] / (1024 * 1024), 2);
        return [
            'success'  => false,
            'path'     => null,
            'filename' => null,
            'error'    => "Uploaded file size ({$sizeMB}MB) exceeds the maximum allowed limit of 2MB."
        ];
    }

    if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        return ['success' => false, 'path' => null, 'filename' => null, 'error' => 'Unable to read uploaded temporary file.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($detectedMime, $allowedMimes)) {
        return [
            'success'  => false,
            'path'     => null,
            'filename' => null,
            'error'    => "Invalid file format detected ({$detectedMime}). Only JPEG, PNG, and WebP images are permitted."
        ];
    }

    $extension = $allowedMimes[$detectedMime];

    $newFilename = uniqid('img_', true) . '_' . time() . '.' . $extension;

    $baseUploadsDir = dirname(__DIR__) . '/uploads/' . trim($targetSubdir, '/\\');
    if (!is_dir($baseUploadsDir)) {
        if (!mkdir($baseUploadsDir, 0755, true) && !is_dir($baseUploadsDir)) {
            return [
                'success'  => false,
                'path'     => null,
                'filename' => null,
                'error'    => "Failed to initialize destination upload directory on server."
            ];
        }
    }

    $destinationPath = $baseUploadsDir . '/' . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        return [
            'success'  => false,
            'path'     => null,
            'filename' => null,
            'error'    => "Failed to save uploaded file into server storage directory."
        ];
    }

    $relativeStoragePath = 'uploads/' . trim($targetSubdir, '/\\') . '/' . $newFilename;

    if (!empty($oldFilePath)) {
        $oldAbsPath = dirname(__DIR__) . '/' . ltrim($oldFilePath, '/\\');
        if (file_exists($oldAbsPath) && is_file($oldAbsPath)) {
            @unlink($oldAbsPath);
        }
    }

    return [
        'success'  => true,
        'path'     => $relativeStoragePath,
        'filename' => $newFilename,
        'error'    => null
    ];
}

function normalize_files_array(array $filesInput): array {
    $normalized = [];
    if (!isset($filesInput['name']) || !is_array($filesInput['name'])) {
        return $normalized;
    }

    $count = count($filesInput['name']);
    for ($i = 0; $i < $count; $i++) {
        if (!isset($filesInput['error'][$i]) || $filesInput['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $normalized[] = [
            'name'     => $filesInput['name'][$i] ?? '',
            'type'     => $filesInput['type'][$i] ?? '',
            'tmp_name' => $filesInput['tmp_name'][$i] ?? '',
            'error'    => $filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $filesInput['size'][$i] ?? 0
        ];
    }

    return $normalized;
}
