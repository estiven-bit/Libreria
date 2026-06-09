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

        $json = json_encode(['data' => $product], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            Response::json(['error' => 'Error al serializar el producto'], 500);
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo $json;
        exit;
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

        // Comprimir y redimensionar la imagen si es demasiado grande antes de guardarla
        $imgData = $this->compressAndResizeImage($tmp, $mime);
        if (strlen($imgData) > 900000) {
            Response::json(['error' => 'La imagen sigue siendo demasiado grande tras comprimirla. Prueba con otra más pequeña.'], 422);
        }

        $base64 = base64_encode($imgData);
        $url = 'data:' . $mime . ';base64,' . $base64;

        try {
            $stmt = $this->db->prepare('INSERT INTO product_images (product_id, image_url) VALUES (:pid, :url)');
            $stmt->execute(['pid' => $productId, 'url' => $url]);
        } catch (\PDOException $e) {
            Response::json(['error' => 'No se pudo guardar la imagen en la base de datos'], 500);
        }

        Response::json([
            'message' => 'Image uploaded',
            'image_url' => $url,
            'id' => (int)$this->db->lastInsertId(),
        ], 201);
    }

    /**
     * Redimensiona la imagen a max 1000px y la comprime para evitar superar
     * el límite de max_allowed_packet de MySQL al guardarla como base64.
     */
    private function compressAndResizeImage(string $filePath, string $mime): string
    {
        if (!extension_loaded('gd') || !in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return file_get_contents($filePath);
        }

        $srcImage = $mime === 'image/jpeg' 
            ? @imagecreatefromjpeg($filePath) 
            : @imagecreatefrompng($filePath);

        if (!$srcImage) {
            return file_get_contents($filePath);
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $maxSize = 1000;

        if ($width > $maxSize || $height > $maxSize) {
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = (int)($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth = (int)($width * ($maxSize / $height));
            }

            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
            
            if ($mime === 'image/png') {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
            }

            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($srcImage);
            $srcImage = $dstImage;
        }

        ob_start();
        if ($mime === 'image/jpeg') {
            imagejpeg($srcImage, null, 75); // 75% calidad para JPG
        } else {
            imagepng($srcImage, null, 6); // 6/9 compresión para PNG
        }
        $data = ob_get_clean();
        imagedestroy($srcImage);

        return $data;
    }
}
