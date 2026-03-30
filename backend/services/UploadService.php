<?php

class UploadService
{
    private string $uploadsPath;

    public function __construct(string $uploadsPath)
    {
        $this->uploadsPath = $uploadsPath;
    }

    public function uploadImage(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            return null;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $ext;
        $target = $this->uploadsPath . '/' . $filename;

        if (!is_dir($this->uploadsPath)) {
            mkdir($this->uploadsPath, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $target)) {
            return '/uploads/' . $filename;
        }

        return null;
    }
}
