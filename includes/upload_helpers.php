<?php
function ensureUploadDirectory($absoluteDirectory) {
    if (!is_dir($absoluteDirectory)) {
        mkdir($absoluteDirectory, 0755, true);
    }
}

function detectMimeType($tmpName) {
    if (!is_file($tmpName)) {
        return null;
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            return $mime ?: null;
        }
    }

    return null;
}

function saveUploadedFile($inputName, $absoluteDirectory, $relativeDirectory, array $allowedExtensions, array $allowedMimeTypes, $prefix, $maxBytes = 5242880) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed for ' . $inputName . '.');
    }

    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('The uploaded file for ' . $inputName . ' is too large.');
    }

    $originalName = basename((string) $file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('The uploaded file type is not allowed.');
    }

    $mimeType = detectMimeType($file['tmp_name']);
    if ($mimeType !== null && !in_array($mimeType, $allowedMimeTypes, true)) {
        throw new RuntimeException('The uploaded file content does not match the allowed file types.');
    }

    ensureUploadDirectory($absoluteDirectory);

    $generatedName = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $absolutePath = rtrim($absoluteDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $generatedName;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('Could not store the uploaded file.');
    }

    return [
        'path' => rtrim($relativeDirectory, '/') . '/' . $generatedName,
        'original_name' => $originalName,
        'mime' => $mimeType,
        'size' => (int) $file['size'],
    ];
}

function deleteRelativeFileIfExists($relativePath) {
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) $relativePath, '/'));
    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}
?>
