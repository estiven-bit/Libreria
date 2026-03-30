<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/UploadService.php';

class UploadController
{
    private UploadService $service;

    public function __construct(UploadService $service)
    {
        $this->service = $service;
    }

    public function upload(): void
    {
        if (!isset($_FILES['image'])) {
            Response::json(['error' => 'No image provided'], 422);
        }

        $url = $this->service->uploadImage($_FILES['image']);
        if (!$url) {
            Response::json(['error' => 'Upload failed'], 400);
        }

        Response::json(['image_url' => $url], 201);
    }
}
