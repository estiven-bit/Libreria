<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/Sanitizer.php';

class ProductController
{
    private PDO $db;
    private array $app;

    public function __construct(PDO $db, array $app = [])
    {
        $this->db = $db;
        $this->app = $app;
    }

    public function list(array $filters): void
    {
        $model = new Product($this->db);
        $products = $model->list($filters);
        Response::json(['data' => $products]);
    }

    public function show(int $id): void
    {
        $model = new Product($this->db);
        $product = $model->findWithDetails($id);
        if (!$product) {
            Response::json(['error' => 'Product not found'], 404);
        }

        $images = $product['images'] ?? [];
        $primary = $images[0]['image_url'] ?? null;
        $product['image_url'] = $primary;

        Response::json(['data' => $product]);
    }

    public function create(array $data): void
    {
        $model = new Product($this->db);
        $id = $model->create([
            'name' => Sanitizer::string($data['name'] ?? ''),
            'description' => Sanitizer::string($data['description'] ?? ''),
            'price' => (float)($data['price'] ?? 0),
            'stock' => (int)($data['stock'] ?? 0),
            'category_id' => (int)($data['category_id'] ?? 0),
        ]);
        Response::json(['message' => 'Product created', 'id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        $model = new Product($this->db);
        $model->update($id, [
            'name' => Sanitizer::string($data['name'] ?? ''),
            'description' => Sanitizer::string($data['description'] ?? ''),
            'price' => (float)($data['price'] ?? 0),
            'stock' => (int)($data['stock'] ?? 0),
            'category_id' => (int)($data['category_id'] ?? 0),
        ]);
        Response::json(['message' => 'Product updated']);
    }

    public function delete(int $id): void
    {
        $model = new Product($this->db);
        $model->delete($id);
        Response::json(['message' => 'Product deleted']);
    }

    public function uploadProductImage(int $productId): void
    {
        $model = new Product($this->db);
        if (!$model->find($productId)) {
            Response::json(['error' => 'Product not found'], 404);
        }

        if (!isset($_FILES['image']) || (int)($_FILES['image']['error'] ?? 0) !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'No image provided'], 422);
        }

        $file = $_FILES['image'];
        $tmp = $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            Response::json(['error' => 'Invalid upload'], 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            Response::json(['error' => 'Solo se permiten JPG o PNG'], 422);
        }

        // Convertir la imagen a un data URL base64 y guardarla directamente en BD
        $imgData = file_get_contents($tmp);
        $base64 = base64_encode($imgData);
        $url = 'data:' . $mime . ';base64,' . $base64;

        $stmt = $this->db->prepare('INSERT INTO product_images (product_id, image_url) VALUES (:pid, :url)');
        $stmt->execute(['pid' => $productId, 'url' => $url]);

        Response::json([
            'message' => 'Image uploaded',
            'image_url' => $url,
            'id' => (int)$this->db->lastInsertId(),
        ], 201);
    }
}
